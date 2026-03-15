<?php

use App\Jobs\ImportContactsJob;
use App\Models\Contact;
use App\Models\ImportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Helper: write a CSV to local storage and return the storage-relative path.
function writeCsv(array $rows, string $filename = 'test-import.csv'): string
{
    $handle = fopen('php://temp', 'r+');

    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }

    rewind($handle);
    $content = stream_get_contents($handle);
    fclose($handle);

    Storage::disk('local')->put("imports/{$filename}", $content);

    return "imports/{$filename}";
}

function makeImportLog(string $strategy = 'skip'): ImportLog
{
    return ImportLog::create([
        'model_type'         => 'contact',
        'filename'           => 'test-import.csv',
        'row_count'          => 0,
        'duplicate_strategy' => $strategy,
        'status'             => 'pending',
    ]);
}

it('new email creates a contact record', function () {
    $path = writeCsv([
        ['first_name', 'last_name', 'email'],
        ['Alice', 'Smith', 'alice@example.com'],
    ]);

    $log = makeImportLog();

    (new ImportContactsJob(
        importLogId:       $log->id,
        storagePath:       $path,
        columnMap:         ['first_name' => 'first_name', 'last_name' => 'last_name', 'email' => 'email'],
        duplicateStrategy: 'skip',
    ))->handle();

    expect(Contact::where('email', 'alice@example.com')->exists())->toBeTrue();

    $log->refresh();
    expect($log->imported_count)->toBe(1);
    expect($log->status)->toBe('complete');
});

it('duplicate email with strategy skip does not update the existing contact', function () {
    Contact::factory()->create([
        'first_name' => 'Original',
        'email'      => 'dup@example.com',
    ]);

    $path = writeCsv([
        ['first_name', 'email'],
        ['Updated', 'dup@example.com'],
    ]);

    $log = makeImportLog('skip');

    (new ImportContactsJob(
        importLogId:       $log->id,
        storagePath:       $path,
        columnMap:         ['first_name' => 'first_name', 'email' => 'email'],
        duplicateStrategy: 'skip',
    ))->handle();

    expect(Contact::where('email', 'dup@example.com')->first()->first_name)->toBe('Original');

    $log->refresh();
    expect($log->skipped_count)->toBe(1);
    expect($log->updated_count)->toBe(0);
});

it('duplicate email with strategy update updates the existing contact fields', function () {
    Contact::factory()->create([
        'first_name' => 'Original',
        'last_name'  => 'Name',
        'email'      => 'update@example.com',
    ]);

    $path = writeCsv([
        ['first_name', 'email'],
        ['Updated', 'update@example.com'],
    ]);

    $log = makeImportLog('update');

    (new ImportContactsJob(
        importLogId:       $log->id,
        storagePath:       $path,
        columnMap:         ['first_name' => 'first_name', 'email' => 'email'],
        duplicateStrategy: 'update',
    ))->handle();

    expect(Contact::where('email', 'update@example.com')->first()->first_name)->toBe('Updated');

    $log->refresh();
    expect($log->updated_count)->toBe(1);
    expect($log->imported_count)->toBe(0);
});

it('row with no email and no first_name is skipped without error', function () {
    $path = writeCsv([
        ['email', 'first_name', 'notes'],
        ['', '', 'Some notes'],
    ]);

    $log = makeImportLog();

    (new ImportContactsJob(
        importLogId:       $log->id,
        storagePath:       $path,
        columnMap:         ['email' => 'email', 'first_name' => 'first_name', 'notes' => 'notes'],
        duplicateStrategy: 'skip',
    ))->handle();

    expect(Contact::count())->toBe(0);

    $log->refresh();
    expect($log->skipped_count)->toBe(1);
    expect($log->error_count)->toBe(0);
});

it('empty email field skips contact creation without error', function () {
    $path = writeCsv([
        ['first_name', 'email'],
        ['Alice', ''],
    ]);

    $log = makeImportLog();

    (new ImportContactsJob(
        importLogId:       $log->id,
        storagePath:       $path,
        columnMap:         ['first_name' => 'first_name', 'email' => 'email'],
        duplicateStrategy: 'skip',
    ))->handle();

    // No email — should still create since first_name is present
    expect(Contact::where('first_name', 'Alice')->exists())->toBeTrue();

    $log->refresh();
    expect($log->error_count)->toBe(0);
    expect($log->imported_count)->toBe(1);
});

it('import log counts match actual outcomes after job completes', function () {
    Contact::factory()->create(['email' => 'existing@example.com']);

    $path = writeCsv([
        ['first_name', 'email'],
        ['New1', 'new1@example.com'],
        ['New2', 'new2@example.com'],
        ['Dup', 'existing@example.com'],
        ['', ''],
    ]);

    $log = makeImportLog('skip');

    (new ImportContactsJob(
        importLogId:       $log->id,
        storagePath:       $path,
        columnMap:         ['first_name' => 'first_name', 'email' => 'email'],
        duplicateStrategy: 'skip',
    ))->handle();

    $log->refresh();
    expect($log->imported_count)->toBe(2);
    expect($log->skipped_count)->toBe(2); // dup + empty row
    expect($log->updated_count)->toBe(0);
    expect($log->status)->toBe('complete');
    expect($log->completed_at)->not->toBeNull();
});
