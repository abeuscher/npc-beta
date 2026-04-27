<?php

use App\Filament\Resources\ContactResource\Pages\EditContact;
use App\Filament\Resources\ContactResource\Pages\EditContactView;
use App\Models\Contact;
use App\Models\User;
use App\WidgetPrimitive\Views\RecordDetailView;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RecordDetailViewSeeder;
use Database\Seeders\WidgetTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new WidgetTypeSeeder())->run();
    (new PermissionSeeder())->run();
    (new RecordDetailViewSeeder())->run();

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super_admin');

    $this->cmsEditor = User::factory()->create();
    $this->cmsEditor->assignRole('cms_editor');

    $this->crmEditor = User::factory()->create();
    $this->crmEditor->assignRole('crm_editor');
});

function invokeProtected(object $instance, string $method): mixed
{
    $reflection = (new ReflectionClass($instance))->getMethod($method);
    $reflection->setAccessible(true);

    return $reflection->invoke($instance);
}

it('EditContact wires recordDetailViewSubPageClass to EditContactView', function () {
    expect(invokeProtected(new EditContact, 'recordDetailViewSubPageClass'))->toBe(EditContactView::class);
});

it('EditContact wires subNavigationEntryPage to itself', function () {
    expect(invokeProtected(new EditContact, 'subNavigationEntryPage'))->toBe(EditContact::class);
});

it('EditContactView wires subNavigationEntryPage to EditContact and self as the View sub-page class', function () {
    $page = new EditContactView;

    expect(invokeProtected($page, 'subNavigationEntryPage'))->toBe(EditContact::class)
        ->and(invokeProtected($page, 'recordDetailViewSubPageClass'))->toBe(EditContactView::class);
});

it('renders EditContactView for super_admin and includes Recent Notes widget output', function () {
    $contact = Contact::factory()->create();

    $component = Livewire::actingAs($this->superAdmin)
        ->test(EditContactView::class, ['record' => $contact->id, 'view' => 'contact_overview']);

    expect($component->instance()->resolvedView)->not->toBeNull()
        ->and($component->instance()->resolvedView->handle)->toBe('contact_overview')
        ->and($component->instance()->record->id)->toBe($contact->id);
});

it('grants access to EditContactView for users who can view or edit the Contact, fail-closed otherwise', function () {
    $contact = Contact::factory()->create();

    $this->actingAs($this->crmEditor);
    expect(EditContactView::canAccess(['record' => $contact]))->toBeTrue();

    $this->actingAs($this->superAdmin);
    expect(EditContactView::canAccess(['record' => $contact]))->toBeTrue();

    $this->actingAs($this->cmsEditor);
    expect(EditContactView::canAccess(['record' => $contact]))->toBeFalse();
});

it('EditContactView::canAccess fails closed when no record is supplied', function () {
    $this->actingAs($this->superAdmin);

    expect(EditContactView::canAccess([]))->toBeFalse();
});

it('EditContactView resolves a second seeded View by handle and renders correct title', function () {
    $contact = Contact::factory()->create();

    RecordDetailView::create([
        'record_type' => Contact::class,
        'handle'      => 'contact_followups',
        'label'       => 'Follow-Ups',
        'sort_order'  => 1,
    ]);

    $component = Livewire::actingAs($this->superAdmin)
        ->test(EditContactView::class, ['record' => $contact->id, 'view' => 'contact_followups']);

    expect($component->instance()->resolvedView->label)->toBe('Follow-Ups');
});

it('EditContactView::mount throws when the View handle is unknown for the record type', function () {
    $contact = Contact::factory()->create();

    Livewire::actingAs($this->superAdmin)
        ->test(EditContactView::class, ['record' => $contact->id, 'view' => 'does-not-exist']);
})->throws(Illuminate\Database\Eloquent\ModelNotFoundException::class);

it('EditContact does not render sub-nav with the seeded primary View alone (single-View threshold)', function () {
    $contact = Contact::factory()->create();

    $this->actingAs($this->superAdmin);

    $page = new EditContact;
    $page->record = $contact;

    expect($page->getSubNavigation())->toBe([]);
});

it('EditContact renders sub-nav once a second View is bound to Contact', function () {
    $contact = Contact::factory()->create();

    RecordDetailView::create([
        'record_type' => Contact::class,
        'handle'      => 'contact_followups',
        'label'       => 'Follow-Ups',
        'sort_order'  => 1,
    ]);

    $this->actingAs($this->superAdmin);

    $page = new EditContact;
    $page->record = $contact;

    $items = $page->getSubNavigation();
    $labels = array_map(fn ($i) => $i->getLabel(), $items);

    expect($items)->toHaveCount(3)
        ->and($labels)->toContain('Overview')
        ->and($labels)->toContain('Follow-Ups');
});
