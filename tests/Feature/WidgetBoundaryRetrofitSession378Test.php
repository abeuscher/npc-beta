<?php

// Session 378 (Plugin Architecture arc P2): render-parity tests for the
// twelve widget templates retrofitted from direct model/auth/service reaches
// onto declared data contracts. Each widget renders through the production
// path (WidgetRenderer) in both data states — populated/empty, logged-in/out
// — and must produce the same output the pre-retrofit template did.
// (SetupChecklist and RandomDataGenerator parity lives in their own test
// files, updated in the same pass.)

use App\Models\Event;
use App\Models\Fund;
use App\Models\MembershipTier;
use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\PortalAccount;
use App\Models\Contact;
use App\Models\SiteSetting;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
    // Production renders inside the web middleware, which always shares an
    // error bag; templates using $errors rely on it.
    View::share('errors', new ViewErrorBag());
});

function renderRetrofitWidget(string $handle, array $config = []): string
{
    $wt = WidgetType::where('handle', $handle)->firstOrFail();
    $host = Page::factory()->create([
        'title'  => 'Retrofit Host',
        'slug'   => 'retrofit-host-' . uniqid(),
        'status' => 'published',
    ]);
    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => $config,
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    return WidgetRenderer::render($pw)['html'] ?? '';
}

function loginRetrofitMember(array $contactAttrs = []): PortalAccount
{
    $contact = Contact::factory()->create(array_merge([
        'first_name'  => 'Robin',
        'last_name'   => 'Parity',
        'city'        => 'Salem',
        'state'       => 'OR',
        'postal_code' => '97301',
        'country'     => 'USA',
    ], $contactAttrs));
    $account = PortalAccount::factory()->create(['contact_id' => $contact->id]);
    test()->actingAs($account, 'portal');

    return $account;
}

// ── DonationForm ────────────────────────────────────────────────────────────

it('DonationForm renders active funds as designation options and hides inactive/archived ones', function () {
    Fund::factory()->create(['name' => 'Building Fund', 'is_active' => true, 'is_archived' => false]);
    Fund::factory()->create(['name' => 'Secret Inactive Fund', 'is_active' => false, 'is_archived' => false]);
    Fund::factory()->create(['name' => 'Secret Archived Fund', 'is_active' => true, 'is_archived' => true]);

    $html = renderRetrofitWidget('donation_form');

    expect($html)
        ->toContain('Designate to a fund')
        ->toContain('Building Fund')
        ->not->toContain('Secret Inactive Fund')
        ->not->toContain('Secret Archived Fund');
});

it('DonationForm omits the fund picker when no funds are available', function () {
    $html = renderRetrofitWidget('donation_form');

    expect($html)
        ->toContain('Select an amount')
        ->not->toContain('Designate to a fund');
});

// ── Nav (dropdown gradient — the moved GradientComposer call) ───────────────

it('Nav renders the dropdown fill gradient composed from config', function () {
    $menu = NavigationMenu::create(['label' => 'Primary', 'handle' => 'primary-grad']);
    $parent = NavigationItem::create([
        'navigation_menu_id' => $menu->id, 'label' => 'Parent', 'url' => '/parent',
        'sort_order' => 0, 'target' => '_self', 'is_visible' => true,
    ]);
    NavigationItem::create([
        'navigation_menu_id' => $menu->id, 'parent_id' => $parent->id, 'label' => 'Child',
        'url' => '/child', 'sort_order' => 0, 'target' => '_self', 'is_visible' => true,
    ]);

    $html = renderRetrofitWidget('nav', [
        'navigation_menu_id' => (string) $menu->id,
        'drop_fill_gradient' => [
            'gradients' => [
                ['type' => 'linear', 'from' => '#ff0000', 'to' => '#0000ff', 'angle' => 90],
            ],
        ],
    ]);

    expect($html)
        ->toContain('Parent')
        ->toContain('Child')
        ->toContain('background-image:linear-gradient(90deg, #ff0000, #0000ff)');
});

// ── PortalSignup (tiers + brand, multi-contract) ────────────────────────────

it('PortalSignup renders the site brand and active tiers with price labels', function () {
    SiteSetting::set('site_name', 'Parity Nonprofit');
    MembershipTier::factory()->create([
        'name' => 'Patron', 'default_price' => 120, 'billing_interval' => 'annual',
        'sort_order' => 1, 'is_active' => true, 'is_archived' => false,
    ]);
    MembershipTier::factory()->create([
        'name' => 'Basic', 'default_price' => null, 'billing_interval' => 'annual',
        'sort_order' => 0, 'is_active' => true, 'is_archived' => false,
    ]);
    MembershipTier::factory()->create([
        'name' => 'Ghost Tier', 'sort_order' => 2, 'is_active' => false, 'is_archived' => false,
    ]);

    $html = renderRetrofitWidget('portal_signup');

    expect($html)
        ->toContain('Parity Nonprofit')
        ->toContain('Membership tier')
        ->toContain('Patron — $120.00/year')
        ->toContain('Basic — Free')
        ->not->toContain('Ghost Tier');
});

it('PortalSignup omits the tier picker when no tiers exist', function () {
    $html = renderRetrofitWidget('portal_signup');

    expect($html)
        ->toContain('Create your account')
        ->not->toContain('Membership tier');
});

// ── PortalLogin / PortalForgotPassword (brand reads) ────────────────────────

it('PortalLogin and PortalForgotPassword render the configured site name in the brand line', function () {
    SiteSetting::set('site_name', 'Brand Line Org');

    expect(renderRetrofitWidget('portal_login'))->toContain('Brand Line Org')
        ->and(renderRetrofitWidget('portal_forgot_password'))->toContain('Brand Line Org');
});

it('PortalLogin falls back to the app name when no site name is set', function () {
    expect(renderRetrofitWidget('portal_login'))->toContain(config('app.name'));
});

// ── Portal account widgets (logged-in / logged-out) ─────────────────────────

it('PortalAccountDashboard renders the member greeting and email when logged in, nothing when logged out', function () {
    expect(trim(renderRetrofitWidget('portal_account_dashboard')))->toBe('');

    $account = loginRetrofitMember();
    $html = renderRetrofitWidget('portal_account_dashboard');

    expect($html)
        ->toContain('Welcome back, Robin.')
        ->toContain($account->email)
        ->not->toContain('Household');
});

it('PortalAccountDashboard shows the household row only for household members', function () {
    $head = Contact::factory()->create(['last_name' => 'Headley']);
    $head->update(['household_id' => $head->id]);
    loginRetrofitMember(['household_id' => $head->id]);

    $html = renderRetrofitWidget('portal_account_dashboard');

    expect($html)
        ->toContain('Household')
        ->toContain('Headley Household');
});

it('PortalChangePassword renders the form when logged in, nothing when logged out', function () {
    expect(trim(renderRetrofitWidget('portal_change_password')))->toBe('');

    loginRetrofitMember();

    expect(renderRetrofitWidget('portal_change_password'))
        ->toContain('Update password')
        ->toContain(route('portal.account.update-password'));
});

it('PortalContactEdit prefills the member address and email when logged in, nothing when logged out', function () {
    expect(trim(renderRetrofitWidget('portal_contact_edit')))->toBe('');

    $account = loginRetrofitMember();
    $html = renderRetrofitWidget('portal_contact_edit');

    expect($html)
        ->toContain('value="Salem"')
        ->toContain('value="97301"')
        ->toContain($account->email)
        ->toContain(route('portal.account.update-address'));
});

it('PortalEventRegistrations renders the member registration rows, the empty copy, and nothing when logged out', function () {
    expect(trim(renderRetrofitWidget('portal_event_registrations')))->toBe('');

    $account = loginRetrofitMember();

    expect(renderRetrofitWidget('portal_event_registrations'))
        ->toContain('No event registrations on file.');

    $event = Event::factory()->create(['title' => 'Parity Gala', 'status' => 'published']);
    $account->contact->eventRegistrations()->create([
        'event_id'      => $event->id,
        'name'          => 'Robin Parity',
        'email'         => $account->email,
        'status'        => 'registered',
        'registered_at' => now(),
    ]);

    expect(renderRetrofitWidget('portal_event_registrations'))
        ->toContain('Parity Gala')
        ->toContain('Registered');
});

// ── EventRegistration (two-source: event + member presence) ─────────────────

function makeRetrofitEvent(): Event
{
    return Event::factory()->create([
        'title'             => 'Two Source Summit',
        'slug'              => 'two-source-summit',
        'status'            => 'published',
        'registration_mode' => 'open',
        'is_free'           => true,
    ]);
}

it('EventRegistration shows the guest form and member login link when logged out', function () {
    $event = makeRetrofitEvent();

    $html = renderRetrofitWidget('event_registration', ['event_slug' => $event->slug]);

    expect($html)
        ->toContain('Or register as a guest')
        ->toContain(route('events.register', $event->slug))
        ->toContain('Register as member');
});

it('EventRegistration shows the member form when a portal member is logged in', function () {
    $event = makeRetrofitEvent();
    loginRetrofitMember();

    $html = renderRetrofitWidget('event_registration', ['event_slug' => $event->slug]);

    expect($html)
        ->toContain(route('portal.events.register', $event->slug))
        ->toContain('Register as member')
        ->not->toContain('Or register as a guest');
});
