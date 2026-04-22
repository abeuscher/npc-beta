<?php

use App\Models\Contact;
use App\Models\Donation;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\ImportIdMap;
use App\Models\ImportSession;
use App\Models\ImportSource;
use App\Models\ImportStagedUpdate;
use App\Models\Membership;
use App\Models\MembershipTier;
use App\Models\Note;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Import\ImportSessionActions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->actingAs($this->admin);
    $this->actions = app(ImportSessionActions::class);

    $this->source = ImportSource::create([
        'name' => 'Test Source',
    ]);
});

function makeActionsSession(string $modelType, array $overrides = []): ImportSession
{
    return ImportSession::create(array_merge([
        'model_type'       => $modelType,
        'status'           => 'reviewing',
        'filename'         => "{$modelType}.csv",
        'row_count'        => 1,
        'imported_by'      => test()->admin->id,
        'import_source_id' => test()->source->id,
    ], $overrides));
}

// ── approve() — contacts ─────────────────────────────────────────────────────

it('approve marks a contact session approved and applies staged updates', function () {
    $session = makeActionsSession('contact');
    $contact = Contact::factory()->create(['city' => 'OldCity']);

    ImportStagedUpdate::create([
        'import_session_id' => $session->id,
        'subject_type'      => Contact::class,
        'subject_id'        => $contact->id,
        'attributes'        => ['city' => 'NewCity'],
    ]);

    $this->actions->approve($session);

    expect($session->fresh()->status)->toBe('approved')
        ->and($session->fresh()->approved_by)->toBe($this->admin->id)
        ->and($session->fresh()->approved_at)->not->toBeNull()
        ->and($contact->fresh()->city)->toBe('NewCity')
        ->and(ImportStagedUpdate::where('import_session_id', $session->id)->count())->toBe(0);
});

it('approve syncs tags and writes an audit note for contact subjects', function () {
    $session = makeActionsSession('contact');
    $contact = Contact::factory()->create();
    $tag     = Tag::factory()->create(['type' => 'contact']);

    ImportStagedUpdate::create([
        'import_session_id' => $session->id,
        'subject_type'      => Contact::class,
        'subject_id'        => $contact->id,
        'attributes'        => [],
        'tag_ids'           => [$tag->id],
    ]);

    $this->actions->approve($session);

    expect($contact->fresh()->tags()->where('tags.id', $tag->id)->exists())->toBeTrue()
        ->and(Note::where('notable_id', $contact->id)
            ->where('notable_type', Contact::class)
            ->where('body', 'like', '%approved by%')
            ->exists())->toBeTrue();
});

// ── approve() — notes ────────────────────────────────────────────────────────

it('approve marks a note session approved and deletes staged updates', function () {
    $session = makeActionsSession('note');
    $contact = Contact::factory()->create();

    $note = Note::create([
        'notable_type'      => Contact::class,
        'notable_id'        => $contact->id,
        'body'              => 'original',
        'import_session_id' => $session->id,
    ]);

    ImportStagedUpdate::create([
        'import_session_id' => $session->id,
        'subject_type'      => Note::class,
        'subject_id'        => $note->id,
        'attributes'        => ['body' => 'updated'],
    ]);

    $this->actions->approve($session);

    expect($session->fresh()->status)->toBe('approved')
        ->and($note->fresh()->body)->toBe('updated')
        ->and(ImportStagedUpdate::where('import_session_id', $session->id)->count())->toBe(0);
});

// ── rollback() — contacts ────────────────────────────────────────────────────

it('rollback deletes new contacts and discards staged updates with audit notes', function () {
    $session = makeActionsSession('contact');
    $imported = Contact::factory()->create(['import_session_id' => $session->id]);
    $existing = Contact::factory()->create(['city' => 'OriginalCity']);

    ImportStagedUpdate::create([
        'import_session_id' => $session->id,
        'subject_type'      => Contact::class,
        'subject_id'        => $existing->id,
        'attributes'        => ['city' => 'ShouldNotApply'],
    ]);

    $this->actions->rollback($session);

    expect(Contact::withoutGlobalScopes()->find($imported->id))->toBeNull()
        ->and($existing->fresh()->city)->toBe('OriginalCity')
        ->and(ImportSession::find($session->id))->toBeNull()
        ->and(Note::where('notable_id', $existing->id)
            ->where('body', 'like', '%discarded during rollback%')
            ->exists())->toBeTrue();
});

// ── rollback() — events ──────────────────────────────────────────────────────

it('rollback for events deletes registrations, transactions, events, and id_maps', function () {
    $session = makeActionsSession('event');
    $event = Event::factory()->create([
        'import_session_id' => $session->id,
    ]);
    $contact = Contact::factory()->create();
    EventRegistration::factory()->create([
        'event_id'          => $event->id,
        'contact_id'        => $contact->id,
        'import_session_id' => $session->id,
    ]);
    $tx = Transaction::factory()->create([
        'import_session_id' => $session->id,
        'import_source_id'  => $this->source->id,
    ]);
    ImportIdMap::create([
        'import_source_id' => $this->source->id,
        'model_type'       => 'event',
        'source_id'        => 'EV-1',
        'model_uuid'       => $event->id,
    ]);
    ImportIdMap::create([
        'import_source_id' => $this->source->id,
        'model_type'       => 'transaction',
        'source_id'        => 'TX-1',
        'model_uuid'       => $tx->id,
    ]);

    $this->actions->rollback($session);

    expect(Event::find($event->id))->toBeNull()
        ->and(Transaction::find($tx->id))->toBeNull()
        ->and(EventRegistration::where('import_session_id', $session->id)->count())->toBe(0)
        ->and(ImportIdMap::where('model_uuid', $event->id)->count())->toBe(0)
        ->and(ImportIdMap::where('model_uuid', $tx->id)->count())->toBe(0)
        ->and(ImportSession::find($session->id))->toBeNull();
});

// ── rollback() — donations ───────────────────────────────────────────────────

it('rollback for donations deletes donations, linked transactions, and auto-created contacts', function () {
    $session = makeActionsSession('donation');
    $autoContact = Contact::factory()->create(['import_session_id' => $session->id]);
    $donation = Donation::factory()->create([
        'contact_id'        => $autoContact->id,
        'import_session_id' => $session->id,
    ]);
    $tx = Transaction::factory()->create([
        'subject_type'      => Donation::class,
        'subject_id'        => $donation->id,
        'import_session_id' => $session->id,
    ]);

    $this->actions->rollback($session);

    expect(Donation::find($donation->id))->toBeNull()
        ->and(Transaction::find($tx->id))->toBeNull()
        ->and(Contact::withoutGlobalScopes()->find($autoContact->id))->toBeNull()
        ->and(ImportSession::find($session->id))->toBeNull();
});

// ── rollback() — memberships ─────────────────────────────────────────────────

it('rollback for memberships force-deletes memberships and auto-created contacts', function () {
    $session = makeActionsSession('membership');
    $tier = MembershipTier::factory()->create();
    $contact = Contact::factory()->create(['import_session_id' => $session->id]);
    $membership = Membership::factory()->create([
        'contact_id'        => $contact->id,
        'tier_id'           => $tier->id,
        'import_session_id' => $session->id,
    ]);

    $this->actions->rollback($session);

    expect(Membership::withTrashed()->find($membership->id))->toBeNull()
        ->and(Contact::withoutGlobalScopes()->find($contact->id))->toBeNull()
        ->and(ImportSession::find($session->id))->toBeNull();
});

// ── rollback() — invoice details ─────────────────────────────────────────────

it('rollback for invoice details deletes transactions and auto-created contacts', function () {
    $session = makeActionsSession('invoice_detail');
    $contact = Contact::factory()->create(['import_session_id' => $session->id]);
    $tx = Transaction::factory()->create([
        'import_session_id' => $session->id,
    ]);

    $this->actions->rollback($session);

    expect(Transaction::find($tx->id))->toBeNull()
        ->and(Contact::withoutGlobalScopes()->find($contact->id))->toBeNull()
        ->and(ImportSession::find($session->id))->toBeNull();
});

// ── rollback() — notes ───────────────────────────────────────────────────────

it('rollback for notes force-deletes notes and discards staged updates', function () {
    $session = makeActionsSession('note');
    $contact = Contact::factory()->create();
    $note = Note::create([
        'notable_type'      => Contact::class,
        'notable_id'        => $contact->id,
        'body'              => 'imported note',
        'import_session_id' => $session->id,
    ]);

    ImportStagedUpdate::create([
        'import_session_id' => $session->id,
        'subject_type'      => Note::class,
        'subject_id'        => $note->id,
        'attributes'        => ['body' => 'would be applied'],
    ]);

    $this->actions->rollback($session);

    expect(Note::withTrashed()->find($note->id))->toBeNull()
        ->and(ImportStagedUpdate::where('import_session_id', $session->id)->count())->toBe(0)
        ->and(ImportSession::find($session->id))->toBeNull();
});

// ── delete() — cascades match rollback for non-contact types ─────────────────

it('delete for events behaves like rollback', function () {
    $session = makeActionsSession('event');
    $event = Event::factory()->create([
        'import_session_id' => $session->id,
    ]);

    $this->actions->delete($session);

    expect(Event::find($event->id))->toBeNull()
        ->and(ImportSession::find($session->id))->toBeNull();
});

it('delete for contacts hard-deletes staged updates without audit notes', function () {
    $session = makeActionsSession('contact');
    $existing = Contact::factory()->create(['city' => 'OriginalCity']);

    ImportStagedUpdate::create([
        'import_session_id' => $session->id,
        'subject_type'      => Contact::class,
        'subject_id'        => $existing->id,
        'attributes'        => ['city' => 'ShouldNotApply'],
    ]);

    $this->actions->delete($session);

    expect($existing->fresh()->city)->toBe('OriginalCity')
        ->and(ImportStagedUpdate::where('import_session_id', $session->id)->count())->toBe(0)
        ->and(Note::where('notable_id', $existing->id)
            ->where('body', 'like', '%discarded during rollback%')
            ->exists())->toBeFalse()
        ->and(ImportSession::find($session->id))->toBeNull();
});

// ── Description builders ─────────────────────────────────────────────────────

it('approveDescription branches per model_type', function () {
    $contactSession = makeActionsSession('contact', ['row_count' => 7]);
    $eventSession   = makeActionsSession('event');
    Event::factory()->create(['import_session_id' => $eventSession->id]);
    $noteSession = makeActionsSession('note');

    expect($this->actions->approveDescription($contactSession))
        ->toContain('7 contacts');
    expect($this->actions->approveDescription($eventSession))
        ->toContain('event(s)')
        ->toContain('registration(s)');
    expect($this->actions->approveDescription($noteSession))
        ->toContain('note(s)');
});

it('rollbackDescription and deleteDescription surface correct counts per type', function () {
    $session = makeActionsSession('event');
    Event::factory()->count(2)->create([
        'import_session_id' => $session->id,
    ]);

    expect($this->actions->rollbackDescription($session))
        ->toContain('2 event(s)');
    expect($this->actions->deleteDescription($session))
        ->toContain('2 event(s)')
        ->toContain('ImportIdMap');
});

it('approve batches subject loads so staged count does not drive query count', function () {
    $session  = makeActionsSession('contact');
    $contacts = Contact::factory()->count(25)->create();

    foreach ($contacts as $contact) {
        ImportStagedUpdate::create([
            'import_session_id' => $session->id,
            'subject_type'      => Contact::class,
            'subject_id'        => $contact->id,
            'attributes'        => ['city' => 'BatchCity'],
        ]);
    }

    \Illuminate\Support\Facades\DB::flushQueryLog();
    \Illuminate\Support\Facades\DB::enableQueryLog();

    $this->actions->approve($session);

    $queries = \Illuminate\Support\Facades\DB::getQueryLog();
    \Illuminate\Support\Facades\DB::disableQueryLog();

    expect(count($queries))->toBeLessThan(110);
});
