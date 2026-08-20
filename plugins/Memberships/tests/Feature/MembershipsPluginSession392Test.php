<?php

use App\Models\Contact;
use App\Models\Membership;
use App\Models\MembershipTier;
use App\Models\Transaction;
use App\Payments\Events\CheckoutSettled;
use App\Payments\Events\InvoicePaymentFailed;
use App\Payments\Events\InvoiceSettled;
use App\Plugins\ImporterRegistry;
use App\Services\Import\CsvTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Session 392 (Plugin Architecture arc D4): the enabled twin for the carved
// Memberships vertical. The shipped config/plugins.php carries the plugin, so
// the plain TestCase boots it; MembershipsPluginRemovalSession392Test is the
// remove-the-line mirror.

it('ships the Memberships provider in the plugin list and loads it', function () {
    expect(config('plugins'))->toContain(\Plugins\Memberships\MembershipsServiceProvider::class)
        ->and(app()->providerIsLoaded(\Plugins\Memberships\MembershipsServiceProvider::class))->toBeTrue();
});

it('registers the membership checkout route under its established name', function () {
    expect(Route::has('membership.checkout'))->toBeTrue();

    $route = Route::getRoutes()->getByName('membership.checkout');
    expect($route->uri())->toBe('membership/checkout')
        ->and($route->methods())->toContain('POST');
});

it('registers the observer from the provider', function () {
    expect(app('events')->hasListeners('eloquent.updated: ' . Membership::class))->toBeTrue();
});

it('registers the membership policy via the gate', function () {
    expect(Gate::getPolicyFor(Membership::class))
        ->toBeInstanceOf(\Plugins\Memberships\Policies\MembershipPolicy::class);
});

it('registers both membership widgets in the registry with their established handles', function () {
    $handles = collect(app(\App\Services\WidgetRegistry::class)->all())
        ->map(fn ($def) => $def->handle());

    expect($handles)->toContain('membership_status')
        ->and($handles)->toContain('pricing_chart');
});

it('discovers the admin membership surface from the plugin at its established routes', function () {
    foreach ([
        'filament.admin.resources.memberships.index',
        'filament.admin.resources.memberships.create',
        'filament.admin.resources.memberships.edit',
        'filament.admin.resources.membership-tiers.index',
        'filament.admin.resources.membership-tiers.create',
        'filament.admin.resources.membership-tiers.edit',
        'filament.admin.resources.members.index',
        'filament.admin.pages.import-memberships-page',
        'filament.admin.pages.import-memberships-progress-page',
    ] as $name) {
        expect(Route::has($name))->toBeTrue("missing route {$name}");
    }
});

// ── The importer socket (contract surface 8) ─────────────────────────────────

it('contributes the memberships importer through the registry', function () {
    $contribution = app(ImporterRegistry::class)->find('memberships');

    expect($contribution)->not->toBeNull()
        ->and($contribution->modelType)->toBe('membership')
        ->and($contribution->pageClass)->toBe(\Plugins\Memberships\Filament\Pages\ImportMembershipsPage::class)
        ->and($contribution->progressPageClass)->toBe(\Plugins\Memberships\Filament\Pages\ImportMembershipsProgressPage::class);
});

it('streams the memberships CSV template through the socket with its established headers', function () {
    $headers = CsvTemplateService::headersFor('memberships');

    expect($headers)->toContain('Membership Level / Tier')
        ->and($headers)->toContain('Email')
        ->and($headers)->toContain('User ID')
        ->and($headers)->toContain('Phone');
});

it('lists the memberships importer on the hub page', function () {
    $importers = (new \App\Filament\Pages\ImporterPage())->getPluginImporters();

    expect(collect($importers)->pluck('slug'))->toContain('memberships');
});

it('seeds the demo memberships import source through the socket', function () {
    $this->artisan('seed:fake-import-sources')->assertSuccessful();

    expect(\App\Models\ImportSource::where('name', 'Demo Fake Data — Memberships')->exists())->toBeTrue();
});

// ── The inbound inversion (contract surface 10, memberships slice) ───────────

it('wires the CheckoutSettled listener: dispatch promotes the pending membership, sets the period, records one transaction', function () {
    $contact = Contact::factory()->create();
    $tier    = MembershipTier::factory()->create([
        'default_price'    => 100.00,
        'billing_interval' => 'annual',
        'is_active'        => true,
    ]);
    $membership = Membership::create([
        'contact_id'  => $contact->id,
        'tier_id'     => $tier->id,
        'status'      => 'pending',
        'amount_paid' => 100.00,
    ]);

    event(new CheckoutSettled((object) [
        'id'             => 'cs_mem_settle_1',
        'payment_intent' => 'pi_mem_settle_1',
        'amount_total'   => 10000,
        'subscription'   => 'sub_mem_settle_1',
    ], (object) ['membership_id' => $membership->id]));

    $fresh = $membership->fresh();
    expect($fresh->status)->toBe('active')
        ->and($fresh->starts_on)->not->toBeNull()
        ->and($fresh->expires_on->format('Y-m-d'))->toBe(now()->addYear()->toDateString())
        ->and($fresh->stripe_subscription_id)->toBe('sub_mem_settle_1');

    expect(Transaction::where('stripe_id', 'pi_mem_settle_1')->count())->toBe(1);
});

it('replays idempotently: a second CheckoutSettled dispatch writes nothing new (the pending-status guard)', function () {
    $contact = Contact::factory()->create();
    $tier    = MembershipTier::factory()->create([
        'default_price'    => 50.00,
        'billing_interval' => 'one_time',
        'is_active'        => true,
    ]);
    $membership = Membership::create([
        'contact_id'  => $contact->id,
        'tier_id'     => $tier->id,
        'status'      => 'pending',
        'amount_paid' => 50.00,
    ]);

    $payload = (object) [
        'id'             => 'cs_mem_settle_2',
        'payment_intent' => 'pi_mem_settle_2',
        'amount_total'   => 5000,
        'subscription'   => null,
    ];
    $metadata = (object) ['membership_id' => $membership->id];

    event(new CheckoutSettled($payload, $metadata));
    event(new CheckoutSettled($payload, $metadata));

    expect(Transaction::where('stripe_id', 'pi_mem_settle_2')->count())->toBe(1)
        ->and($membership->fresh()->status)->toBe('active');
});

it('no-ops on CheckoutSettled dispatches routed to another vertical', function () {
    event(new CheckoutSettled((object) [
        'id'             => 'cs_other_vertical_mem',
        'payment_intent' => 'pi_other_vertical_mem',
        'amount_total'   => 1000,
    ], (object) ['event_registration_checkout' => '1']));

    expect(Transaction::where('subject_type', Membership::class)->exists())->toBeFalse();
});

it('wires the InvoiceSettled listener: a renewal invoice extends the period and records one transaction', function () {
    $contact = Contact::factory()->create();
    $tier    = MembershipTier::factory()->create([
        'default_price'    => 10.00,
        'billing_interval' => 'monthly',
        'is_active'        => true,
    ]);
    $membership = Membership::create([
        'contact_id'             => $contact->id,
        'tier_id'                => $tier->id,
        'status'                 => 'active',
        'amount_paid'            => 10.00,
        'starts_on'              => now()->subMonth()->toDateString(),
        'expires_on'             => now()->toDateString(),
        'stripe_subscription_id' => 'sub_mem_renew_1',
    ]);

    event(new InvoiceSettled((object) [
        'id'           => 'in_mem_renew_1',
        'subscription' => 'sub_mem_renew_1',
        'amount_paid'  => 1000,
    ]));

    expect($membership->fresh()->expires_on->format('Y-m-d'))->toBe(now()->addMonth()->toDateString())
        ->and(Transaction::where('stripe_id', 'in_mem_renew_1')->count())->toBe(1);

    // Replay: the invoice-id transaction guard holds.
    event(new InvoiceSettled((object) [
        'id'           => 'in_mem_renew_1',
        'subscription' => 'sub_mem_renew_1',
        'amount_paid'  => 1000,
    ]));

    expect(Transaction::where('stripe_id', 'in_mem_renew_1')->count())->toBe(1);
});

it('no-ops on InvoiceSettled for a subscription that is not a membership', function () {
    event(new InvoiceSettled((object) [
        'id'           => 'in_not_ours_mem',
        'subscription' => 'sub_not_ours_mem',
        'amount_paid'  => 1000,
    ]));

    expect(Transaction::where('stripe_id', 'in_not_ours_mem')->exists())->toBeFalse();
});

it('leaves membership invoice failures unhandled — preservation cuts both ways', function () {
    $contact = Contact::factory()->create();
    $tier    = MembershipTier::factory()->create([
        'default_price'    => 10.00,
        'billing_interval' => 'monthly',
        'is_active'        => true,
    ]);
    $membership = Membership::create([
        'contact_id'             => $contact->id,
        'tier_id'                => $tier->id,
        'status'                 => 'active',
        'amount_paid'            => 10.00,
        'stripe_subscription_id' => 'sub_mem_fail_1',
    ]);

    event(new InvoicePaymentFailed((object) [
        'id'           => 'in_mem_fail_1',
        'subscription' => 'sub_mem_fail_1',
        'amount_due'   => 1000,
    ]));

    expect($membership->fresh()->status)->toBe('active')
        ->and(Transaction::where('stripe_id', 'in_mem_fail_1')->exists())->toBeFalse();
});

// ── The webhook dispatch (the Payments v0.3.0 side of the seam) ──────────────

it('dispatches CheckoutSettled from the webhook membership branch', function () {
    \Illuminate\Support\Facades\Event::fake([CheckoutSettled::class]);

    $controller = new \Plugins\Payments\Http\Controllers\StripeWebhookController();
    $reflection = new ReflectionMethod($controller, 'handleCheckoutSessionCompleted');
    $reflection->setAccessible(true);

    $stripeEvent = \Stripe\Event::constructFrom(['data' => ['object' => [
        'id'             => 'cs_test_mem_dispatch',
        'payment_intent' => 'pi_mem_dispatch',
        'amount_total'   => 10000,
        'metadata'       => ['membership_id' => 'some-membership-id'],
    ]]]);

    $response = $reflection->invoke($controller, $stripeEvent);

    expect($response->getStatusCode())->toBe(200);
    \Illuminate\Support\Facades\Event::assertDispatched(CheckoutSettled::class);
});
