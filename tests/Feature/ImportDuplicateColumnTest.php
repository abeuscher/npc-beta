<?php

use App\Filament\Pages\ImportProgressPage;
use App\Models\Contact;
use App\Models\ImportLog;
use App\Models\ImportSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    putenv('IMPORTER_SKIP_PII_CHECK=1');
    $_ENV['IMPORTER_SKIP_PII_CHECK']    = '1';
    $_SERVER['IMPORTER_SKIP_PII_CHECK'] = '1';
    $this->admin = User::factory()->create();
    $this->actingAs($this->admin);
});

function dupCsv(array $rows): string
{
    $handle = fopen('php://temp', 'r+');
    foreach ($rows as $row) {
        fputcsv($handle, $row, ',', '"', '\\');
    }
    rewind($handle);
    $content = stream_get_contents($handle);
    fclose($handle);

    $path = 'imports/' . uniqid('dup-', true) . '.csv';
    Storage::disk('local')->put($path, $content);

    return $path;
}

function runDupImport(ImportLog $log, User $admin): ImportProgressPage
{
    $session = ImportSession::create([
        'model_type'  => 'contact',
        'status'      => 'pending',
        'filename'    => $log->filename,
        'row_count'   => $log->row_count,
        'imported_by' => $admin->id,
    ]);

    $page = new ImportProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = $session->id;
    $page->mount();
    $page->runCommit();

    while (! $page->done) {
        $page->tick();
    }

    return $page;
}

// ── fallback (blank in later column does not overwrite earlier) ───────────────

it('two columns mapped to the same field fall back when the later is blank', function () {
    $path = dupCsv([
        ['First name', 'Email', 'FirstName', 'Email address'],
        ['Alice',       'a@example.com', '', ''],   // old-style row
        ['',            '',               'Bob', 'b@example.com'], // new-style row
    ]);

    $log = ImportLog::create([
        'model_type'         => 'contact',
        'filename'           => basename($path),
        'storage_path'       => $path,
        'column_map'         => [
            'First name'    => 'first_name',
            'Email'         => 'email',
            'FirstName'     => 'first_name',
            'Email address' => 'email',
        ],
        'row_count'          => 2,
        'duplicate_strategy' => 'skip',
        'match_key'          => 'email',
        'status'             => 'pending',
    ]);

    $page = runDupImport($log, $this->admin);

    expect($page->imported)->toBe(2);

    $alice = Contact::withoutGlobalScopes()->where('email', 'a@example.com')->first();
    $bob   = Contact::withoutGlobalScopes()->where('email', 'b@example.com')->first();

    expect($alice)->not->toBeNull()
        ->and($alice->first_name)->toBe('Alice')
        ->and($bob)->not->toBeNull()
        ->and($bob->first_name)->toBe('Bob');
});

// ── column_preferences: preferred wins when both are populated ────────────────

it('column_preferences makes the preferred column win even when both are populated', function () {
    $path = dupCsv([
        ['First name', 'FirstName', 'email'],
        ['Preferred', 'Fallback', 'p@example.com'],
    ]);

    $log = ImportLog::create([
        'model_type'         => 'contact',
        'filename'           => basename($path),
        'storage_path'       => $path,
        'column_map'         => [
            'First name' => 'first_name',
            'FirstName'  => 'first_name',
            'email'      => 'email',
        ],
        'column_preferences' => ['first_name' => 'First name'],
        'row_count'          => 1,
        'duplicate_strategy' => 'skip',
        'match_key'          => 'email',
        'status'             => 'pending',
    ]);

    $page = runDupImport($log, $this->admin);

    expect(Contact::withoutGlobalScopes()->where('email', 'p@example.com')->first()->first_name)->toBe('Preferred');
});

// ── column_preferences: preferred blank → falls back to sibling columns ───────

it('column_preferences falls back to sibling columns when the preferred column is blank', function () {
    $path = dupCsv([
        ['First name', 'FirstName', 'email'],
        ['',           'Fallback',  'fb@example.com'],
    ]);

    $log = ImportLog::create([
        'model_type'         => 'contact',
        'filename'           => basename($path),
        'storage_path'       => $path,
        'column_map'         => [
            'First name' => 'first_name',
            'FirstName'  => 'first_name',
            'email'      => 'email',
        ],
        'column_preferences' => ['first_name' => 'First name'],
        'row_count'          => 1,
        'duplicate_strategy' => 'skip',
        'match_key'          => 'email',
        'status'             => 'pending',
    ]);

    runDupImport($log, $this->admin);

    expect(Contact::withoutGlobalScopes()->where('email', 'fb@example.com')->first()->first_name)->toBe('Fallback');
});
