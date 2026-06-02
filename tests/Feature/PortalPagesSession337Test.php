<?php

use App\Models\Contact;
use App\Models\Page;
use App\Models\PortalAccount;
use App\Models\User;
use Database\Seeders\PortalPageSeeder;
use Database\Seeders\SystemPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // The page seeders stamp author_id from the first User, the same precondition
    // they rely on in DatabaseSeeder (which creates a user before they run).
    User::factory()->create();
    $this->seed(PortalPageSeeder::class);
    $this->seed(SystemPageSeeder::class);
});

function portalWidgetHandles(Page $page): array
{
    return $page->widgets()->with('widgetType')->get()
        ->pluck('widgetType.handle')->all();
}

it('seeds a published member dashboard with illustrative widgets', function () {
    $dashboard = Page::where('slug', 'members')->where('type', 'member')->first();

    expect($dashboard)->not->toBeNull();
    expect($dashboard->status)->toBe('published');
    expect(portalWidgetHandles($dashboard))
        ->toContain('text_block')
        ->toContain('bar_chart');
});

it('seeds a published account page carrying both account forms', function () {
    $account = Page::where('slug', 'members/account')->where('type', 'member')->first();

    expect($account)->not->toBeNull();
    expect($account->status)->toBe('published');
    expect(portalWidgetHandles($account))
        ->toContain('portal_contact_edit')
        ->toContain('portal_change_password');
});

it('retires the legacy split + dashboard pages', function () {
    expect(Page::where('slug', 'system/account')->exists())->toBeFalse();
    expect(Page::where('slug', 'members/edit-account')->exists())->toBeFalse();
    expect(Page::where('slug', 'members/change-password')->exists())->toBeFalse();
});

it('points the portal nav at the consolidated member pages', function () {
    $hrefs = \App\Models\NavigationMenu::where('handle', 'portal')->first()
        ->items()->orderBy('sort_order')->with('page')->get()
        ->map(fn ($i) => $i->page?->slug)->all();

    expect($hrefs)->toBe(['members', 'members/account', 'members/event-registrations']);
});

it('redirects the legacy /system/account alias to the member dashboard', function () {
    $contact = Contact::factory()->create();
    $account = PortalAccount::factory()->create([
        'contact_id'        => $contact->id,
        'is_active'         => true,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($account, 'portal')
        ->get(route('portal.account'))
        ->assertRedirect(url('/members'));
});

it('renders the dashboard with the portal hero band for a verified member', function () {
    $contact = Contact::factory()->create(['first_name' => 'Dana']);
    $account = PortalAccount::factory()->create([
        'contact_id'        => $contact->id,
        'is_active'         => true,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($account, 'portal')->get('/members');

    $response->assertOk();
    $response->assertSee('portal-hero', false);
    $response->assertSee('Welcome back, Dana', false);
    $response->assertSee('widget--bar_chart', false);
});

it('renders the account page with both account forms for a verified member', function () {
    $contact = Contact::factory()->create();
    $account = PortalAccount::factory()->create([
        'contact_id'        => $contact->id,
        'is_active'         => true,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($account, 'portal')->get('/members/account');

    $response->assertOk();
    $response->assertSee('Mailing Address');
    $response->assertSee('Password');
    $response->assertSee('data-tour="portal.account"', false);
});
