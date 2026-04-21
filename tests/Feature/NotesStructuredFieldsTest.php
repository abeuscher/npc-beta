<?php

use App\Filament\Resources\ContactResource\Pages\ContactNotes;
use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo(['create_note', 'update_note', 'delete_note']);
    $this->actingAs($this->admin);

    $this->contact = Contact::factory()->create();
});

// ── Schema ──────────────────────────────────────────────────────────────────

it('adds the six structured columns to the notes table', function () {
    $columns = ['type', 'subject', 'status', 'follow_up_at', 'outcome', 'duration_minutes', 'meta'];

    foreach ($columns as $column) {
        expect(Schema::hasColumn('notes', $column))->toBeTrue("notes.{$column} missing");
    }
});

it('applies default type=note and status=completed when only body is provided', function () {
    $note = Note::create([
        'notable_id'   => $this->contact->id,
        'notable_type' => Contact::class,
        'author_id'    => $this->admin->id,
        'body'         => 'Bare note — no structured fields set.',
    ]);

    expect($note->fresh()->type)->toBe('note')
        ->and($note->fresh()->status)->toBe('completed');
});

it('casts meta to array and duration_minutes to int', function () {
    $note = Note::create([
        'notable_id'       => $this->contact->id,
        'notable_type'     => Contact::class,
        'author_id'        => $this->admin->id,
        'body'             => 'Call about grant.',
        'type'             => 'call',
        'duration_minutes' => '42',
        'meta'             => ['priority' => 'high', 'location' => 'Zoom'],
    ]);

    $fresh = $note->fresh();

    expect($fresh->duration_minutes)->toBe(42)
        ->and($fresh->meta)->toMatchArray(['priority' => 'high', 'location' => 'Zoom']);
});

// ── Create / edit actions persist new fields ─────────────────────────────────

it('persists the new fields through the ContactNotes create_note action', function () {
    $page = new ContactNotes();
    $page->mount($this->contact);

    $this->contact->notes()->create([
        'author_id'        => $this->admin->id,
        'type'             => 'call',
        'subject'          => 'Intro call with donor',
        'status'           => 'completed',
        'body'             => 'Discussed spring appeal.',
        'occurred_at'      => now(),
        'outcome'          => 'Will consider a gift.',
        'duration_minutes' => 25,
    ]);

    $note = Note::where('notable_id', $this->contact->id)->first();

    expect($note->type)->toBe('call')
        ->and($note->subject)->toBe('Intro call with donor')
        ->and($note->outcome)->toBe('Will consider a gift.')
        ->and($note->duration_minutes)->toBe(25);
});

it('persists a scheduled task with a follow-up date', function () {
    $followUp = now()->addDays(7);

    $note = $this->contact->notes()->create([
        'author_id'    => $this->admin->id,
        'type'         => 'task',
        'subject'      => 'Send thank-you letter',
        'status'       => 'scheduled',
        'body'         => 'After board meeting.',
        'occurred_at'  => now(),
        'follow_up_at' => $followUp,
    ]);

    expect($note->type)->toBe('task')
        ->and($note->status)->toBe('scheduled')
        ->and($note->follow_up_at->format('Y-m-d'))->toBe($followUp->format('Y-m-d'));
});

it('persists a note with importer-supplied meta payload', function () {
    $note = Note::factory()->create([
        'notable_id'   => $this->contact->id,
        'notable_type' => Contact::class,
        'type'         => 'meeting',
        'meta'         => ['civicrm_activity_id' => 'civ-9001', 'participants' => ['Board member A']],
    ]);

    expect($note->fresh()->meta)->toMatchArray([
        'civicrm_activity_id' => 'civ-9001',
        'participants'        => ['Board member A'],
    ]);
});

// ── Factory ─────────────────────────────────────────────────────────────────

it('NoteFactory produces valid records with structured defaults', function () {
    Note::factory()->count(10)->create([
        'notable_id'   => $this->contact->id,
        'notable_type' => Contact::class,
    ]);

    $notes = Note::where('notable_id', $this->contact->id)->get();

    expect($notes)->toHaveCount(10);
    $notes->each(function (Note $note) {
        expect($note->type)->toBeIn(['call', 'meeting', 'email', 'note', 'task', 'letter', 'sms'])
            ->and($note->status)->toBeString()
            ->and($note->body)->toBeString();
    });
});

// ── Timeline projection ─────────────────────────────────────────────────────

it('Timeline projection exposes the new structured fields on note items', function () {
    $this->contact->notes()->create([
        'author_id'        => $this->admin->id,
        'type'             => 'call',
        'subject'          => 'Quarterly check-in',
        'status'           => 'completed',
        'body'             => 'Positive conversation.',
        'outcome'          => 'Agreed to increase pledge.',
        'duration_minutes' => 30,
        'occurred_at'      => now()->subHour(),
        'meta'             => ['campaign' => 'Spring 2026'],
    ]);

    $page = new ContactNotes();
    $page->mount($this->contact);

    $timeline = $page->getTimeline();
    $item = $timeline->firstWhere('_type', 'note');

    expect($item)->not->toBeNull()
        ->and($item->type)->toBe('call')
        ->and($item->subject)->toBe('Quarterly check-in')
        ->and($item->status)->toBe('completed')
        ->and($item->outcome)->toBe('Agreed to increase pledge.')
        ->and($item->duration_minutes)->toBe(30)
        ->and($item->meta)->toBe(['campaign' => 'Spring 2026']);
});

it('Timeline respects the type filter on notes', function () {
    $this->contact->notes()->create([
        'author_id' => $this->admin->id,
        'type'      => 'call',
        'body'      => 'A call.',
    ]);
    $this->contact->notes()->create([
        'author_id' => $this->admin->id,
        'type'      => 'email',
        'body'      => 'An email.',
    ]);

    $page = new ContactNotes();
    $page->mount($this->contact);
    $page->typeFilter = 'call';

    $timeline = $page->getTimeline();
    $notes = $timeline->where('_type', 'note');

    expect($notes)->toHaveCount(1)
        ->and($notes->first()->type)->toBe('call');
});

it('Timeline disables type filter implicitly when source filter is activity-only', function () {
    $this->contact->notes()->create([
        'author_id' => $this->admin->id,
        'type'      => 'call',
        'body'      => 'A call.',
    ]);

    ActivityLog::create([
        'subject_type' => Contact::class,
        'subject_id'   => $this->contact->id,
        'actor_type'   => 'system',
        'actor_id'     => null,
        'event'        => 'created',
        'description'  => 'Record created',
    ]);

    $page = new ContactNotes();
    $page->mount($this->contact);
    $page->filter = 'activity';
    $page->typeFilter = 'call';

    $timeline = $page->getTimeline();

    expect($timeline->pluck('_type')->unique()->values()->all())->toBe(['activity']);
});

it('surfaces non-canonical imported types for the More filter submenu', function () {
    $this->contact->notes()->create([
        'author_id' => $this->admin->id,
        'type'      => 'Phone Call',
        'body'      => 'From CiviCRM import.',
    ]);
    $this->contact->notes()->create([
        'author_id' => $this->admin->id,
        'type'      => 'note',
        'body'      => 'Plain note.',
    ]);

    $page = new ContactNotes();
    $page->mount($this->contact);

    expect($page->getNonCanonicalTypes())->toBe(['Phone Call']);
});

// ── Backwards-compat rendering ──────────────────────────────────────────────

// ── View mode toggle ───────────────────────────────────────────────────────

it('ContactNotes defaults to collapsed view mode', function () {
    $page = new ContactNotes();
    $page->mount($this->contact);

    expect($page->viewMode)->toBe('collapsed');
});

it('toggleViewMode flips between collapsed and expanded', function () {
    $page = new ContactNotes();
    $page->mount($this->contact);

    $page->toggleViewMode();
    expect($page->viewMode)->toBe('expanded');

    $page->toggleViewMode();
    expect($page->viewMode)->toBe('collapsed');
});

it('Timeline projection returns full structured fields regardless of view mode', function () {
    $this->contact->notes()->create([
        'author_id' => $this->admin->id,
        'type'      => 'call',
        'subject'   => 'Big pledge conversation',
        'body'      => 'Long body content that only shows in expanded mode.',
        'outcome'   => 'Closed a gift.',
    ]);

    $page = new ContactNotes();
    $page->mount($this->contact);

    foreach (['collapsed', 'expanded'] as $mode) {
        $page->viewMode = $mode;
        $item = $page->getTimeline()->firstWhere('_type', 'note');

        expect($item)->not->toBeNull()
            ->and($item->subject)->toBe('Big pledge conversation')
            ->and($item->outcome)->toBe('Closed a gift.');
    }
});

it('renders a bare pre-structured note without errors', function () {
    $this->contact->notes()->create([
        'author_id'   => $this->admin->id,
        'body'        => 'Old-style note, no subject, no outcome.',
        'occurred_at' => now()->subDay(),
    ]);

    $page = new ContactNotes();
    $page->mount($this->contact);

    $timeline = $page->getTimeline();
    $item = $timeline->firstWhere('_type', 'note');

    expect($item)->not->toBeNull()
        ->and($item->type)->toBe('note')
        ->and($item->status)->toBe('completed')
        ->and($item->subject)->toBeNull()
        ->and($item->outcome)->toBeNull()
        ->and($item->follow_up_at)->toBeNull()
        ->and($item->meta)->toBe([]);
});
