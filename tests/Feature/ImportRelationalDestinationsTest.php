<?php

use App\Filament\Pages\ImportProgressPage;
use App\Models\Contact;
use App\Models\ImportLog;
use App\Models\ImportSession;
use App\Models\Note;
use App\Models\Organization;
use App\Models\Tag;
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

function relCsv(array $rows): string
{
    $handle = fopen('php://temp', 'r+');
    foreach ($rows as $row) {
        fputcsv($handle, $row, ',', '"', '\\');
    }
    rewind($handle);
    $content = stream_get_contents($handle);
    fclose($handle);

    $path = 'imports/' . uniqid('rel-', true) . '.csv';
    Storage::disk('local')->put($path, $content);

    return $path;
}

function relLog(string $path, array $columnMap, int $rowCount, array $relationalMap = []): ImportLog
{
    return ImportLog::create([
        'model_type'         => 'contact',
        'filename'           => basename($path),
        'storage_path'       => $path,
        'column_map'         => $columnMap,
        'relational_map'     => $relationalMap,
        'row_count'          => $rowCount,
        'duplicate_strategy' => 'skip',
        'match_key'          => 'email',
        'status'             => 'pending',
    ]);
}

function runRelImport(ImportLog $log, User $admin): ImportProgressPage
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

// ── Organization: auto_create ─────────────────────────────────────────────────

it('auto_create strategy links rows to an existing Organization and creates missing ones', function () {
    Organization::create(['name' => 'ACME']);

    $path = relCsv([
        ['first_name', 'email',              'Organization'],
        ['Alice',      'a@example.com',      'ACME'],         // match existing
        ['Bob',        'b@example.com',      'Blue Sky'],     // create new
    ]);

    $log = relLog(
        $path,
        ['first_name' => 'first_name', 'email' => 'email', 'Organization' => '__org__'],
        2,
        ['Organization' => ['type' => 'organization', 'strategy' => 'auto_create']],
    );

    runRelImport($log, $this->admin);

    $acme = Organization::where('name', 'ACME')->first();
    $blue = Organization::where('name', 'Blue Sky')->first();

    expect(Organization::count())->toBe(2)
        ->and(Contact::withoutGlobalScopes()->where('email', 'a@example.com')->first()->organization_id)->toBe($acme->id)
        ->and(Contact::withoutGlobalScopes()->where('email', 'b@example.com')->first()->organization_id)->toBe($blue->id);
});

// ── Organization: match_only leaves unmatched rows unlinked ───────────────────

it('match_only strategy does not create new Organizations and leaves unmatched rows unlinked', function () {
    Organization::create(['name' => 'ACME']);

    $path = relCsv([
        ['first_name', 'email',         'Organization'],
        ['Alice',      'a@example.com', 'ACME'],
        ['Bob',        'b@example.com', 'Unknown Org'],
    ]);

    $log = relLog(
        $path,
        ['first_name' => 'first_name', 'email' => 'email', 'Organization' => '__org__'],
        2,
        ['Organization' => ['type' => 'organization', 'strategy' => 'match_only']],
    );

    runRelImport($log, $this->admin);

    $acme = Organization::where('name', 'ACME')->first();

    expect(Organization::count())->toBe(1) // Unknown Org NOT created
        ->and(Contact::withoutGlobalScopes()->where('email', 'a@example.com')->first()->organization_id)->toBe($acme->id)
        ->and(Contact::withoutGlobalScopes()->where('email', 'b@example.com')->first()->organization_id)->toBeNull();
});

// ── Organization: dry-run preview surfaces create/match/unmatched buckets ─────

it('dry-run reports organizations that would be created, matched, or left unmatched', function () {
    Organization::create(['name' => 'ACME']);

    $path = relCsv([
        ['first_name', 'email',         'Organization'],
        ['Alice',      'a@example.com', 'ACME'],       // match
        ['Bob',        'b@example.com', 'ACME'],       // match (2nd time)
        ['Carol',      'c@example.com', 'Blue Sky'],   // create
        ['Dave',       'd@example.com', 'Blue Sky'],   // create (same name → still just 1 created)
    ]);

    $log = relLog(
        $path,
        ['first_name' => 'first_name', 'email' => 'email', 'Organization' => '__org__'],
        4,
        ['Organization' => ['type' => 'organization', 'strategy' => 'auto_create']],
    );

    $session = ImportSession::create([
        'model_type'  => 'contact',
        'status'      => 'pending',
        'filename'    => basename($path),
        'row_count'   => 4,
        'imported_by' => $this->admin->id,
    ]);

    $page = new ImportProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = $session->id;
    $page->mount();

    $orgs = $page->dryRunReport['relationalPreview']['organizations'];

    expect($orgs['would_create'])->toBe(['Blue Sky' => 2])
        ->and($orgs['would_match'])->toBe(['ACME' => 2])
        ->and($orgs['unmatched'])->toBe([])
        // Rolled back — ACME is still the only one in DB.
        ->and(Organization::count())->toBe(1);
});

// ── Notes: one note per row when delimiter is blank ───────────────────────────

it('maps a column as __note__ and creates one note record per row by default', function () {
    $path = relCsv([
        ['first_name', 'email',         'Notes'],
        ['Alice',      'a@example.com', 'Prefers email contact'],
        ['Bob',        'b@example.com', ''],
    ]);

    $log = relLog(
        $path,
        ['first_name' => 'first_name', 'email' => 'email', 'Notes' => '__note__'],
        2,
        ['Notes' => ['type' => 'note', 'delimiter' => '', 'skip_blanks' => true]],
    );

    runRelImport($log, $this->admin);

    $alice = Contact::withoutGlobalScopes()->where('email', 'a@example.com')->first();
    $bob   = Contact::withoutGlobalScopes()->where('email', 'b@example.com')->first();

    // Alice: one imported-from note + one per-row note.
    // Bob: one imported-from note only (blank per-row note skipped).
    expect(Note::where('notable_id', $alice->id)->where('body', 'Prefers email contact')->exists())->toBeTrue()
        ->and(Note::where('notable_id', $alice->id)->count())->toBe(2)
        ->and(Note::where('notable_id', $bob->id)->count())->toBe(1);
});

// ── Notes: delimiter splits a cell into multiple notes ────────────────────────

it('splits a __note__ cell by delimiter and creates one note per non-blank piece', function () {
    $path = relCsv([
        ['first_name', 'email',         'Notes'],
        ['Alice',      'a@example.com', 'First|Second||Third'],
    ]);

    $log = relLog(
        $path,
        ['first_name' => 'first_name', 'email' => 'email', 'Notes' => '__note__'],
        1,
        ['Notes' => ['type' => 'note', 'delimiter' => '|', 'skip_blanks' => true]],
    );

    runRelImport($log, $this->admin);

    $alice = Contact::withoutGlobalScopes()->where('email', 'a@example.com')->first();

    // 3 per-row notes + 1 imported-from note = 4 total
    expect(Note::where('notable_id', $alice->id)->count())->toBe(4)
        ->and(Note::where('notable_id', $alice->id)->whereIn('body', ['First', 'Second', 'Third'])->count())->toBe(3);
});

// ── Notes: skip_blanks = false keeps empty splits ─────────────────────────────

it('skip_blanks = false retains blank pieces as empty-body notes', function () {
    $path = relCsv([
        ['first_name', 'email',         'Notes'],
        ['Alice',      'a@example.com', 'a||b'],
    ]);

    $log = relLog(
        $path,
        ['first_name' => 'first_name', 'email' => 'email', 'Notes' => '__note__'],
        1,
        ['Notes' => ['type' => 'note', 'delimiter' => '|', 'skip_blanks' => false]],
    );

    runRelImport($log, $this->admin);

    $alice = Contact::withoutGlobalScopes()->where('email', 'a@example.com')->first();

    // 3 per-row notes (a, '', b) + 1 imported-from
    expect(Note::where('notable_id', $alice->id)->count())->toBe(4);
});

// ── Tags: delimiter splits into N tags, missing ones auto-created ─────────────

it('maps a column as __tag__, splits by delimiter, and auto-creates missing tags', function () {
    Tag::create(['name' => 'board', 'type' => 'contact']);

    $path = relCsv([
        ['first_name', 'email',         'Roles'],
        ['Alice',      'a@example.com', 'board|donor|volunteer'],
    ]);

    $log = relLog(
        $path,
        ['first_name' => 'first_name', 'email' => 'email', 'Roles' => '__tag__'],
        1,
        ['Roles' => ['type' => 'tag', 'delimiter' => '|']],
    );

    runRelImport($log, $this->admin);

    $alice = Contact::withoutGlobalScopes()->where('email', 'a@example.com')->first();
    $names = $alice->tags()->pluck('name')->sort()->values()->all();

    expect($names)->toBe(['board', 'donor', 'volunteer'])
        ->and(Tag::where('type', 'contact')->count())->toBe(3); // board existed; donor+volunteer created
});

// ── Tags: dry-run preview distinguishes match vs create ───────────────────────

it('dry-run tag preview distinguishes tags that would be matched vs created', function () {
    Tag::create(['name' => 'board', 'type' => 'contact']);

    $path = relCsv([
        ['first_name', 'email',         'Roles'],
        ['Alice',      'a@example.com', 'board|donor'],
        ['Bob',        'b@example.com', 'volunteer'],
    ]);

    $log = relLog(
        $path,
        ['first_name' => 'first_name', 'email' => 'email', 'Roles' => '__tag__'],
        2,
        ['Roles' => ['type' => 'tag', 'delimiter' => '|']],
    );

    $session = ImportSession::create([
        'model_type'  => 'contact',
        'status'      => 'pending',
        'filename'    => basename($path),
        'row_count'   => 2,
        'imported_by' => $this->admin->id,
    ]);

    $page = new ImportProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = $session->id;
    $page->mount();

    $tags = $page->dryRunReport['relationalPreview']['tags'];

    expect($tags['would_match'])->toHaveKey('board')
        ->and($tags['would_create'])->toHaveKey('donor')
        ->and($tags['would_create'])->toHaveKey('volunteer')
        // Rolled back — only 'board' remains.
        ->and(Tag::where('type', 'contact')->count())->toBe(1);
});
