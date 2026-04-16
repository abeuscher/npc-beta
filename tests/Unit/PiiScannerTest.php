<?php

use App\Services\PiiScanner;
use Illuminate\Support\Facades\Storage;

function writePiiCsv(array $rows): string
{
    $handle = fopen('php://temp', 'r+');
    foreach ($rows as $row) {
        fputcsv($handle, $row, ',', '"', '\\');
    }
    rewind($handle);
    $content = stream_get_contents($handle);
    fclose($handle);

    $path = sys_get_temp_dir() . '/' . uniqid('pii-', true) . '.csv';
    file_put_contents($path, $content);

    return $path;
}

it('scan returns empty violations for a clean file', function () {
    $path = writePiiCsv([
        ['first_name', 'email'],
        ['Alice', 'a@example.com'],
    ]);

    $result = (new PiiScanner())->scan($path, ['first_name', 'email']);

    expect($result['violations'])->toBe([])
        ->and($result['header_violation'])->toBeFalse()
        ->and($result['truncated'])->toBeFalse();
});

it('scan returns every violation up to the limit, not just the first', function () {
    $rows = [['first_name', 'identifier']];
    for ($i = 1; $i <= 10; $i++) {
        $rows[] = ["User{$i}", '123-45-6789'];
    }
    $path = writePiiCsv($rows);

    $result = (new PiiScanner())->scan($path, ['first_name', 'identifier'], limit: 50);

    expect($result['violations'])->toHaveCount(10)
        ->and($result['violations'][0]['row'])->toBe(2)
        ->and($result['violations'][9]['row'])->toBe(11)
        ->and($result['truncated'])->toBeFalse();
});

it('scan truncates at the configured limit', function () {
    $rows = [['first_name', 'identifier']];
    for ($i = 1; $i <= 100; $i++) {
        $rows[] = ["User{$i}", '123-45-6789'];
    }
    $path = writePiiCsv($rows);

    $result = (new PiiScanner())->scan($path, ['first_name', 'identifier'], limit: 50);

    expect($result['violations'])->toHaveCount(50)
        ->and($result['truncated'])->toBeTrue();
});

it('scan includes the original row data in each violation', function () {
    $path = writePiiCsv([
        ['first_name', 'identifier'],
        ['Alice', '123-45-6789'],
    ]);

    $result = (new PiiScanner())->scan($path, ['first_name', 'identifier']);

    expect($result['violations'][0]['row_data'])->toBe(['Alice', '123-45-6789']);
});

it('does not flag 9-digit ZIP+4 values when the column header is ZIP-like', function () {
    // "021391234" would match the ABA prefix heuristic, but it's a legitimate
    // US ZIP+4 for a column labelled "Zip Code".
    $path = writePiiCsv([
        ['first_name', 'Zip Code'],
        ['Alice', '021391234'],
        ['Bob',   '323451234'],
    ]);

    $result = (new PiiScanner())->scan($path, ['first_name', 'Zip Code']);

    expect($result['violations'])->toBe([]);
});

it('still flags 9-digit values in non-ZIP columns', function () {
    $path = writePiiCsv([
        ['first_name', 'account'],
        ['Alice', '021391234'],
    ]);

    $result = (new PiiScanner())->scan($path, ['first_name', 'account']);

    expect($result['violations'])->toHaveCount(1)
        ->and($result['violations'][0]['reason'])->toBe('ABA routing number');
});

it('still flags hyphenated SSN even in a ZIP column', function () {
    // If someone puts "123-45-6789" in a ZIP column, we should still catch it.
    $path = writePiiCsv([
        ['first_name', 'zip'],
        ['Alice', '123-45-6789'],
    ]);

    $result = (new PiiScanner())->scan($path, ['first_name', 'zip']);

    expect($result['violations'])->toHaveCount(1)
        ->and($result['violations'][0]['reason'])->toBe('Social Security Number');
});

it('still flags credit card numbers in any column', function () {
    // Valid Luhn test number.
    $path = writePiiCsv([
        ['first_name', 'zip'],
        ['Alice', '4532015112830366'],
    ]);

    $result = (new PiiScanner())->scan($path, ['first_name', 'zip']);

    expect($result['violations'])->toHaveCount(1)
        ->and($result['violations'][0]['reason'])->toBe('credit card number');
});

it('returns a single header-violation entry and never scans rows when a header is blocked', function () {
    $path = writePiiCsv([
        ['first_name', 'ssn'],
        ['Alice', '123-45-6789'],
    ]);

    $result = (new PiiScanner())->scan($path, ['first_name', 'ssn']);

    expect($result['header_violation'])->toBeTrue()
        ->and($result['violations'])->toHaveCount(1)
        ->and($result['violations'][0]['reason'])->toBe('blocked_header');
});

it('scanCell signature remains backward-compatible for FormSubmissionController', function () {
    $scanner = new PiiScanner();

    expect($scanner->scanCell('123-45-6789'))->toBe('Social Security Number')
        ->and($scanner->scanCell('abc'))->toBeNull()
        ->and($scanner->scanCell('021391234'))->toBe('ABA routing number')
        ->and($scanner->scanCell('021391234', isZipColumn: true))->toBeNull();
});
