<?php

use App\Filament\Pages\ImportProgressPage;
use App\Models\Contact;
use App\Models\CustomFieldDef;
use App\Models\ImportIdMap;
use App\Models\ImportLog;
use App\Models\ImportSession;
use App\Models\ImportSource;
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

function writeImportCsv(array $rows): string
{
    $handle = fopen('php://temp', 'r+');
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    rewind($handle);
    $content = stream_get_contents($handle);
    fclose($handle);

    $path = 'imports/' . uniqid('m-', true) . '.csv';
    Storage::disk('local')->put($path, $content);

    return $path;
}

function makeSession(User $admin, ?string $sourceId = null): ImportSession
{
    return ImportSession::create([
        'import_source_id' => $sourceId,
        'model_type'       => 'contact',
        'status'           => 'pending',
        'filename'         => 'match-key.csv',
        'row_count'        => 100,
        'imported_by'      => $admin->id,
    ]);
}

function runTick(ImportLog $log, ImportSession $session, string $sourceId = ''): ImportProgressPage
{
    $page = new ImportProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId  = $sourceId;
    $page->mount();

    // mount() auto-runs the dry-run. Now commit, then tick until done.
    $page->runCommit();

    while (! $page->done) {
        $page->tick();
    }

    return $page;
}

// ── match by email (default) ──────────────────────────────────────────────────

it('matches existing contact by email when match_key is email', function () {
    $existing = Contact::factory()->create(['email' => 'hit@example.com', 'first_name' => 'Old']);

    $path = writeImportCsv([
        ['first_name', 'email'],
        ['NewName', 'hit@example.com'],
    ]);

    $log = ImportLog::create([
        'model_type'         => 'contact',
        'filename'           => 'email.csv',
        'storage_path'       => $path,
        'column_map'         => ['first_name' => 'first_name', 'email' => 'email'],
        'row_count'          => 1,
        'duplicate_strategy' => 'update',
        'match_key'          => 'email',
        'status'             => 'pending',
    ]);

    runTick($log, makeSession($this->admin));

    // Existing contact untouched; staged update created.
    expect($existing->fresh()->first_name)->toBe('Old')
        ->and(\App\Models\ImportStagedUpdate::where('contact_id', $existing->id)->count())->toBe(1);
});

// ── match by external_id via ImportIdMap ──────────────────────────────────────

it('matches existing contact by external_id through import_id_maps', function () {
    $source  = ImportSource::create(['name' => 'Old CRM']);
    $contact = Contact::factory()->create(['email' => 'ext@example.com']);

    ImportIdMap::create([
        'import_source_id' => $source->id,
        'model_type'       => 'contact',
        'source_id'        => 'ext-42',
        'model_uuid'       => $contact->id,
    ]);

    $path = writeImportCsv([
        ['ext_id', 'email'],
        ['ext-42', 'ext@example.com'],
    ]);

    $log = ImportLog::create([
        'model_type'         => 'contact',
        'filename'           => 'ext.csv',
        'storage_path'       => $path,
        'column_map'         => ['ext_id' => 'external_id', 'email' => 'email'],
        'row_count'          => 1,
        'duplicate_strategy' => 'update',
        'match_key'          => 'external_id',
        'status'             => 'pending',
    ]);

    runTick($log, makeSession($this->admin, $source->id), $source->id);

    expect(\App\Models\ImportStagedUpdate::where('contact_id', $contact->id)->count())->toBe(1)
        ->and(Contact::count())->toBe(1);
});

// ── match by custom field handle ──────────────────────────────────────────────

it('matches existing contact by custom field handle', function () {
    CustomFieldDef::create([
        'model_type' => 'contact',
        'handle'     => 'member_id',
        'label'      => 'Member ID',
        'field_type' => 'text',
    ]);

    $existing = Contact::factory()->create([
        'email'         => 'm1@example.com',
        'custom_fields' => ['member_id' => 'MEM-777'],
    ]);

    $path = writeImportCsv([
        ['first_name', 'email', 'member_id'],
        ['Updated', 'm1@example.com', 'MEM-777'],
    ]);

    $log = ImportLog::create([
        'model_type'         => 'contact',
        'filename'           => 'cf.csv',
        'storage_path'       => $path,
        'column_map'         => ['first_name' => 'first_name', 'email' => 'email', 'member_id' => null],
        'custom_field_map'   => ['member_id' => ['handle' => 'member_id', 'label' => 'Member ID', 'field_type' => 'text']],
        'row_count'          => 1,
        'duplicate_strategy' => 'update',
        'match_key'          => 'member_id',
        'status'             => 'pending',
    ]);

    runTick($log, makeSession($this->admin));

    expect(\App\Models\ImportStagedUpdate::where('contact_id', $existing->id)->count())->toBe(1)
        ->and(Contact::count())->toBe(1);
});

// ── ambiguous match errors out ────────────────────────────────────────────────

it('errors out when two existing contacts share the same match key value', function () {
    Contact::factory()->create(['email' => 'dup@example.com', 'first_name' => 'A']);
    Contact::factory()->create(['email' => 'dup@example.com', 'first_name' => 'B']);

    $path = writeImportCsv([
        ['first_name', 'email'],
        ['Imported', 'dup@example.com'],
    ]);

    $log = ImportLog::create([
        'model_type'         => 'contact',
        'filename'           => 'amb.csv',
        'storage_path'       => $path,
        'column_map'         => ['first_name' => 'first_name', 'email' => 'email'],
        'row_count'          => 1,
        'duplicate_strategy' => 'update',
        'match_key'          => 'email',
        'status'             => 'pending',
    ]);

    // Dry-run only — the ambiguous row should be flagged, then skipped on commit.
    $page = new ImportProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = makeSession($this->admin)->id;
    $page->mount();

    expect($page->dryRunReport['errorCount'])->toBe(1)
        ->and($page->dryRunReport['errors'][0]['message'])->toContain('Ambiguous match on email = dup@example.com')
        ->and($page->skipRowNumbers)->toBe([2]);
});

// ── new contact created when no match found ───────────────────────────────────

it('creates a new contact when match_key value does not match any existing row', function () {
    Contact::factory()->create(['email' => 'other@example.com']);

    $path = writeImportCsv([
        ['first_name', 'email'],
        ['Fresh', 'fresh@example.com'],
    ]);

    $log = ImportLog::create([
        'model_type'         => 'contact',
        'filename'           => 'new.csv',
        'storage_path'       => $path,
        'column_map'         => ['first_name' => 'first_name', 'email' => 'email'],
        'row_count'          => 1,
        'duplicate_strategy' => 'skip',
        'match_key'          => 'email',
        'status'             => 'pending',
    ]);

    runTick($log, makeSession($this->admin));

    expect(Contact::withoutGlobalScopes()->where('email', 'fresh@example.com')->exists())->toBeTrue();
});
