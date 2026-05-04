<?php

use App\Filament\Pages\ImportEventsProgressPage;
use App\Models\Contact;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\ImportIdMap;
use App\Models\ImportLog;
use App\Models\ImportSession;
use App\Models\ImportSource;
use App\Models\Note;
use App\Models\Organization;
use App\Models\Tag;
use App\Models\Transaction;
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

function eventsCsv(array $rows): string
{
    $handle = fopen('php://temp', 'r+');
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    rewind($handle);
    $content = stream_get_contents($handle);
    fclose($handle);

    $path = 'imports/' . uniqid('ev-', true) . '.csv';
    Storage::disk('local')->put($path, $content);

    return $path;
}

function eventsLog(
    string $path,
    array $columnMap,
    int $rowCount,
    ?string $sourceId = null,
    string $eventMatchKey = 'event:external_id',
    string $contactMatchKey = 'contact:email',
    array $customFieldMap = [],
): ImportLog {
    return ImportLog::create([
        'model_type'         => 'event',
        'filename'           => basename($path),
        'storage_path'       => $path,
        'column_map'         => $columnMap,
        'custom_field_map'   => $customFieldMap,
        'row_count'          => $rowCount,
        'duplicate_strategy' => 'skip',
        'match_key'          => $eventMatchKey,
        'contact_match_key'  => $contactMatchKey,
        'import_source_id'   => $sourceId,
        'status'             => 'pending',
    ]);
}

function eventsSession(User $admin, ?string $sourceId = null, string $label = 'Events run'): ImportSession
{
    return ImportSession::create([
        'session_label'    => $label,
        'import_source_id' => $sourceId,
        'model_type'       => 'event',
        'status'           => 'pending',
        'filename'         => 'events.csv',
        'row_count'        => 10,
        'imported_by'      => $admin->id,
    ]);
}

function mountEventsPage(ImportLog $log, ImportSession $session, string $sourceId): ImportEventsProgressPage
{
    $page = new ImportEventsProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId  = $sourceId;
    $page->mount();

    return $page;
}

// ── Events external ID matching ──────────────────────────────────────────────

it('reuses an existing Event when the external ID matches an ImportIdMap row', function () {
    $source   = ImportSource::create(['name' => 'Source A']);
    $existing = Event::factory()->create(['title' => 'Existing Event']);
    Contact::factory()->create(['email' => 'alice@example.com']);

    ImportIdMap::create([
        'import_source_id' => $source->id,
        'model_type'       => 'event',
        'source_id'        => 'EV-1',
        'model_uuid'       => $existing->id,
    ]);

    $path = eventsCsv([
        ['Event ID', 'Event title', 'Email'],
        ['EV-1', 'Ignored because matched', 'alice@example.com'],
    ]);

    $log     = eventsLog($path, [
        'Event ID'    => 'event:external_id',
        'Event title' => 'event:title',
        'Email'       => 'contact:email',
    ], 1, $source->id);
    $session = eventsSession($this->admin, $source->id);
    $page    = mountEventsPage($log, $session, $source->id);

    $page->runCommit();
    while (! $page->done) {
        $page->tick();
    }

    // Still just the one Event, and its title was not overwritten.
    expect(Event::count())->toBe(1)
        ->and(Event::first()->title)->toBe('Existing Event')
        ->and(EventRegistration::where('event_id', $existing->id)->count())->toBe(1);
});

it('creates a new Event when no external ID match exists, writing an ImportIdMap row', function () {
    $source = ImportSource::create(['name' => 'Source B']);
    Contact::factory()->create(['email' => 'bob@example.com']);

    $path = eventsCsv([
        ['Event ID', 'Event title', 'Start date', 'Email'],
        ['NEW-1', 'Brand New Event', '05/01/2026 10:00:00', 'bob@example.com'],
    ]);

    $log     = eventsLog($path, [
        'Event ID'    => 'event:external_id',
        'Event title' => 'event:title',
        'Start date'  => 'event:starts_at',
        'Email'       => 'contact:email',
    ], 1, $source->id);
    $session = eventsSession($this->admin, $source->id);
    $page    = mountEventsPage($log, $session, $source->id);

    $page->runCommit();
    while (! $page->done) {
        $page->tick();
    }

    expect(Event::count())->toBe(1)
        ->and(Event::first()->title)->toBe('Brand New Event')
        ->and(Event::first()->starts_at)->not->toBeNull()
        ->and(ImportIdMap::where('import_source_id', $source->id)
            ->where('model_type', 'event')
            ->where('source_id', 'NEW-1')
            ->exists())->toBeTrue();
});

// ── Event status case-normalization ──────────────────────────────────────────

it('normalises mixed-case event status values to the lowercase enum form', function () {
    $source = ImportSource::create(['name' => 'Source Status']);
    Contact::factory()->create(['email' => 'a@example.com']);
    Contact::factory()->create(['email' => 'b@example.com']);
    Contact::factory()->create(['email' => 'c@example.com']);
    Contact::factory()->create(['email' => 'd@example.com']);

    $path = eventsCsv([
        ['Event ID', 'Event Title', 'Start Date', 'Event Status', 'Email'],
        ['EV-DRAFT',  'Draft Event',     '05/01/2026 10:00:00', 'Draft',     'a@example.com'],
        ['EV-PUB',    'Published Event', '05/01/2026 10:00:00', 'PUBLISHED', 'b@example.com'],
        ['EV-CXL',    'Cancelled Event', '05/01/2026 10:00:00', 'Cancelled', 'c@example.com'],
        ['EV-LIVE',   'Live Event',      '05/01/2026 10:00:00', 'Live',      'd@example.com'],
    ]);

    $log = eventsLog($path, [
        'Event ID'     => 'event:external_id',
        'Event Title'  => 'event:title',
        'Start Date'   => 'event:starts_at',
        'Event Status' => 'event:status',
        'Email'        => 'contact:email',
    ], 4, $source->id);
    $session = eventsSession($this->admin, $source->id);
    $page    = mountEventsPage($log, $session, $source->id);

    $page->runCommit();
    while (! $page->done) {
        $page->tick();
    }

    expect(Event::where('title', 'Draft Event')->first()->status)->toBe('draft')
        ->and(Event::where('title', 'Published Event')->first()->status)->toBe('published')
        ->and(Event::where('title', 'Cancelled Event')->first()->status)->toBe('cancelled')
        ->and(Event::where('title', 'Live Event')->first()->status)->toBe('published');
});

it('defaults event status to draft when the source value is unrecognised', function () {
    $source = ImportSource::create(['name' => 'Source Status Unrecognised']);
    Contact::factory()->create(['email' => 'a@example.com']);

    $path = eventsCsv([
        ['Event ID', 'Event Title', 'Start Date', 'Event Status', 'Email'],
        ['EV-WTF',  'Mystery Event', '05/01/2026 10:00:00', 'WhoKnows',     'a@example.com'],
    ]);

    $log = eventsLog($path, [
        'Event ID'     => 'event:external_id',
        'Event Title'  => 'event:title',
        'Start Date'   => 'event:starts_at',
        'Event Status' => 'event:status',
        'Email'        => 'contact:email',
    ], 1, $source->id);
    $session = eventsSession($this->admin, $source->id);
    $page    = mountEventsPage($log, $session, $source->id);

    $page->runCommit();
    while (! $page->done) {
        $page->tick();
    }

    expect(Event::where('title', 'Mystery Event')->first()->status)->toBe('draft');
});

// ── Unresolved contact ───────────────────────────────────────────────────────

it('errors out when the contact match key fails to resolve a contact', function () {
    $source = ImportSource::create(['name' => 'Source C']);

    $path = eventsCsv([
        ['Event ID', 'Event title', 'Start date', 'Email'],
        ['EV-X', 'Some Event', '05/01/2026 10:00:00', 'ghost@example.com'],
    ]);

    $log     = eventsLog($path, [
        'Event ID'    => 'event:external_id',
        'Event title' => 'event:title',
        'Start date'  => 'event:starts_at',
        'Email'       => 'contact:email',
    ], 1, $source->id);
    $session = eventsSession($this->admin, $source->id);
    $page    = mountEventsPage($log, $session, $source->id);

    expect($page->dryRunReport['skipped'])->toBe(1)
        ->and($page->dryRunReport['skipReasons']['contact_not_found'])->toBe(1)
        ->and($page->skipRowNumbers)->toBe([2]);

    $page->runCommit();
    while (! $page->done) {
        $page->tick();
    }

    expect(EventRegistration::count())->toBe(0);
});

// ── One registration per committed row ───────────────────────────────────────

it('creates exactly one EventRegistration per committed row, linked to the right contact and event', function () {
    $source = ImportSource::create(['name' => 'Source D']);
    $alice  = Contact::factory()->create(['email' => 'alice@example.com']);
    $bob    = Contact::factory()->create(['email' => 'bob@example.com']);

    $path = eventsCsv([
        ['Event ID', 'Event title', 'Start date', 'Email'],
        ['EV-D1', 'Workshop Alpha', '05/02/2026 13:00:00', 'alice@example.com'],
        ['EV-D1', 'Workshop Alpha', '05/02/2026 13:00:00', 'bob@example.com'],
    ]);

    $log     = eventsLog($path, [
        'Event ID'    => 'event:external_id',
        'Event title' => 'event:title',
        'Start date'  => 'event:starts_at',
        'Email'       => 'contact:email',
    ], 2, $source->id);
    $session = eventsSession($this->admin, $source->id);
    $page    = mountEventsPage($log, $session, $source->id);

    $page->runCommit();
    while (! $page->done) {
        $page->tick();
    }

    expect(Event::count())->toBe(1);

    $event = Event::first();

    expect($event->registrations()->count())->toBe(2)
        ->and($event->registrations()->where('contact_id', $alice->id)->count())->toBe(1)
        ->and($event->registrations()->where('contact_id', $bob->id)->count())->toBe(1);
});

// ── Transaction dedupe across two runs ───────────────────────────────────────

it('upserts Transactions on (import_source_id, external_id) across repeated runs', function () {
    $source = ImportSource::create(['name' => 'Source E']);
    Contact::factory()->create(['email' => 'alice@example.com']);

    $columnMap = [
        'Event ID'       => 'event:external_id',
        'Event title'    => 'event:title',
        'Start date'     => 'event:starts_at',
        'Email'          => 'contact:email',
        'Invoice #'      => 'transaction:external_id',
        'Amount'         => 'transaction:amount',
        'Payment state'  => 'transaction:payment_state',
        'Payment method' => 'transaction:payment_method',
    ];

    $path1 = eventsCsv([
        ['Event ID', 'Event title', 'Start date', 'Email', 'Invoice #', 'Amount', 'Payment state', 'Payment method'],
        ['EV-E1',    'Pay Event',   '05/01/2026 10:00:00', 'alice@example.com', 'INV-1', '50.00', 'Paid', ''],
    ]);

    $log     = eventsLog($path1, $columnMap, 1, $source->id);
    $session = eventsSession($this->admin, $source->id, 'Run 1');
    $page    = mountEventsPage($log, $session, $source->id);

    $page->runCommit();
    while (! $page->done) {
        $page->tick();
    }

    expect(Transaction::count())->toBe(1)
        ->and(Transaction::first()->external_id)->toBe('INV-1')
        ->and((float) Transaction::first()->amount)->toBe(50.0)
        ->and(Transaction::first()->payment_method)->toBeNull();

    // Round 2: same invoice, richer payment_method + registered at a new Event.
    $path2 = eventsCsv([
        ['Event ID', 'Event title', 'Start date', 'Email', 'Invoice #', 'Amount', 'Payment state', 'Payment method'],
        ['EV-E2',    'Another Event', '05/02/2026 10:00:00', 'alice@example.com', 'INV-1', '75.00', 'Paid', 'Check'],
    ]);

    $log2     = eventsLog($path2, $columnMap, 1, $source->id);
    $session2 = eventsSession($this->admin, $source->id, 'Run 2');
    $page2    = mountEventsPage($log2, $session2, $source->id);

    $page2->runCommit();
    while (! $page2->done) {
        $page2->tick();
    }

    expect(Transaction::count())->toBe(1)
        ->and(Transaction::first()->external_id)->toBe('INV-1')
        ->and((float) Transaction::first()->amount)->toBe(50.0) // not overwritten (fill-blanks-only)
        ->and(Transaction::first()->payment_method)->toBe('Check'); // filled in because was blank
});

// ── transaction_id link on registration ──────────────────────────────────────

it('links event_registrations.transaction_id when financial fields are mapped; leaves it null otherwise', function () {
    $source = ImportSource::create(['name' => 'Source F']);
    Contact::factory()->create(['email' => 'alice@example.com']);

    // With transaction mapped.
    $pathWith = eventsCsv([
        ['Event ID', 'Event title', 'Start date', 'Email', 'Invoice #', 'Amount'],
        ['EV-F1',    'With Money',  '05/01/2026 10:00:00', 'alice@example.com', 'INV-F1', '20.00'],
    ]);

    $logWith = eventsLog($pathWith, [
        'Event ID'    => 'event:external_id',
        'Event title' => 'event:title',
        'Start date'  => 'event:starts_at',
        'Email'       => 'contact:email',
        'Invoice #'   => 'transaction:external_id',
        'Amount'      => 'transaction:amount',
    ], 1, $source->id);
    $sessWith = eventsSession($this->admin, $source->id, 'With');
    $pageWith = mountEventsPage($logWith, $sessWith, $source->id);

    $pageWith->runCommit();
    while (! $pageWith->done) {
        $pageWith->tick();
    }

    $regWith = EventRegistration::first();
    expect($regWith->transaction_id)->not->toBeNull()
        ->and($regWith->transaction->external_id)->toBe('INV-F1');

    // Without transaction mapped.
    $pathNo = eventsCsv([
        ['Event ID', 'Event title', 'Start date', 'Email'],
        ['EV-F2',    'Free Event',  '05/02/2026 10:00:00', 'alice@example.com'],
    ]);

    $logNo = eventsLog($pathNo, [
        'Event ID'    => 'event:external_id',
        'Event title' => 'event:title',
        'Start date'  => 'event:starts_at',
        'Email'       => 'contact:email',
    ], 1, $source->id);
    $sessNo = eventsSession($this->admin, $source->id, 'Without');
    $pageNo = mountEventsPage($logNo, $sessNo, $source->id);

    $pageNo->runCommit();
    while (! $pageNo->done) {
        $pageNo->tick();
    }

    $regNo = EventRegistration::where('id', '!=', $regWith->id)->first();
    expect($regNo->transaction_id)->toBeNull();
});

// ── Dry-run rollback ─────────────────────────────────────────────────────────

it('dry-run rolls back every write: events, registrations, transactions, id_maps, notes', function () {
    $source = ImportSource::create(['name' => 'Source G']);
    Contact::factory()->create(['email' => 'alice@example.com']);

    $path = eventsCsv([
        ['Event ID', 'Event title', 'Start date', 'Email', 'Invoice #', 'Amount'],
        ['EV-G1', 'Dry Event', '05/03/2026 10:00:00', 'alice@example.com', 'INV-G1', '10.00'],
    ]);

    $log     = eventsLog($path, [
        'Event ID'    => 'event:external_id',
        'Event title' => 'event:title',
        'Start date'  => 'event:starts_at',
        'Email'       => 'contact:email',
        'Invoice #'   => 'transaction:external_id',
        'Amount'      => 'transaction:amount',
    ], 1, $source->id);
    $session = eventsSession($this->admin, $source->id, 'Dry');
    $page    = mountEventsPage($log, $session, $source->id);

    // mount() completes the dry-run internally. Nothing should be persisted yet.
    expect($page->dryRunReport['imported'])->toBe(1)
        ->and(Event::count())->toBe(0)
        ->and(EventRegistration::count())->toBe(0)
        ->and(Transaction::count())->toBe(0)
        ->and(ImportIdMap::where('model_type', 'event')->count())->toBe(0)
        ->and(Note::count())->toBe(0);
});

// ── Source preset save/restore scoped ────────────────────────────────────────

// ── Case-insensitive email lookup ────────────────────────────────────────────

it('matches contacts by email case-insensitively', function () {
    $source = ImportSource::create(['name' => 'Case Source']);
    Contact::factory()->create(['email' => 'Mixed.Case@Example.COM']);

    $path = eventsCsv([
        ['Event ID', 'Event title', 'Start date', 'Email'],
        ['EV-C1', 'Case Event', '05/01/2026 10:00:00', 'mixed.case@example.com'],
    ]);

    $log     = eventsLog($path, [
        'Event ID'    => 'event:external_id',
        'Event title' => 'event:title',
        'Start date'  => 'event:starts_at',
        'Email'       => 'contact:email',
    ], 1, $source->id);
    $session = eventsSession($this->admin, $source->id);
    $page    = mountEventsPage($log, $session, $source->id);

    expect($page->dryRunReport['errorCount'])->toBe(0);

    $page->runCommit();
    while (! $page->done) {
        $page->tick();
    }

    expect(EventRegistration::count())->toBe(1);
});

// ── Event tags ───────────────────────────────────────────────────────────────

it('applies event tags (delimited) to the created Event via the __tag_event__ sentinel', function () {
    $source = ImportSource::create(['name' => 'Tag Source']);
    Contact::factory()->create(['email' => 'alice@example.com']);

    $path = eventsCsv([
        ['Event ID', 'Event title', 'Start date', 'Email', 'Event tags'],
        ['EV-T1',    'Tagged Event', '05/01/2026 10:00:00', 'alice@example.com', 'community, greenway, invasive plants'],
    ]);

    $log = eventsLog($path, [
        'Event ID'    => 'event:external_id',
        'Event title' => 'event:title',
        'Start date'  => 'event:starts_at',
        'Email'       => 'contact:email',
        'Event tags'  => '__tag_event__',
    ], 1, $source->id);

    $log->update([
        'relational_map' => [
            'Event tags' => ['type' => 'event_tag', 'delimiter' => ','],
        ],
    ]);

    $session = eventsSession($this->admin, $source->id);
    $page    = mountEventsPage($log, $session, $source->id);

    $page->runCommit();
    while (! $page->done) {
        $page->tick();
    }

    $event = Event::first();

    expect($event)->not->toBeNull()
        ->and($event->tags()->where('type', 'event')->pluck('name')->sort()->values()->all())
        ->toBe(['community', 'greenway', 'invasive plants'])
        ->and(Tag::where('type', 'event')->count())->toBe(3);
});

// ── Contact organization fill-blanks-only ────────────────────────────────────

it('fills Contact.organization_id when blank using __org_contact__, never overwrites', function () {
    $source = ImportSource::create(['name' => 'Org Source']);

    // Contact A has no org; will get linked.
    $a = Contact::factory()->create(['email' => 'a@example.com', 'organization_id' => null]);

    // Contact B already has an org; must not be overwritten.
    $existingOrg = Organization::create(['name' => 'Existing Org']);
    $b = Contact::factory()->create(['email' => 'b@example.com', 'organization_id' => $existingOrg->id]);

    $path = eventsCsv([
        ['Event ID', 'Event title', 'Start date', 'Email', 'Organization'],
        ['EV-O1',    'Org Event',  '05/01/2026 10:00:00', 'a@example.com', 'Acme Corp'],
        ['EV-O1',    'Org Event',  '05/01/2026 10:00:00', 'b@example.com', 'Other Corp'],
    ]);

    $log = eventsLog($path, [
        'Event ID'     => 'event:external_id',
        'Event title'  => 'event:title',
        'Start date'   => 'event:starts_at',
        'Email'        => 'contact:email',
        'Organization' => '__org_contact__',
    ], 2, $source->id);

    $log->update([
        'relational_map' => [
            'Organization' => ['type' => 'contact_organization', 'strategy' => 'auto_create'],
        ],
    ]);

    $session = eventsSession($this->admin, $source->id);
    $page    = mountEventsPage($log, $session, $source->id);

    $page->runCommit();
    while (! $page->done) {
        $page->tick();
    }

    $a->refresh();
    $b->refresh();

    $acme = Organization::whereRaw('LOWER(name) = ?', ['acme corp'])->first();

    expect($acme)->not->toBeNull()
        ->and($a->organization_id)->toBe($acme->id)
        ->and($b->organization_id)->toBe($existingOrg->id) // NOT overwritten to "Other Corp"
        // "Other Corp" is NOT created — we skip Organization creation entirely
        // for contacts that already have a link, to avoid orphan rows.
        ->and(Organization::whereRaw('LOWER(name) = ?', ['other corp'])->exists())->toBeFalse();
});

it('saveMapping writes to events_* columns without touching contact-scoped columns', function () {
    $source = ImportSource::create([
        'name'                      => 'Multi Source',
        // Simulate a previously-saved contacts preset — we must not clobber it.
        'contacts_field_map'        => ['email' => 'email'],
        'contacts_custom_field_map' => [],
        'contacts_match_key'        => 'email',
        'contacts_match_key_column' => 'email',
    ]);
    Contact::factory()->create(['email' => 'alice@example.com']);

    $path = eventsCsv([
        ['Event ID', 'Event title', 'Start date', 'Email'],
        ['EV-SR', 'Saveable Event', '05/04/2026 10:00:00', 'alice@example.com'],
    ]);

    $log     = eventsLog($path, [
        'Event ID'    => 'event:external_id',
        'Event title' => 'event:title',
        'Start date'  => 'event:starts_at',
        'Email'       => 'contact:email',
    ], 1, $source->id);
    $session = eventsSession($this->admin, $source->id, 'Save');
    $page    = mountEventsPage($log, $session, $source->id);

    $page->runCommit();
    while (! $page->done) {
        $page->tick();
    }

    $page->saveMapping();

    $source->refresh();

    expect($source->events_field_map)->toMatchArray([
        'event id'    => 'event:external_id',
        'event title' => 'event:title',
        'start date'  => 'event:starts_at',
        'email'       => 'contact:email',
    ])
        ->and($source->events_match_key)->toBe('event:external_id')
        ->and($source->events_contact_match_key)->toBe('contact:email')
        // Contact-scoped preset untouched.
        ->and($source->contacts_field_map)->toBe(['email' => 'email'])
        ->and($source->contacts_match_key)->toBe('email');
});
