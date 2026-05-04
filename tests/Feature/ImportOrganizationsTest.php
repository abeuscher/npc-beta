<?php

use App\Filament\Pages\ImportOrganizationsProgressPage;
use App\Models\Contact;
use App\Models\ImportLog;
use App\Models\ImportSession;
use App\Models\ImportSource;
use App\Models\Note;
use App\Models\Organization;
use App\Models\Tag;
use App\Models\User;
use App\Services\Import\ImportSessionActions;
use App\WidgetPrimitive\Source;
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

function organizationsCsv(array $rows): string
{
    $handle = fopen('php://temp', 'r+');
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    rewind($handle);
    $content = stream_get_contents($handle);
    fclose($handle);

    $path = 'imports/' . uniqid('orgs-', true) . '.csv';
    Storage::disk('local')->put($path, $content);

    return $path;
}

function organizationsSession(User $admin, ?string $sourceId = null): ImportSession
{
    return ImportSession::create([
        'session_label'    => 'Organizations run',
        'import_source_id' => $sourceId,
        'model_type'       => 'organization',
        'status'           => 'pending',
        'filename'         => 'organizations.csv',
        'row_count'        => 10,
        'imported_by'      => $admin->id,
    ]);
}

function organizationsLog(
    string $path,
    array $columnMap,
    int $rowCount,
    string $matchKey = 'organization:name',
    string $duplicateStrategy = 'skip',
    ?string $sourceId = null,
    array $customFieldMap = [],
    array $relationalMap = [],
): ImportLog {
    return ImportLog::create([
        'model_type'         => 'organization',
        'filename'           => basename($path),
        'storage_path'       => $path,
        'column_map'         => $columnMap,
        'custom_field_map'   => $customFieldMap ?: null,
        'relational_map'     => $relationalMap ?: [],
        'row_count'          => $rowCount,
        'duplicate_strategy' => $duplicateStrategy,
        'match_key'          => $matchKey,
        'import_source_id'   => $sourceId,
        'status'             => 'pending',
    ]);
}

function runOrgImport(
    ImportLog $log,
    ImportSession $session,
    ?string $sourceId = null,
): ImportOrganizationsProgressPage {
    $page = new ImportOrganizationsProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId  = $sourceId ?? '';
    $page->mount();
    $page->runCommit();
    $page->tick();

    return $page;
}

// ── Round-trip ────────────────────────────────────────────────────────────────

it('creates an Organization from a simple CSV row with source = import', function () {
    $source = ImportSource::create(['name' => 'Wild Apricot']);

    $path = organizationsCsv([
        ['Name', 'Type', 'Website', 'Email'],
        ['ACME Corp', 'for_profit', 'acme.example', 'hello@acme.example'],
    ]);

    $session = organizationsSession($this->admin, $source->id);
    $log     = organizationsLog($path, [
        'Name'    => 'organization:name',
        'Type'    => 'organization:type',
        'Website' => 'organization:website',
        'Email'   => 'organization:email',
    ], 1, sourceId: $source->id);

    $page = runOrgImport($log, $session, $source->id);

    expect($page->dryRunReport['imported'])->toBe(1);
    expect(Organization::count())->toBe(1);

    $org = Organization::first();
    expect($org->name)->toBe('ACME Corp');
    expect($org->type)->toBe('for_profit');
    expect($org->website)->toBe('acme.example');
    expect($org->email)->toBe('hello@acme.example');
    expect($org->source)->toBe(Source::IMPORT);
    expect($org->import_source_id)->toBe($source->id);
    expect($org->import_session_id)->toBe($session->id);
});

it('persists a custom field via __custom_organization__', function () {
    $source = ImportSource::create(['name' => 'Wild Apricot']);

    $path = organizationsCsv([
        ['Name', 'Industry'],
        ['ACME Corp', 'Aerospace'],
    ]);

    $session = organizationsSession($this->admin, $source->id);
    $log     = organizationsLog($path, [
        'Name'     => 'organization:name',
        'Industry' => '__custom_organization__',
    ], 1, sourceId: $source->id, customFieldMap: [
        'Industry' => ['handle' => 'industry', 'label' => 'Industry', 'field_type' => 'text'],
    ]);

    runOrgImport($log, $session, $source->id);

    $org = Organization::first();
    expect($org->custom_fields)->toBe(['industry' => 'Aerospace']);
});

it('attaches an organization-typed Tag via __tag_organization__', function () {
    $source = ImportSource::create(['name' => 'Wild Apricot']);

    $path = organizationsCsv([
        ['Name', 'Tags'],
        ['ACME Corp', 'partner|sponsor'],
    ]);

    $session = organizationsSession($this->admin, $source->id);
    $log     = organizationsLog($path, [
        'Name' => 'organization:name',
        'Tags' => '__tag_organization__',
    ], 1, sourceId: $source->id, relationalMap: [
        'Tags' => ['type' => 'organization_tag', 'delimiter' => '|'],
    ]);

    runOrgImport($log, $session, $source->id);

    $org = Organization::first();
    expect($org->tags()->pluck('name')->all())->toEqualCanonicalizing(['partner', 'sponsor']);

    foreach (Tag::all() as $tag) {
        expect($tag->type)->toBe('organization');
    }
});

it('creates a Note polymorphically via __note_organization__', function () {
    $source = ImportSource::create(['name' => 'Wild Apricot']);

    $path = organizationsCsv([
        ['Name', 'Notes'],
        ['ACME Corp', 'Met at conference 2023'],
    ]);

    $session = organizationsSession($this->admin, $source->id);
    $log     = organizationsLog($path, [
        'Name'  => 'organization:name',
        'Notes' => '__note_organization__',
    ], 1, sourceId: $source->id, relationalMap: [
        'Notes' => ['type' => 'organization_note', 'split_mode' => 'none', 'split_regex' => ''],
    ]);

    runOrgImport($log, $session, $source->id);

    $org = Organization::first();
    // One creation note from the importer + one relational note from the CSV column.
    expect($org->notes()->count())->toBe(2);

    $relational = $org->notes()->where('body', 'Met at conference 2023')->first();
    expect($relational)->not->toBeNull();
    expect($relational->notable_type)->toBe(Organization::class);
    expect($relational->notable_id)->toBe($org->id);

    $creation = $org->notes()->where('body', 'like', 'Imported from%')->first();
    expect($creation)->not->toBeNull();
});

// ── Dedup × match key × strategy ─────────────────────────────────────────────

dataset('matchKeys', [
    ['organization:name',        'name',        'ACME Corp'],
    ['organization:email',       'email',       'hello@acme.example'],
]);

it('skips a duplicate match on {0} with skip strategy', function (string $matchKey, string $field, string $value) {
    Organization::create([$field => $value, 'name' => $value === 'ACME Corp' ? $value : 'ACME Corp']);
    expect(Organization::count())->toBe(1);

    $columns = $field === 'name' ? ['Name'] : ['Name', 'Email'];
    $row     = $field === 'name' ? [$value] : ['ACME Corp', $value];

    $path = organizationsCsv([$columns, $row]);

    $columnMap = $field === 'name'
        ? ['Name' => 'organization:name']
        : ['Name' => 'organization:name', 'Email' => 'organization:email'];

    $session = organizationsSession($this->admin);
    $log     = organizationsLog($path, $columnMap, 1, matchKey: $matchKey, duplicateStrategy: 'skip');

    $page = runOrgImport($log, $session);

    expect($page->dryRunReport['skipped'])->toBe(1);
    expect($page->dryRunReport['skipReasons']['duplicate_skipped'])->toBe(1);
    expect(Organization::count())->toBe(1);
})->with('matchKeys');

it('errors on a duplicate match on {0} with error strategy', function (string $matchKey, string $field, string $value) {
    Organization::create([$field => $value, 'name' => $value === 'ACME Corp' ? $value : 'ACME Corp']);

    $columns = $field === 'name' ? ['Name'] : ['Name', 'Email'];
    $row     = $field === 'name' ? [$value] : ['ACME Corp', $value];

    $path = organizationsCsv([$columns, $row]);

    $columnMap = $field === 'name'
        ? ['Name' => 'organization:name']
        : ['Name' => 'organization:name', 'Email' => 'organization:email'];

    $session = organizationsSession($this->admin);
    $log     = organizationsLog($path, $columnMap, 1, matchKey: $matchKey, duplicateStrategy: 'error');

    $page = runOrgImport($log, $session);

    expect($page->dryRunReport['errorCount'])->toBe(1);
    expect(Organization::count())->toBe(1);
})->with('matchKeys');

it('stages a fill-blanks-only update on {0} with update strategy', function (string $matchKey, string $field, string $value) {
    // Stub org: only name and the match field set; everything else blank.
    $existing = Organization::create([
        'name'    => 'ACME Corp',
        $field    => $value,
        'website' => null,
        'phone'   => null,
    ]);

    // Pre-existing non-blank value that update must not overwrite.
    $existing->update(['phone' => '555-EXISTING']);

    $columns = $field === 'name'
        ? ['Name', 'Website', 'Phone']
        : ['Name', 'Email', 'Website', 'Phone'];

    $row = $field === 'name'
        ? ['ACME Corp', 'acme.example', '555-NEW']
        : ['ACME Corp', $value, 'acme.example', '555-NEW'];

    $path = organizationsCsv([$columns, $row]);

    $columnMap = $field === 'name'
        ? ['Name' => 'organization:name', 'Website' => 'organization:website', 'Phone' => 'organization:phone']
        : ['Name' => 'organization:name', 'Email' => 'organization:email', 'Website' => 'organization:website', 'Phone' => 'organization:phone'];

    $session = organizationsSession($this->admin);
    $log     = organizationsLog($path, $columnMap, 1, matchKey: $matchKey, duplicateStrategy: 'update');

    $page = runOrgImport($log, $session);

    expect($page->dryRunReport['updated'])->toBe(1);

    // Approve the staged update so we can verify fill-blanks-only semantics.
    app(ImportSessionActions::class)->approve($session->fresh());

    $existing->refresh();
    expect($existing->website)->toBe('acme.example'); // was blank → filled
    expect($existing->phone)->toBe('555-EXISTING');   // was non-blank → preserved
})->with('matchKeys');

it('matches an existing organization by external_id scoped to the import source', function () {
    $source = ImportSource::create(['name' => 'Wild Apricot']);

    Organization::create([
        'name'             => 'ACME Corp',
        'external_id'      => 'EXT-42',
        'import_source_id' => $source->id,
        'source'           => Source::IMPORT,
    ]);

    $path = organizationsCsv([
        ['Name', 'External ID'],
        ['ACME Corp Updated', 'EXT-42'],
    ]);

    $session = organizationsSession($this->admin, $source->id);
    $log     = organizationsLog($path, [
        'Name'        => 'organization:name',
        'External ID' => 'organization:external_id',
    ], 1, matchKey: 'organization:external_id', duplicateStrategy: 'skip', sourceId: $source->id);

    $page = runOrgImport($log, $session, $source->id);

    expect($page->dryRunReport['skipped'])->toBe(1);
    expect($page->dryRunReport['skipReasons']['duplicate_skipped'])->toBe(1);
    expect(Organization::count())->toBe(1);
});

// ── Coexistence with __org_contact__ stubs ───────────────────────────────────

it('enriches an org stub auto-created by __org_contact__ via update strategy', function () {
    // Stub created by Contact importer (just name).
    $stub = Organization::create(['name' => 'ACME Corp']);

    expect($stub->website)->toBeNull();
    expect($stub->email)->toBeNull();

    $path = organizationsCsv([
        ['Name', 'Website', 'Email', 'Type'],
        ['acme corp', 'acme.example', 'hello@acme.example', 'for_profit'],
    ]);

    $session = organizationsSession($this->admin);
    $log     = organizationsLog($path, [
        'Name'    => 'organization:name',
        'Website' => 'organization:website',
        'Email'   => 'organization:email',
        'Type'    => 'organization:type',
    ], 1, matchKey: 'organization:name', duplicateStrategy: 'update');

    runOrgImport($log, $session);

    // Approve staged update to verify fill-blanks-only enrichment.
    app(ImportSessionActions::class)->approve($session->fresh());

    expect(Organization::count())->toBe(1, 'no duplicate org created');

    $stub->refresh();
    expect($stub->website)->toBe('acme.example');
    expect($stub->email)->toBe('hello@acme.example');
    expect($stub->type)->toBe('for_profit');
});

// ── Source-policy / EnforcesScrubInheritance ─────────────────────────────────

it('writes Organization::create() with default source = human', function () {
    $org = Organization::create(['name' => 'ACME Corp']);

    expect($org->fresh()->source)->toBe('human');
});

it('accepts source = scrub_data on creation', function () {
    $org = Organization::create(['name' => 'ACME Corp', 'source' => Source::SCRUB_DATA]);

    expect($org->source)->toBe(Source::SCRUB_DATA);
});

it('locks scrub_data orgs from transitioning out of scrub_data', function () {
    $org = Organization::create(['name' => 'ACME Corp', 'source' => Source::SCRUB_DATA]);

    expect(fn () => $org->update(['source' => Source::IMPORT]))
        ->toThrow(\App\WidgetPrimitive\Exceptions\ScrubSourceLocked::class);
});

it('declares an empty scrubInheritsFrom array (top of source-policy graph)', function () {
    expect(Organization::scrubInheritsFrom())->toBe([]);
});

// ── Import-creation note: every imported Organization gets a timeline note ──

it('writes an "Imported from {source}" creation note on every imported Organization', function () {
    $source = ImportSource::create(['name' => 'Wild Apricot']);

    $path = organizationsCsv([
        ['Name'],
        ['ACME Corp'],
    ]);

    $session = organizationsSession($this->admin, $source->id);
    $log     = organizationsLog($path, [
        'Name' => 'organization:name',
    ], 1, sourceId: $source->id);

    runOrgImport($log, $session, $source->id);

    $org = Organization::where('name', 'ACME Corp')->first();
    expect($org)->not->toBeNull();

    $note = $org->notes()->first();
    expect($note)->not->toBeNull('Org importer must write a creation note on the new Organization');
    expect($note->body)->toBe('Imported from Wild Apricot (session: Organizations run)');
    expect($note->author_id)->toBe($this->admin->id);
    expect($note->import_source_id)->toBe($source->id);
});

// ── ImportModelType + Organization arm of ImportSessionActions ────────────────

it('rolls back an organization session by deleting its rows + tags + notes', function () {
    $session = organizationsSession($this->admin);

    $org = Organization::create([
        'name'              => 'ACME Corp',
        'source'            => Source::IMPORT,
        'import_session_id' => $session->id,
    ]);
    $tag = Tag::create(['name' => 'partner', 'type' => 'organization']);
    $org->tags()->syncWithoutDetaching([$tag->id]);
    Note::create([
        'notable_type'      => Organization::class,
        'notable_id'        => $org->id,
        'body'              => 'note body',
        'occurred_at'       => now(),
        'import_session_id' => $session->id,
    ]);

    expect(Organization::count())->toBe(1);
    expect(Note::count())->toBe(1);

    app(ImportSessionActions::class)->rollback($session->fresh());

    expect(Organization::count())->toBe(0);
    expect(Note::count())->toBe(0);

    // Tag definition itself isn't rolled back — only the morph row in taggables.
    expect(Tag::where('id', $tag->id)->exists())->toBeTrue();
    expect(\Illuminate\Support\Facades\DB::table('taggables')->where('taggable_id', $org->id)->count())->toBe(0);
});
