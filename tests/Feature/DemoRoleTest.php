<?php

use App\Filament\Resources\ContactResource;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\PageResource;
use App\Filament\Resources\UserResource;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Form;
use App\Models\MailingList;
use App\Models\Page;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder']);

    $this->demoUser = User::factory()->create(['is_active' => true]);
    $this->demoUser->assignRole('demo');
});

// ── Role setup ──────────────────────────────────────────────────────────────

it('demo role has only view permissions', function () {
    $permissions = $this->demoUser->getAllPermissions()->pluck('name');

    // Every permission should start with view_any_ or view_
    $nonViewPerms = $permissions->reject(fn ($p) => str_starts_with($p, 'view_any_') || str_starts_with($p, 'view_'));

    expect($nonViewPerms)->toBeEmpty()
        ->and($permissions->count())->toBeGreaterThan(0);
})->group('demo');

it('user isDemo() returns true for demo role', function () {
    expect($this->demoUser->isDemo())->toBeTrue();
})->group('demo');

// ── CRM resource gates ─────────────────────────────────────────────────────

it('demo user can view contacts but not create/update/delete', function () {
    $contact = Contact::factory()->create();

    expect($this->demoUser->can('viewAny', Contact::class))->toBeTrue()
        ->and($this->demoUser->can('view', $contact))->toBeTrue()
        ->and($this->demoUser->can('create', Contact::class))->toBeFalse()
        ->and($this->demoUser->can('update', $contact))->toBeFalse()
        ->and($this->demoUser->can('delete', $contact))->toBeFalse();
})->group('demo');

// ── CMS resource gates ─────────────────────────────────────────────────────

it('demo user can view pages but not create/update/delete', function () {
    $page = Page::factory()->create();

    expect($this->demoUser->can('viewAny', Page::class))->toBeTrue()
        ->and($this->demoUser->can('create', Page::class))->toBeFalse()
        ->and($this->demoUser->can('update', $page))->toBeFalse()
        ->and($this->demoUser->can('delete', $page))->toBeFalse();
})->group('demo');

// ── New policy enforcement (Event, Form, Product, MailingList) ──────────────

it('demo user cannot create/update/delete events', function () {
    $event = Event::factory()->create();

    expect($this->demoUser->can('viewAny', Event::class))->toBeTrue()
        ->and($this->demoUser->can('create', Event::class))->toBeFalse()
        ->and($this->demoUser->can('update', $event))->toBeFalse()
        ->and($this->demoUser->can('delete', $event))->toBeFalse();
})->group('demo');

it('demo user cannot create/update/delete forms', function () {
    $form = Form::factory()->create();

    expect($this->demoUser->can('viewAny', Form::class))->toBeTrue()
        ->and($this->demoUser->can('create', Form::class))->toBeFalse()
        ->and($this->demoUser->can('update', $form))->toBeFalse()
        ->and($this->demoUser->can('delete', $form))->toBeFalse();
})->group('demo');

it('demo user cannot create/update/delete products', function () {
    $product = Product::factory()->create();

    expect($this->demoUser->can('viewAny', Product::class))->toBeTrue()
        ->and($this->demoUser->can('create', Product::class))->toBeFalse()
        ->and($this->demoUser->can('update', $product))->toBeFalse()
        ->and($this->demoUser->can('delete', $product))->toBeFalse();
})->group('demo');

it('demo user cannot create/update/delete mailing lists', function () {
    $list = MailingList::create(['name' => 'Test List', 'is_active' => true]);

    expect($this->demoUser->can('viewAny', MailingList::class))->toBeTrue()
        ->and($this->demoUser->can('create', MailingList::class))->toBeFalse()
        ->and($this->demoUser->can('update', $list))->toBeFalse()
        ->and($this->demoUser->can('delete', $list))->toBeFalse();
})->group('demo');

// ── Filament route access ───────────────────────────────────────────────────

it('demo user can access contact list page', function () {
    $this->actingAs($this->demoUser)
        ->get('/admin/contacts')
        ->assertSuccessful();
})->group('demo');

it('demo user cannot access contact create page', function () {
    $this->actingAs($this->demoUser)
        ->get('/admin/contacts/create')
        ->assertForbidden();
})->group('demo');

it('demo user cannot access settings pages', function () {
    // Filament returns 403 or 404 for pages the user lacks access to
    $response = $this->actingAs($this->demoUser)->get('/admin/general-settings');
    expect(in_array($response->status(), [403, 404]))->toBeTrue();

    $response = $this->actingAs($this->demoUser)->get('/admin/mail-settings');
    expect(in_array($response->status(), [403, 404]))->toBeTrue();
})->group('demo');

it('demo user cannot access import pages', function () {
    $response = $this->actingAs($this->demoUser)->get('/admin/importer');
    expect(in_array($response->status(), [403, 404]))->toBeTrue();

    $response = $this->actingAs($this->demoUser)->get('/admin/import-contacts');
    expect(in_array($response->status(), [403, 404]))->toBeTrue();
})->group('demo');

it('demo user cannot access horizon', function () {
    $this->actingAs($this->demoUser)
        ->get('/horizon')
        ->assertForbidden();
})->group('demo');

// ── Users list scoping ──────────────────────────────────────────────────────

it('demo user does not see other demo or super_admin users in users list', function () {
    $otherDemo = User::factory()->create(['is_active' => true, 'name' => 'Other Demo']);
    $otherDemo->assignRole('demo');

    $superAdmin = User::factory()->create(['is_active' => true, 'name' => 'Hidden Admin']);
    $superAdmin->assignRole('super_admin');

    $staffUser = User::factory()->create(['is_active' => true, 'name' => 'Staff Person']);
    $staffUser->assignRole('cms_editor');

    $this->actingAs($this->demoUser);

    $response = $this->get('/admin/users');
    $response->assertSuccessful();
    $response->assertDontSee('Other Demo');
    $response->assertDontSee('Hidden Admin');
    $response->assertSee('Staff Person');
})->group('demo');

// ── Livewire write method hardening ─────────────────────────────────────────

it('page builder write methods abort for demo user', function () {
    $page = Page::factory()->create();

    $this->actingAs($this->demoUser);

    $component = Livewire::test(\App\Livewire\PageBuilder::class, ['pageId' => $page->id]);

    $widgetType = \App\Models\WidgetType::first();
    if ($widgetType) {
        $component->call('createBlock', $widgetType->id)
            ->assertStatus(403);
    }
})->group('demo');

it('page builder inspector write methods abort for demo user', function () {
    $page = Page::factory()->create();
    $widgetType = \App\Models\WidgetType::first();

    if (! $widgetType) {
        $this->markTestSkipped('No widget types seeded.');
    }

    $pw = \App\Models\PageWidget::create([
        'page_id' => $page->id,
        'widget_type_id' => $widgetType->id,
        'config' => [],
        'sort_order' => 0,
    ]);

    $this->actingAs($this->demoUser);

    Livewire::test(\App\Livewire\PageBuilderInspector::class, ['blockId' => $pw->id])
        ->call('updateConfig', 'test_key', 'test_value')
        ->assertStatus(403);
})->group('demo');

// ── Mail driver override ────────────────────────────────────────────────────

it('mail driver is forced to log in demo mode', function () {
    // Simulate demo mode
    app()['env'] = 'demo';

    // Re-boot the provider
    (new \App\Providers\AppServiceProvider(app()))->boot();

    expect(config('mail.default'))->toBe('log');
})->group('demo');
