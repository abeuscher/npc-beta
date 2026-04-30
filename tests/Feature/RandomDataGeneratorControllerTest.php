<?php

use App\Models\Contact;
use App\Models\Donation;
use App\Models\EventRegistration;
use App\Models\Membership;
use App\Models\Transaction;
use App\Models\User;
use App\WidgetPrimitive\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();
});

function actAsSuperAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    test()->actingAs($admin);

    return $admin;
}

it('store route — non-super-admin user gets 403', function () {
    $user = User::factory()->create();
    test()->actingAs($user);

    $response = $this->post(route('filament.admin.dev-tools.random-data.store'), [
        'counts' => ['contacts' => 1, 'events' => 0, 'registrations' => 0, 'donations' => 0, 'memberships' => 0],
    ]);

    $response->assertForbidden();
    expect(Contact::where('source', Source::SCRUB_DATA)->count())->toBe(0);
});

it('store route — unauthenticated request is redirected to login (not allowed to generate)', function () {
    $response = $this->post(route('filament.admin.dev-tools.random-data.store'), [
        'counts' => ['contacts' => 1, 'events' => 0, 'registrations' => 0, 'donations' => 0, 'memberships' => 0],
    ]);

    $response->assertRedirect();
    expect(Contact::where('source', Source::SCRUB_DATA)->count())->toBe(0);
});

it('wipe route — non-super-admin user gets 403', function () {
    $user = User::factory()->create();
    test()->actingAs($user);

    $response = $this->post(route('filament.admin.dev-tools.random-data.wipe'));

    $response->assertForbidden();
});

it('store route — super-admin generates rows tagged scrub_data', function () {
    actAsSuperAdmin();

    $response = $this->post(route('filament.admin.dev-tools.random-data.store'), [
        'counts' => ['contacts' => 4, 'events' => 0, 'registrations' => 0, 'donations' => 0, 'memberships' => 0],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('rdg_status');

    expect(Contact::where('source', Source::SCRUB_DATA)->count())->toBe(4);
});

it('store route — validation rejects per-type counts above the cap', function () {
    actAsSuperAdmin();

    $response = $this->post(route('filament.admin.dev-tools.random-data.store'), [
        'counts' => ['contacts' => 1001, 'events' => 0, 'registrations' => 0, 'donations' => 0, 'memberships' => 0],
    ]);

    $response->assertSessionHasErrors(['counts.contacts']);
    expect(Contact::where('source', Source::SCRUB_DATA)->count())->toBe(0);
});

it('end-to-end — generate then wipe via controller leaves real data intact', function () {
    actAsSuperAdmin();

    $realContact = Contact::factory()->create();

    $this->post(route('filament.admin.dev-tools.random-data.store'), [
        'counts' => ['contacts' => 3, 'events' => 1, 'registrations' => 2, 'donations' => 4, 'memberships' => 2],
    ])->assertRedirect();

    expect(Contact::where('source', Source::SCRUB_DATA)->count())->toBe(3);

    $response = $this->post(route('filament.admin.dev-tools.random-data.wipe'));
    $response->assertRedirect();
    $response->assertSessionHas('rdg_status');

    expect(Contact::where('source', Source::SCRUB_DATA)->count())->toBe(0)
        ->and(Donation::where('source', Source::SCRUB_DATA)->count())->toBe(0)
        ->and(Membership::where('source', Source::SCRUB_DATA)->count())->toBe(0)
        ->and(EventRegistration::where('source', Source::SCRUB_DATA)->count())->toBe(0)
        ->and(Transaction::where('source', Source::SCRUB_DATA)->count())->toBe(0)
        ->and(Contact::where('id', $realContact->id)->exists())->toBeTrue();
});

it('widget template renders empty for non-super-admin user', function () {
    $user = User::factory()->create();
    test()->actingAs($user);

    $rendered = view('widgets::RandomDataGenerator.template')
        ->with('errors', new \Illuminate\Support\ViewErrorBag())
        ->render();

    expect(trim($rendered))->toBe('');
});

it('widget template renders content for super-admin user', function () {
    actAsSuperAdmin();

    $rendered = view('widgets::RandomDataGenerator.template')
        ->with('errors', new \Illuminate\Support\ViewErrorBag())
        ->render();

    expect($rendered)->toContain('Random Data Generator')
        ->and($rendered)->toContain('counts[contacts]')
        ->and($rendered)->toContain('Wipe scrub data');
});
