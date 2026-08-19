<?php

use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\Donation;
use App\Models\DonationCredit;
use App\Models\EmailTemplate;
use App\Models\Fund;
use App\Models\Transaction;
use App\Payments\Events\CheckoutSettled;
use App\Payments\Events\InvoicePaymentFailed;
use App\Payments\Events\InvoiceSettled;
use App\Plugins\EmailTemplateRegistry;
use App\Plugins\ImporterRegistry;
use App\Services\Import\CsvTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Plugins\Donations\Mail\DonationAcknowledgment;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Session 389 (Plugin Architecture arc D3): the enabled twin for the carved
// Donations vertical. The shipped config/plugins.php carries the plugin, so
// the plain TestCase boots it; DonationsPluginRemovalSession389Test is the
// remove-the-line mirror.

it('ships the Donations provider in the plugin list and loads it', function () {
    expect(config('plugins'))->toContain(\Plugins\Donations\DonationsServiceProvider::class)
        ->and(app()->providerIsLoaded(\Plugins\Donations\DonationsServiceProvider::class))->toBeTrue();
});

it('registers the donation checkout route under its established name', function () {
    expect(Route::has('donations.checkout'))->toBeTrue();

    $route = Route::getRoutes()->getByName('donations.checkout');
    expect($route->uri())->toBe('donate/checkout')
        ->and($route->methods())->toContain('POST');
});

it('registers both observers from the provider', function () {
    expect(app('events')->hasListeners('eloquent.updated: ' . Donation::class))->toBeTrue()
        ->and(app('events')->hasListeners('eloquent.created: ' . DonationCredit::class))->toBeTrue();
});

it('registers the three policies via the gate', function () {
    expect(Gate::getPolicyFor(Donation::class))
        ->toBeInstanceOf(\Plugins\Donations\Policies\DonationPolicy::class)
        ->and(Gate::getPolicyFor(DonationCredit::class))
        ->toBeInstanceOf(\Plugins\Donations\Policies\DonationCreditPolicy::class)
        ->and(Gate::getPolicyFor(Fund::class))
        ->toBeInstanceOf(\Plugins\Donations\Policies\FundPolicy::class);
});

it('registers both donation widgets in the registry with their established handles', function () {
    $handles = collect(app(\App\Services\WidgetRegistry::class)->all())
        ->map(fn ($def) => $def->handle());

    expect($handles)->toContain('donation_form')
        ->and($handles)->toContain('recent_donations');
});

it('discovers the admin donation surface from the plugin at its established routes', function () {
    foreach ([
        'filament.admin.resources.donations.index',
        'filament.admin.resources.donations.view',
        'filament.admin.resources.funds.index',
        'filament.admin.resources.funds.create',
        'filament.admin.resources.funds.edit',
        'filament.admin.pages.donors-page',
        'filament.admin.pages.import-donations-page',
        'filament.admin.pages.import-donations-progress-page',
    ] as $name) {
        expect(Route::has($name))->toBeTrue("missing route {$name}");
    }
});

// ── The importer socket (contract surface 8) ─────────────────────────────────

it('contributes the donations importer through the registry', function () {
    $contribution = app(ImporterRegistry::class)->find('donations');

    expect($contribution)->not->toBeNull()
        ->and($contribution->modelType)->toBe('donation')
        ->and($contribution->pageClass)->toBe(\Plugins\Donations\Filament\Pages\ImportDonationsPage::class)
        ->and($contribution->progressPageClass)->toBe(\Plugins\Donations\Filament\Pages\ImportDonationsProgressPage::class);
});

it('streams the donations CSV template through the socket with its established headers', function () {
    $headers = CsvTemplateService::headersFor('donations');

    expect($headers)->toContain('Donation Amount')
        ->and($headers)->toContain('Email')
        ->and($headers)->toContain('User ID')
        ->and($headers)->toContain('Phone');
});

it('lists the donations importer on the hub page', function () {
    $importers = (new \App\Filament\Pages\ImporterPage())->getPluginImporters();

    expect(collect($importers)->pluck('slug'))->toContain('donations');
});

it('seeds the demo donations import source through the socket', function () {
    $this->artisan('seed:fake-import-sources')->assertSuccessful();

    expect(\App\Models\ImportSource::where('name', 'Demo Fake Data — Donations')->exists())->toBeTrue();
});

// ── The email-template registry (contract surface 7) ─────────────────────────

it('contributes both donation handles to the email-template registry', function () {
    $registry = app(EmailTemplateRegistry::class);

    expect($registry->defaultsFor('donation_receipt'))->not->toBeNull()
        ->and($registry->defaultsFor('donation_acknowledgment'))->not->toBeNull();
});

it('resolves forHandle() defaults for the donation handles through the registry', function () {
    $receipt = EmailTemplate::forHandle('donation_receipt');
    $ack     = EmailTemplate::forHandle('donation_acknowledgment');

    expect($receipt->subject)->toBe('Your {{tax_year}} donation receipt — {{org_name}}')
        ->and($ack->subject)->toBe('Thank you for your donation — {{org_name}}');
});

it('seeds both donation email-template rows from the registry', function () {
    $this->seed();

    expect(EmailTemplate::where('handle', 'donation_receipt')->exists())->toBeTrue()
        ->and(EmailTemplate::where('handle', 'donation_acknowledgment')->exists())->toBeTrue();
})->group('slow');

// ── The inbound inversion (contract surface 10, donations slice) ─────────────

it('wires the CheckoutSettled listener: dispatch promotes the pending donation, records one transaction, queues one acknowledgment', function () {
    Mail::fake();

    $donation = Donation::factory()->create(['status' => 'pending', 'contact_id' => null]);

    event(new CheckoutSettled((object) [
        'id'               => 'cs_don_settle_1',
        'payment_intent'   => 'pi_don_settle_1',
        'amount_total'     => 5000,
        'customer'         => 'cus_don_1',
        'subscription'     => null,
        'customer_details' => (object) ['email' => 'donor@example.com', 'name' => 'Donor Person'],
    ], (object) ['donation_id' => $donation->id]));

    $fresh = $donation->fresh();
    expect($fresh->status)->toBe('active')
        ->and($fresh->contact_id)->not->toBeNull()
        ->and($fresh->acknowledged_at)->not->toBeNull();

    expect(Transaction::where('stripe_id', 'pi_don_settle_1')->count())->toBe(1)
        ->and(Contact::where('email', 'donor@example.com')->exists())->toBeTrue();

    Mail::assertQueued(DonationAcknowledgment::class, 1);
});

it('replays idempotently: a second CheckoutSettled dispatch writes nothing new', function () {
    Mail::fake();

    $donation = Donation::factory()->create(['status' => 'pending', 'contact_id' => null]);

    $payload = (object) [
        'id'               => 'cs_don_settle_2',
        'payment_intent'   => 'pi_don_settle_2',
        'amount_total'     => 2500,
        'customer'         => null,
        'subscription'     => null,
        'customer_details' => (object) ['email' => 'replay-donor@example.com', 'name' => 'Replay Donor'],
    ];
    $metadata = (object) ['donation_id' => $donation->id];

    event(new CheckoutSettled($payload, $metadata));
    event(new CheckoutSettled($payload, $metadata));

    expect(Transaction::where('stripe_id', 'pi_don_settle_2')->count())->toBe(1);
    Mail::assertQueued(DonationAcknowledgment::class, 1);
});

it('no-ops on CheckoutSettled dispatches routed to another vertical', function () {
    Mail::fake();

    event(new CheckoutSettled((object) [
        'id'             => 'cs_other_vertical',
        'payment_intent' => 'pi_other_vertical',
        'amount_total'   => 1000,
    ], (object) ['event_registration_checkout' => '1']));

    expect(Transaction::where('stripe_id', 'pi_other_vertical')->exists())->toBeFalse();
    Mail::assertNothingQueued();
});

it('wires the InvoiceSettled listener: a renewal invoice records one transaction and logs donation_received', function () {
    $contact  = Contact::factory()->create();
    $donation = Donation::factory()->create([
        'status'                 => 'active',
        'contact_id'             => $contact->id,
        'type'                   => 'recurring',
        'stripe_subscription_id' => 'sub_renew_1',
    ]);

    event(new InvoiceSettled((object) [
        'id'             => 'in_renew_1',
        'subscription'   => 'sub_renew_1',
        'amount_paid'    => 2500,
        'billing_reason' => 'subscription_cycle',
    ]));

    expect(Transaction::where('stripe_id', 'in_renew_1')->count())->toBe(1)
        ->and(ActivityLog::where('subject_type', Contact::class)
            ->where('subject_id', $contact->id)
            ->where('event', 'donation_received')
            ->exists())->toBeTrue();

    // Replay: the invoice-id transaction guard holds.
    event(new InvoiceSettled((object) [
        'id'             => 'in_renew_1',
        'subscription'   => 'sub_renew_1',
        'amount_paid'    => 2500,
        'billing_reason' => 'subscription_cycle',
    ]));

    expect(Transaction::where('stripe_id', 'in_renew_1')->count())->toBe(1);
});

it('does not log donation_received on the subscription-creating first invoice', function () {
    $contact  = Contact::factory()->create();
    $donation = Donation::factory()->create([
        'status'                 => 'active',
        'contact_id'             => $contact->id,
        'type'                   => 'recurring',
        'stripe_subscription_id' => 'sub_first_1',
    ]);

    event(new InvoiceSettled((object) [
        'id'             => 'in_first_1',
        'subscription'   => 'sub_first_1',
        'amount_paid'    => 2500,
        'billing_reason' => 'subscription_create',
    ]));

    expect(Transaction::where('stripe_id', 'in_first_1')->count())->toBe(1)
        ->and(ActivityLog::where('subject_type', Contact::class)
            ->where('subject_id', $contact->id)
            ->where('event', 'donation_received')
            ->exists())->toBeFalse();
});

it('no-ops on InvoiceSettled for a subscription that is not a donation', function () {
    event(new InvoiceSettled((object) [
        'id'             => 'in_not_ours',
        'subscription'   => 'sub_not_ours',
        'amount_paid'    => 2500,
        'billing_reason' => 'subscription_cycle',
    ]));

    expect(Transaction::where('stripe_id', 'in_not_ours')->exists())->toBeFalse();
});

it('wires the InvoicePaymentFailed listener: the donation goes past_due with one failed transaction', function () {
    $donation = Donation::factory()->create([
        'status'                 => 'active',
        'type'                   => 'recurring',
        'stripe_subscription_id' => 'sub_fail_1',
    ]);

    event(new InvoicePaymentFailed((object) [
        'id'           => 'in_fail_1',
        'subscription' => 'sub_fail_1',
        'amount_due'   => 2500,
    ]));

    expect($donation->fresh()->status)->toBe('past_due')
        ->and(Transaction::where('stripe_id', 'in_fail_1')->where('status', 'failed')->count())->toBe(1);

    // Replay: the invoice-id transaction guard holds.
    event(new InvoicePaymentFailed((object) [
        'id'           => 'in_fail_1',
        'subscription' => 'sub_fail_1',
        'amount_due'   => 2500,
    ]));

    expect(Transaction::where('stripe_id', 'in_fail_1')->count())->toBe(1);
});

// ── The webhook dispatches (the Payments v0.2.0 side of the seam) ────────────

it('dispatches CheckoutSettled from the webhook donation branch', function () {
    \Illuminate\Support\Facades\Event::fake([CheckoutSettled::class]);

    $controller = new \Plugins\Payments\Http\Controllers\StripeWebhookController();
    $reflection = new ReflectionMethod($controller, 'handleCheckoutSessionCompleted');
    $reflection->setAccessible(true);

    $stripeEvent = \Stripe\Event::constructFrom(['data' => ['object' => [
        'id'             => 'cs_test_don_dispatch',
        'payment_intent' => 'pi_don_dispatch',
        'amount_total'   => 1000,
        'metadata'       => ['donation_id' => 'some-donation-id'],
    ]]]);

    $response = $reflection->invoke($controller, $stripeEvent);

    expect($response->getStatusCode())->toBe(200);
    \Illuminate\Support\Facades\Event::assertDispatched(CheckoutSettled::class);
});

it('dispatches InvoiceSettled from the webhook invoice-succeeded branch for every subscription invoice', function () {
    \Illuminate\Support\Facades\Event::fake([InvoiceSettled::class]);

    $controller = new \Plugins\Payments\Http\Controllers\StripeWebhookController();
    $reflection = new ReflectionMethod($controller, 'handleInvoicePaymentSucceeded');
    $reflection->setAccessible(true);

    $stripeEvent = \Stripe\Event::constructFrom(['data' => ['object' => [
        'id'           => 'in_dispatch_1',
        'subscription' => 'sub_dispatch_1',
        'amount_paid'  => 2500,
    ]]]);

    $response = $reflection->invoke($controller, $stripeEvent);

    expect($response->getStatusCode())->toBe(200);
    \Illuminate\Support\Facades\Event::assertDispatched(InvoiceSettled::class);
});

it('dispatches InvoicePaymentFailed from the webhook invoice-failed branch', function () {
    \Illuminate\Support\Facades\Event::fake([InvoicePaymentFailed::class]);

    $controller = new \Plugins\Payments\Http\Controllers\StripeWebhookController();
    $reflection = new ReflectionMethod($controller, 'handleInvoicePaymentFailed');
    $reflection->setAccessible(true);

    $stripeEvent = \Stripe\Event::constructFrom(['data' => ['object' => [
        'id'           => 'in_dispatch_fail_1',
        'subscription' => 'sub_dispatch_fail_1',
        'amount_due'   => 2500,
    ]]]);

    $response = $reflection->invoke($controller, $stripeEvent);

    expect($response->getStatusCode())->toBe(200);
    \Illuminate\Support\Facades\Event::assertDispatched(InvoicePaymentFailed::class);
});
