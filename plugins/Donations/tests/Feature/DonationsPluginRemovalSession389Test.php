<?php

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
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Plugins\DonationsRemovedTestCase;

uses(DonationsRemovedTestCase::class, RefreshDatabase::class);

// Session 389 (Plugin Architecture arc D3): the remove-the-line mirror for
// the Donations vertical. The application boots with the Donations line
// stripped from config('plugins'); the whole donations surface must vanish —
// checkout route, admin pages, importer, widgets, email-template handles,
// webhook fulfillment — while the DATA is kept (schema stays core this
// session) and every other surface runs untouched.

it('does not load the Donations provider', function () {
    expect(config('plugins'))->not->toContain(\Plugins\Donations\DonationsServiceProvider::class)
        ->and(app()->providerIsLoaded(\Plugins\Donations\DonationsServiceProvider::class))->toBeFalse();
});

it('drops the donation checkout route', function () {
    expect(Route::has('donations.checkout'))->toBeFalse();
});

it('answers 405 on the checkout POST path — the page catch-all owns GET on every path', function () {
    $this->post('/donate/checkout', ['amount' => 25, 'type' => 'one_off'])
        ->assertStatus(405);
});

it('drops the admin donation surface entirely', function () {
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
        expect(Route::has($name))->toBeFalse("route {$name} should be gone");
    }
});

it('leaves both models unobserved and unpoliced — writes fire no plugin hooks', function () {
    expect(app('events')->hasListeners('eloquent.updated: ' . Donation::class))->toBeFalse()
        ->and(app('events')->hasListeners('eloquent.created: ' . DonationCredit::class))->toBeFalse()
        ->and(\Illuminate\Support\Facades\Gate::getPolicyFor(Donation::class))->toBeNull()
        ->and(\Illuminate\Support\Facades\Gate::getPolicyFor(Fund::class))->toBeNull();
});

it('withdraws the importer contribution from every core surface', function () {
    expect(app(ImporterRegistry::class)->find('donations'))->toBeNull()
        ->and(collect((new \App\Filament\Pages\ImporterPage())->getPluginImporters())->pluck('slug'))
            ->not->toContain('donations');

    expect(fn () => CsvTemplateService::headersFor('donations'))
        ->toThrow(InvalidArgumentException::class);
});

it('skips the demo donations import source when seeding', function () {
    $this->artisan('seed:fake-import-sources')->assertSuccessful();

    expect(\App\Models\ImportSource::where('name', 'Demo Fake Data — Donations')->exists())->toBeFalse();
});

it('drops both donation widget rows on the next widgets:sync', function () {
    $this->artisan('widgets:sync')->assertSuccessful();

    $handles = \App\Models\WidgetType::pluck('handle');

    expect($handles)->not->toContain('donation_form')
        ->and($handles)->not->toContain('recent_donations');
});

it('withdraws both email-template handles from the registry', function () {
    $registry = app(EmailTemplateRegistry::class);

    expect($registry->defaultsFor('donation_receipt'))->toBeNull()
        ->and($registry->defaultsFor('donation_acknowledgment'))->toBeNull();

    // forHandle() falls back to the empty template — no plugin defaults.
    expect(EmailTemplate::forHandle('donation_receipt')->subject)->toBe('');
});

it('keeps donation data queryable — vanish is never destructive', function () {
    $fund     = Fund::factory()->create();
    $donation = Donation::factory()->create(['fund_id' => $fund->id]);

    expect(Donation::count())->toBe(1)
        ->and(Fund::count())->toBe(1)
        ->and($donation->fresh()->fund_id)->toBe($fund->id);
});

it('leaves a late donation webhook dispatch with no listener: the donation stays pending, data kept', function () {
    $donation = Donation::factory()->create(['status' => 'pending', 'contact_id' => null]);

    event(new CheckoutSettled((object) [
        'id'               => 'cs_don_late_1',
        'payment_intent'   => 'pi_don_late_1',
        'amount_total'     => 2500,
        'customer_details' => (object) ['email' => 'late-donor@example.com'],
    ], (object) ['donation_id' => $donation->id]));

    expect($donation->fresh()->status)->toBe('pending')
        ->and(Transaction::where('stripe_id', 'pi_don_late_1')->exists())->toBeFalse();
});

it('leaves late invoice dispatches with no listener: no renewal transaction, no past_due flip', function () {
    $donation = Donation::factory()->create([
        'status'                 => 'active',
        'type'                   => 'recurring',
        'stripe_subscription_id' => 'sub_late_1',
    ]);

    event(new InvoiceSettled((object) [
        'id'           => 'in_late_1',
        'subscription' => 'sub_late_1',
        'amount_paid'  => 2500,
    ]));
    event(new InvoicePaymentFailed((object) [
        'id'           => 'in_late_fail_1',
        'subscription' => 'sub_late_1',
        'amount_due'   => 2500,
    ]));

    expect($donation->fresh()->status)->toBe('active')
        ->and(Transaction::where('stripe_id', 'in_late_1')->exists())->toBeFalse()
        ->and(Transaction::where('stripe_id', 'in_late_fail_1')->exists())->toBeFalse();
});

// ── The other surfaces run untouched ─────────────────────────────────────────

it('leaves the Payments and Events plugins loaded with their surfaces intact', function () {
    expect(app()->providerIsLoaded(\Plugins\Payments\PaymentsServiceProvider::class))->toBeTrue()
        ->and(Route::has('webhooks.stripe'))->toBeTrue()
        ->and(app()->providerIsLoaded(\Plugins\Events\EventsServiceProvider::class))->toBeTrue()
        ->and(Route::has('events.register'))->toBeTrue();
});

it('streams the core CSV templates unaffected', function () {
    expect(CsvTemplateService::headersFor('contacts'))->not->toBeEmpty()
        ->and(CsvTemplateService::headersFor('memberships'))->not->toBeEmpty();
});
