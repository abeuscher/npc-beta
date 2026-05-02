<?php

use App\Filament\Resources\OrganizationResource\Pages\OrganizationNotes;
use App\Models\Note;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    foreach (['create_note', 'update_note', 'delete_note'] as $perm) {
        Permission::findOrCreate($perm, 'web');
    }

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo(['create_note', 'update_note', 'delete_note']);
    $this->actingAs($this->admin);

    $this->org = Organization::factory()->create(['name' => 'ACME Foundation']);
});

it('mount accepts an Organization model and assigns the typed record', function () {
    $page = new OrganizationNotes();
    $page->mount($this->org);

    expect($page->record)->toBeInstanceOf(Organization::class)
        ->and($page->record->id)->toBe($this->org->id);
});

it('mount accepts an Organization id string', function () {
    $page = new OrganizationNotes();
    $page->mount($this->org->id);

    expect($page->record->id)->toBe($this->org->id);
});

it('title formats as "{name} — Timeline"', function () {
    $page = new OrganizationNotes();
    $page->mount($this->org);

    expect($page->getTitle())->toBe('ACME Foundation — Timeline');
});

it('getTimeline surfaces polymorphic notes filed against the Organization', function () {
    $this->org->notes()->create([
        'author_id'   => $this->admin->id,
        'type'        => 'meeting',
        'subject'     => 'Quarterly check-in',
        'status'      => 'completed',
        'body'        => 'Discussed renewal of partnership.',
        'occurred_at' => now(),
    ]);

    $page = new OrganizationNotes();
    $page->mount($this->org);

    $items = $page->getTimeline();

    expect($items)->toHaveCount(1)
        ->and($items->first()->_type)->toBe('note')
        ->and($items->first()->subject)->toBe('Quarterly check-in')
        ->and($items->first()->type)->toBe('meeting');
});

it('getTimeline does not leak Notes filed against other notable types', function () {
    Note::create([
        'notable_type' => \App\Models\Contact::class,
        'notable_id'   => \App\Models\Contact::factory()->create()->id,
        'author_id'    => $this->admin->id,
        'type'         => 'note',
        'body'         => 'Contact-side note',
        'occurred_at'  => now(),
    ]);

    $this->org->notes()->create([
        'author_id'   => $this->admin->id,
        'type'        => 'note',
        'body'        => 'Org-side note',
        'occurred_at' => now(),
    ]);

    $page = new OrganizationNotes();
    $page->mount($this->org);

    $items = $page->getTimeline();

    expect($items)->toHaveCount(1)
        ->and($items->first()->body)->toBe('Org-side note');
});

it('Type filter scopes the timeline by Note.type', function () {
    $this->org->notes()->create([
        'author_id' => $this->admin->id, 'type' => 'meeting', 'body' => 'M', 'occurred_at' => now(),
    ]);
    $this->org->notes()->create([
        'author_id' => $this->admin->id, 'type' => 'call', 'body' => 'C', 'occurred_at' => now(),
    ]);

    $page = new OrganizationNotes();
    $page->mount($this->org);
    $page->typeFilter = 'call';

    $items = $page->getTimeline();

    expect($items)->toHaveCount(1)
        ->and($items->first()->type)->toBe('call');
});

it('OrganizationNotes defaults to collapsed view mode', function () {
    $page = new OrganizationNotes();
    $page->mount($this->org);

    expect($page->viewMode)->toBe('collapsed');
});

it('toggleViewMode flips between collapsed and expanded', function () {
    $page = new OrganizationNotes();
    $page->mount($this->org);

    expect($page->viewMode)->toBe('collapsed');

    $page->toggleViewMode();
    expect($page->viewMode)->toBe('expanded');

    $page->toggleViewMode();
    expect($page->viewMode)->toBe('collapsed');
});

it('OrganizationResource registers the notes route', function () {
    $pages = \App\Filament\Resources\OrganizationResource::getPages();

    expect(array_keys($pages))->toContain('notes');
});
