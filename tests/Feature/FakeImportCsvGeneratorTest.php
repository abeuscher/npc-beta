<?php

use App\Services\Import\CsvTemplateService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->outDir = storage_path('app/fake-csvs-test-' . uniqid());
});

afterEach(function () {
    if (is_dir($this->outDir)) {
        foreach (glob($this->outDir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->outDir);
    }
});

function runFakeImportGenerator(string $out, int $seed = 12345): int
{
    return Artisan::call('csv:generate-fake-imports', [
        '--out'  => $out,
        '--seed' => $seed,
    ]);
}

function readCsv(string $path): array
{
    $fp = fopen($path, 'r');
    $headers = fgetcsv($fp);
    $rows = [];
    while (($row = fgetcsv($fp)) !== false) {
        $rows[] = array_combine($headers, $row);
    }
    fclose($fp);
    return ['headers' => $headers, 'rows' => $rows];
}

it('writes all six CSVs to the configured output directory', function () {
    $code = runFakeImportGenerator($this->outDir);
    expect($code)->toBe(0);

    foreach (['contacts.csv', 'events.csv', 'donations.csv', 'memberships.csv', 'invoice_details.csv', 'notes.csv'] as $f) {
        expect(file_exists($this->outDir . '/' . $f))->toBeTrue("missing {$f}");
    }
});

it('each CSV emits headers matching CsvTemplateService exactly', function () {
    runFakeImportGenerator($this->outDir);

    $pairs = [
        'contacts.csv'        => CsvTemplateService::contactHeaders(),
        'events.csv'          => CsvTemplateService::eventHeaders(),
        'donations.csv'       => CsvTemplateService::donationHeaders(),
        'memberships.csv'     => CsvTemplateService::membershipHeaders(),
        'invoice_details.csv' => CsvTemplateService::invoiceDetailHeaders(),
        'notes.csv'           => CsvTemplateService::noteHeaders(),
    ];

    foreach ($pairs as $file => $canonical) {
        $csv = readCsv($this->outDir . '/' . $file);
        expect($csv['headers'])->toEqual($canonical, "headers for {$file} drift from CsvTemplateService");
    }
});

it('row counts fall within the declared ranges', function () {
    runFakeImportGenerator($this->outDir);

    $ranges = [
        'contacts.csv'        => [200, 250],
        'events.csv'          => [20, 40],
        'donations.csv'       => [300, 500],
        'memberships.csv'     => [50, 100],
        'invoice_details.csv' => [100, 300],
        'notes.csv'           => [300, 600],
    ];

    foreach ($ranges as $file => [$min, $max]) {
        $csv   = readCsv($this->outDir . '/' . $file);
        $count = count($csv['rows']);
        expect($count)
            ->toBeGreaterThanOrEqual($min, "{$file} has {$count} rows, below min {$min}")
            ->toBeLessThanOrEqual($max, "{$file} has {$count} rows, above max {$max}");
    }
});

it('cross-file email references resolve back to contacts.csv', function () {
    runFakeImportGenerator($this->outDir);

    $contacts = readCsv($this->outDir . '/contacts.csv');
    $contactEmails = array_filter(array_column($contacts['rows'], 'Email'));
    $contactEmailSet = array_flip($contactEmails);

    $references = [
        'donations.csv'       => 'Email',
        'memberships.csv'     => 'Email',
        'invoice_details.csv' => 'Email',
        'events.csv'          => 'Contact Email',
        'notes.csv'           => 'Email',
    ];

    foreach ($references as $file => $emailColumn) {
        $csv = readCsv($this->outDir . '/' . $file);
        foreach ($csv['rows'] as $idx => $row) {
            $email = $row[$emailColumn] ?? '';
            if ($email === '') {
                continue;
            }
            expect(isset($contactEmailSet[$email]))
                ->toBeTrue("{$file} row {$idx} references unknown contact email '{$email}'");
        }
    }
});

it('invoice_details.csv groups rows into invoices of 1–5 line items with consistent parent fields', function () {
    runFakeImportGenerator($this->outDir);

    $csv = readCsv($this->outDir . '/invoice_details.csv');
    $groups = [];
    foreach ($csv['rows'] as $row) {
        $groups[$row['Invoice #']][] = $row;
    }

    $parentFields = ['Invoice Date', 'Origin', 'Origin Details', 'Ticket Type', 'Invoice Status', 'Currency', 'Payment Date', 'Payment Type', 'Email'];

    foreach ($groups as $invoiceNumber => $rows) {
        $size = count($rows);
        expect($size)
            ->toBeGreaterThanOrEqual(1)
            ->toBeLessThanOrEqual(5, "invoice {$invoiceNumber} has {$size} rows (max 5)");

        foreach ($parentFields as $field) {
            $values = array_unique(array_map(fn ($r) => $r[$field], $rows));
            expect($values)->toHaveCount(1, "invoice {$invoiceNumber} rows disagree on '{$field}'");
        }
    }
});

it('events.csv groups rows by event with consistent event-level fields', function () {
    runFakeImportGenerator($this->outDir);

    $csv = readCsv($this->outDir . '/events.csv');
    $groups = [];
    foreach ($csv['rows'] as $row) {
        $groups[$row['Event External ID']][] = $row;
    }

    $eventFields = ['Event Title', 'Event Slug', 'Event Description', 'Event Status', 'Event Starts At', 'Event Ends At', 'Event Price'];

    foreach ($groups as $externalId => $rows) {
        $size = count($rows);
        expect($size)
            ->toBeGreaterThanOrEqual(1)
            ->toBeLessThanOrEqual(5, "event {$externalId} has {$size} rows (max 5)");

        foreach ($eventFields as $field) {
            $values = array_unique(array_map(fn ($r) => $r[$field], $rows));
            expect($values)->toHaveCount(1, "event {$externalId} rows disagree on '{$field}'");
        }
    }
});

it('same seed produces identical output (reproducibility)', function () {
    $dir1 = $this->outDir . '-a';
    $dir2 = $this->outDir . '-b';

    runFakeImportGenerator($dir1, 999);
    runFakeImportGenerator($dir2, 999);

    foreach (['contacts.csv', 'events.csv', 'donations.csv', 'memberships.csv', 'invoice_details.csv', 'notes.csv'] as $f) {
        expect(file_get_contents("{$dir1}/{$f}"))->toEqual(file_get_contents("{$dir2}/{$f}"), "{$f} differs between two --seed=999 runs");
    }

    foreach ([$dir1, $dir2] as $d) {
        foreach (glob($d . '/*') ?: [] as $x) { @unlink($x); }
        @rmdir($d);
    }
});

it('overwrites prior-run CSVs in the same out dir', function () {
    runFakeImportGenerator($this->outDir, 1);
    $firstFingerprint = filesize($this->outDir . '/contacts.csv') . ':' . md5_file($this->outDir . '/contacts.csv');

    runFakeImportGenerator($this->outDir, 2);
    $secondFingerprint = filesize($this->outDir . '/contacts.csv') . ':' . md5_file($this->outDir . '/contacts.csv');

    expect($secondFingerprint)->not->toEqual($firstFingerprint);
});
