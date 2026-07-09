<?php

use App\Filament\Pages\Settings\AccountPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| The read-only "My Account" page (client billing, CB2 / session 367). Renders
| EXCLUSIVELY from the FM-pushed billing-state document via BillingStateReader.
| Fixtures write that document to the faked `local` disk exactly like CB1's tests.
*/

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    // Freeze time so the trial countdown + "as of" relative footer are deterministic.
    Carbon::setTestNow(Carbon::parse('2026-07-08T12:00:00+00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/** Write a billing-state document (deep-merged over a healthy active default). */
function seedBillingDoc(array $overrides = []): void
{
    Storage::fake('local');
    $doc = array_replace_recursive([
        'schema_version'        => 1,
        'as_of'                 => '2026-07-08T09:00:00+00:00', // 3h before frozen now
        'status'                => 'active',
        'plan'                  => ['name' => 'Standard', 'amount' => 4900, 'currency' => 'usd', 'interval' => 'month'],
        'next_invoice'          => [
            'date'       => '2026-08-01',
            'amount'     => 4900,
            'line_items' => [['description' => 'Subscription — Standard', 'amount' => 4900]],
        ],
        'billing_contact_email' => 'billing@example.org',
        'portal_url'            => 'https://billing.stripe.com/p/session/test_123',
        'suspension'            => ['state' => 'none', 'reason' => null, 'since' => null, 'grace_ends' => null],
        'trial'                 => ['ends_at' => null],
    ], $overrides);

    Storage::disk('local')->put('fleet/billing-state.json', json_encode($doc));
}

/** A super-admin (reaches manage_account via the Gate::before bypass). */
function billingAdmin(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('super_admin');

    return $user;
}

// ── Rendering: every field from the pushed document ──────────────────────────

it('renders plan, status, next invoice with line items, billing contact, portal, and staleness footer', function () {
    seedBillingDoc();

    $this->actingAs(billingAdmin())
        ->get('/admin/account-page')
        ->assertOk()
        ->assertSee('Standard')                        // plan name
        ->assertSee('$49.00 / month')                  // plan price (minor units formatted)
        ->assertSee('Active')                          // status badge
        ->assertSee('Aug 1, 2026')                     // next invoice date
        ->assertSee('Subscription — Standard')         // line item description
        ->assertSee('billing@example.org')             // read-only billing contact
        ->assertSee('https://billing.stripe.com/p/session/test_123', false) // portal link
        ->assertSee('Manage billing')                  // portal button label
        ->assertSee('Billing information as of')        // staleness footer
        ->assertSee('3 hours ago')                     // relative timestamp (time frozen)
        ->assertSee('up to a day to appear');          // freshness note
});

it('names Stripe only as the hosted-portal hand-off label', function () {
    seedBillingDoc();

    // The one allowed "Stripe" mention on the page — the portal is Stripe-hosted.
    $this->actingAs(billingAdmin())
        ->get('/admin/account-page')
        ->assertOk()
        ->assertSee('Stripe');
});

// ── Status badge variants ────────────────────────────────────────────────────

it('shows the Active badge for an active subscription', function () {
    seedBillingDoc(['status' => 'active']);

    $this->actingAs(billingAdmin())->get('/admin/account-page')
        ->assertOk()->assertSee('Active');
});

it('shows a plain-English payment-problem badge for past_due', function () {
    seedBillingDoc(['status' => 'past_due']);

    $this->actingAs(billingAdmin())->get('/admin/account-page')
        ->assertOk()->assertSee('Payment problem — please update your card');
});

it('shows a trial countdown badge for a trialing subscription', function () {
    seedBillingDoc([
        'status' => 'trialing',
        'trial'  => ['ends_at' => '2026-07-18T12:00:00+00:00'], // 10 days after frozen now
    ]);

    $this->actingAs(billingAdmin())->get('/admin/account-page')
        ->assertOk()->assertSee('Trial — 10 days left');
});

it('shows the Canceled badge for a canceled subscription', function () {
    seedBillingDoc(['status' => 'canceled']);

    $this->actingAs(billingAdmin())->get('/admin/account-page')
        ->assertOk()->assertSee('Canceled');
});

// ── Self-hide when no document is present ─────────────────────────────────────

it('canAccess is false (and page 403s) when no billing-state document exists', function () {
    Storage::fake('local'); // no document
    $user = billingAdmin();

    $this->actingAs($user);

    expect(AccountPage::canAccess())->toBeFalse()
        ->and(AccountPage::shouldRegisterNavigation())->toBeFalse();

    $this->get('/admin/account-page')->assertStatus(403);
});

it('canAccess is true and the page is reachable once a document is present', function () {
    seedBillingDoc();
    $user = billingAdmin();

    $this->actingAs($user);

    expect(AccountPage::canAccess())->toBeTrue()
        ->and(AccountPage::shouldRegisterNavigation())->toBeTrue();
});

// ── On-page prominent banner (past-due / grace) ──────────────────────────────

it('shows the prominent pre-lock banner with the lock date under past_due + grace', function () {
    seedBillingDoc([
        'status'     => 'past_due',
        'suspension' => ['grace_ends' => '2026-07-22T00:00:00+00:00'],
    ]);

    $this->actingAs(billingAdmin())
        ->get('/admin/account-page')
        ->assertOk()
        ->assertSee('A recent payment didn’t go through', false)
        ->assertSee('July 22, 2026')                    // lock date (LONG_DATE)
        ->assertSee('Update your payment in the billing portal', false);
});

it('shows no prominent banner when the subscription is active', function () {
    seedBillingDoc(['status' => 'active']);

    $this->actingAs(billingAdmin())
        ->get('/admin/account-page')
        ->assertOk()
        ->assertDontSee('A recent payment didn’t go through', false);
});

// ── Slim panel-wide banner (render hook on every admin page) ──────────────────

it('injects the slim panel-wide banner on other admin pages when past-due', function () {
    seedBillingDoc(['status' => 'past_due', 'suspension' => ['grace_ends' => '2026-07-22T00:00:00+00:00']]);

    $this->actingAs(billingAdmin())
        ->get('/admin')
        ->assertOk()
        ->assertSee('A billing payment needs attention', false)
        ->assertSee('Review account', false);
});

it('shows no panel-wide banner when the subscription is active', function () {
    seedBillingDoc(['status' => 'active']);

    $this->actingAs(billingAdmin())
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('A billing payment needs attention', false);
});

it('shows no panel-wide banner when no billing-state document exists', function () {
    Storage::fake('local'); // no document

    $this->actingAs(billingAdmin())
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('A billing payment needs attention', false);
});

it('suppresses the slim banner on the Account page itself (prominent banner already there)', function () {
    seedBillingDoc(['status' => 'past_due', 'suspension' => ['grace_ends' => '2026-07-22T00:00:00+00:00']]);

    $this->actingAs(billingAdmin())
        ->get('/admin/account-page')
        ->assertOk()
        ->assertSee('A recent payment didn’t go through', false)   // prominent, present
        ->assertDontSee('A billing payment needs attention', false); // slim, suppressed
});
