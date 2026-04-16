<?php

use App\Filament\Pages\ImportProgressPage;
use App\Models\Contact;
use App\Models\CustomFieldDef;
use App\Models\ImportLog;
use App\Models\ImportSession;
use App\Models\ImportSource;
use App\Models\ImportStagedUpdate;
use App\Models\Note;
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

function dryCsv(array $rows): string
{
    $handle = fopen('php://temp', 'r+');
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    rewind($handle);
    $content = stream_get_contents($handle);
    fclose($handle);

    $path = 'imports/' . uniqid('d-', true) . '.csv';
    Storage::disk('local')->put($path, $content);

    return $path;
}

function dryLog(string $path, array $columnMap, int $rowCount, string $matchKey = 'email', array $customFieldMap = []): ImportLog
{
    return ImportLog::create([
        'model_type'         => 'contact',
        'filename'           => basename($path),
        'storage_path'       => $path,
        'column_map'         => $columnMap,
        'custom_field_map'   => $customFieldMap,
        'row_count'          => $rowCount,
        'duplicate_strategy' => 'update',
        'match_key'          => $matchKey,
        'status'             => 'pending',
    ]);
}

function mountDryRun(ImportLog $log, ImportSession $session, string $sourceId = ''): ImportProgressPage
{
    $page = new ImportProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId  = $sourceId;
    $page->mount();

    return $page;
}

function makeDrySession(User $admin, ?string $sourceId = null): ImportSession
{
    return ImportSession::create([
        'import_source_id' => $sourceId,
        'model_type'       => 'contact',
        'status'           => 'pending',
        'filename'         => 'dry.csv',
        'row_count'        => 100,
        'imported_by'      => $admin->id,
    ]);
}

// ── dry-run rolls back every write ────────────────────────────────────────────

it('dry-run rolls back contact creations, staged updates, notes, and id_maps', function () {
    $source  = ImportSource::create(['name' => 'Dry Source']);
    $existing = Contact::factory()->create(['email' => 'hit@example.com']);

    $path = dryCsv([
        ['first_name', 'email'],
        ['New1', 'fresh1@example.com'],
        ['New2', 'fresh2@example.com'],
        ['HitUpdate', 'hit@example.com'],
    ]);

    $log     = dryLog($path, ['first_name' => 'first_name', 'email' => 'email'], 3);
    $session = makeDrySession($this->admin, $source->id);
    $page    = mountDryRun($log, $session, $source->id);

    // Dry-run report reflects the planned actions.
    expect($page->dryRunReport['imported'])->toBe(2)
        ->and($page->dryRunReport['updated'])->toBe(1)
        ->and($page->dryRunReport['errorCount'])->toBe(0);

    // But nothing was actually persisted.
    expect(Contact::count())->toBe(1) // only the pre-existing one
        ->and(ImportStagedUpdate::count())->toBe(0)
        ->and(Note::where('notable_id', $existing->id)->count())->toBe(0);
});

// ── dry-run accurate counts ───────────────────────────────────────────────────

it('dry-run reports accurate create/update/skip/error counts', function () {
    Contact::factory()->create(['email' => 'existing@example.com']);
    Contact::factory()->create(['email' => 'dup@example.com']);
    Contact::factory()->create(['email' => 'dup@example.com']);

    $path = dryCsv([
        ['first_name', 'email'],
        ['New1', 'n1@example.com'],
        ['New2', 'n2@example.com'],
        ['Match', 'existing@example.com'],
        ['Ambig', 'dup@example.com'],
        ['', ''],
    ]);

    $log  = dryLog($path, ['first_name' => 'first_name', 'email' => 'email'], 5);
    $page = mountDryRun($log, makeDrySession($this->admin));

    expect($page->dryRunReport['imported'])->toBe(2)
        ->and($page->dryRunReport['updated'])->toBe(1)
        ->and($page->dryRunReport['skipped'])->toBe(1)
        ->and($page->dryRunReport['errorCount'])->toBe(1);
});

// ── commit skips errored rows, applies the rest ───────────────────────────────

it('commit omits rows that errored during dry-run and applies the rest', function () {
    Contact::factory()->create(['email' => 'dup@example.com']);
    Contact::factory()->create(['email' => 'dup@example.com']);

    $path = dryCsv([
        ['first_name', 'email'],
        ['Good', 'good@example.com'],
        ['Bad',  'dup@example.com'],
        ['Also', 'also@example.com'],
    ]);

    $log     = dryLog($path, ['first_name' => 'first_name', 'email' => 'email'], 3);
    $session = makeDrySession($this->admin);
    $page    = mountDryRun($log, $session);

    expect($page->skipRowNumbers)->toBe([3])
        ->and($page->dryRunReport['errorCount'])->toBe(1);

    $page->runCommit();
    while (! $page->done) {
        $page->tick();
    }

    expect($page->imported)->toBe(2)
        ->and($page->errorCount)->toBe(0)
        ->and(Contact::withoutGlobalScopes()->where('email', 'good@example.com')->exists())->toBeTrue()
        ->and(Contact::withoutGlobalScopes()->where('email', 'also@example.com')->exists())->toBeTrue()
        // The ambiguous email's existing contacts are untouched; no new contact was created.
        ->and(Contact::withoutGlobalScopes()->where('email', 'dup@example.com')->count())->toBe(2);
});

// ── save mapping persists to source ──────────────────────────────────────────

it('saveMapping persists field_map, custom_field_map, match_key, and match_key_column to the source', function () {
    $source = ImportSource::create(['name' => 'Save Me']);

    $path = dryCsv([
        ['First Name', 'Email Address', 'Member ID'],
        ['Alice', 'a@example.com', 'MEM-1'],
    ]);

    $log = dryLog(
        $path,
        ['First Name' => 'first_name', 'Email Address' => 'email', 'Member ID' => null],
        1,
        'email',
        ['Member ID' => ['handle' => 'member_id', 'label' => 'Member ID', 'field_type' => 'text']]
    );

    $session = makeDrySession($this->admin, $source->id);
    $page    = mountDryRun($log, $session, $source->id);

    $page->runCommit();
    while (! $page->done) {
        $page->tick();
    }

    $page->saveMapping();

    $source->refresh();

    expect($source->field_map)->toBe([
        'first name'    => 'first_name',
        'email address' => 'email',
    ])
        ->and($source->custom_field_map)->toHaveKey('member id')
        ->and($source->custom_field_map['member id']['handle'])->toBe('member_id')
        ->and($source->match_key)->toBe('email')
        ->and($source->match_key_column)->toBe('Email Address');
});

// ── save mapping cannot run before commit finishes ────────────────────────────

it('saveMapping is a no-op before commit completes', function () {
    $source = ImportSource::create(['name' => 'NoSave']);

    $path = dryCsv([
        ['email'],
        ['ok@example.com'],
    ]);

    $log     = dryLog($path, ['email' => 'email'], 1);
    $session = makeDrySession($this->admin, $source->id);
    $page    = mountDryRun($log, $session, $source->id);

    // phase is 'awaitingDecision' — save should be rejected.
    $page->saveMapping();
    $source->refresh();

    expect($source->field_map)->toBe([])
        ->and($page->mappingSaved)->toBeFalse();
});
