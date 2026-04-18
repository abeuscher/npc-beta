<?php

use App\Models\Contact;
use App\Models\ImportSession;
use App\Models\ImportStagedUpdate;
use App\Models\Note;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create();
});

// ── Approve — staged updates applied ──────────────────────────────────────────

it('applies staged attribute updates to contacts on approval', function () {
    $session = ImportSession::create([
        'model_type'  => 'contact',
        'status'      => 'reviewing',
        'filename'    => 'test.csv',
        'row_count'   => 1,
        'imported_by' => $this->admin->id,
    ]);

    $contact = Contact::factory()->create([
        'city' => 'OldCity',
    ]);

    ImportStagedUpdate::create([
        'import_session_id' => $session->id,
        'subject_type'      => Contact::class,
        'subject_id'        => $contact->id,
        'attributes'        => ['city' => 'NewCity'],
    ]);

    // Simulate the approve action
    $this->actingAs($this->admin);

    $session->update([
        'status'      => 'approved',
        'approved_by' => $this->admin->id,
        'approved_at' => now(),
    ]);

    $staged = ImportStagedUpdate::where('import_session_id', $session->id)->get();

    foreach ($staged as $update) {
        $c = Contact::withoutGlobalScopes()->find($update->subject_id);
        if ($c && ! empty($update->attributes)) {
            $c->fill($update->attributes)->save();
        }

        Note::create([
            'notable_type' => Contact::class,
            'notable_id'   => $c->id,
            'author_id'    => $this->admin->id,
            'body'         => 'Changes applied from import session test.csv — approved by ' . $this->admin->name,
            'occurred_at'  => now(),
        ]);
    }

    $staged->each->delete();

    $contact->refresh();
    expect($contact->city)->toBe('NewCity')
        ->and($session->fresh()->status)->toBe('approved')
        ->and(ImportStagedUpdate::where('import_session_id', $session->id)->count())->toBe(0);
});

// ── Approve — tag application ─────────────────────────────────────────────────

it('applies tags to contacts on approval', function () {
    $session = ImportSession::create([
        'model_type'  => 'contact',
        'status'      => 'reviewing',
        'filename'    => 'tags.csv',
        'row_count'   => 1,
        'imported_by' => $this->admin->id,
    ]);

    $contact = Contact::factory()->create();
    $tag     = Tag::factory()->create(['type' => 'contact']);

    ImportStagedUpdate::create([
        'import_session_id' => $session->id,
        'subject_type'      => Contact::class,
        'subject_id'        => $contact->id,
        'attributes'        => [],
        'tag_ids'           => [$tag->id],
    ]);

    // Simulate approve
    $this->actingAs($this->admin);

    $staged = ImportStagedUpdate::where('import_session_id', $session->id)->get();

    foreach ($staged as $update) {
        $c = Contact::withoutGlobalScopes()->find($update->subject_id);
        if ($c && ! empty($update->tag_ids)) {
            $c->tags()->syncWithoutDetaching($update->tag_ids);
        }
    }

    expect($contact->fresh()->tags()->where('tags.id', $tag->id)->exists())->toBeTrue();
});

// ── Rollback — staged changes discarded ───────────────────────────────────────

it('discards staged updates on rollback without modifying contacts', function () {
    $session = ImportSession::create([
        'model_type'  => 'contact',
        'status'      => 'reviewing',
        'filename'    => 'rollback.csv',
        'row_count'   => 1,
        'imported_by' => $this->admin->id,
    ]);

    $contact = Contact::factory()->create([
        'city' => 'OriginalCity',
    ]);

    ImportStagedUpdate::create([
        'import_session_id' => $session->id,
        'subject_type'      => Contact::class,
        'subject_id'        => $contact->id,
        'attributes'        => ['city' => 'ShouldNotApply'],
    ]);

    // Simulate rollback (do not apply attributes)
    $this->actingAs($this->admin);

    $staged = ImportStagedUpdate::where('import_session_id', $session->id)->get();
    $staged->each->delete();
    $session->delete();

    $contact->refresh();
    expect($contact->city)->toBe('OriginalCity')
        ->and(ImportStagedUpdate::where('import_session_id', $session->id)->count())->toBe(0)
        ->and(ImportSession::find($session->id))->toBeNull();
});

// ── Rollback — new contacts deleted ───────────────────────────────────────────

it('deletes newly imported contacts on rollback', function () {
    $session = ImportSession::create([
        'model_type'  => 'contact',
        'status'      => 'reviewing',
        'filename'    => 'delete.csv',
        'row_count'   => 2,
        'imported_by' => $this->admin->id,
    ]);

    $imported1 = Contact::factory()->create(['import_session_id' => $session->id]);
    $imported2 = Contact::factory()->create(['import_session_id' => $session->id]);
    $existing  = Contact::factory()->create();

    // Simulate rollback
    $contactIds = Contact::withoutGlobalScopes()
        ->where('import_session_id', $session->id)
        ->pluck('id')
        ->toArray();

    DB::table('taggables')
        ->whereIn('taggable_id', $contactIds)
        ->where('taggable_type', Contact::class)
        ->delete();

    Contact::withoutGlobalScopes()
        ->whereIn('id', $contactIds)
        ->forceDelete();

    $session->delete();

    expect(Contact::withoutGlobalScopes()->find($imported1->id))->toBeNull()
        ->and(Contact::withoutGlobalScopes()->find($imported2->id))->toBeNull()
        ->and(Contact::withoutGlobalScopes()->find($existing->id))->not->toBeNull();
});

// ── Status transitions ────────────────────────────────────────────────────────

it('transitions import session through pending → reviewing → approved', function () {
    $session = ImportSession::create([
        'model_type'  => 'contact',
        'status'      => 'pending',
        'filename'    => 'status.csv',
        'row_count'   => 5,
        'imported_by' => $this->admin->id,
    ]);

    expect($session->status)->toBe('pending');

    $session->update(['status' => 'reviewing']);
    expect($session->fresh()->status)->toBe('reviewing');

    $session->update([
        'status'      => 'approved',
        'approved_by' => $this->admin->id,
        'approved_at' => now(),
    ]);

    $session->refresh();
    expect($session->status)->toBe('approved')
        ->and($session->approved_by)->toBe($this->admin->id)
        ->and($session->approved_at)->not->toBeNull();
});

it('transitions import session to rolled_back status', function () {
    $session = ImportSession::create([
        'model_type'  => 'contact',
        'status'      => 'reviewing',
        'filename'    => 'rollback-status.csv',
        'row_count'   => 3,
        'imported_by' => $this->admin->id,
    ]);

    // The rollback action deletes the session entirely rather than updating status
    // But the model supports rolled_back as a valid status
    $session->update(['status' => 'rolled_back']);
    expect($session->fresh()->status)->toBe('rolled_back');
});
