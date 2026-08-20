<?php

use App\Models\Membership;
use App\Models\MembershipTier;
use App\Models\Transaction;
use App\Payments\Events\CheckoutSettled;
use App\Payments\Events\InvoiceSettled;
use App\Plugins\ImporterRegistry;
use App\Services\Import\CsvTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Plugins\MembershipsRemovedTestCase;

uses(MembershipsRemovedTestCase::class, RefreshDatabase::class);

// Session 392 (Plugin Architecture arc D4): the remove-the-line mirror for
// the Memberships vertical. The application boots with the Memberships line
// stripped from config('plugins'); the whole membership surface must vanish —
// checkout route, admin pages, importer, widgets, webhook fulfillment — while
// the DATA is kept (the two membership tables are core-dump tables until the
// package phase, so no fixture migrator path is needed) and every other
// surface runs untouched.

it('does not load the Memberships provider', function () {
    expect(config('plugins'))->not->toContain(\Plugins\Memberships\MembershipsServiceProvider::class)
        ->and(app()->providerIsLoaded(\Plugins\Memberships\MembershipsServiceProvider::class))->toBeFalse();
});

it('drops the membership checkout route', function () {
    expect(Route::has('membership.checkout'))->toBeFalse();
});

it('answers 405 on the checkout POST path — the page catch-all owns GET on every path', function () {
    $this->post('/membership/checkout', ['tier_id' => 'x'])
        ->assertStatus(405);
});

it('drops the admin membership surface entirely', function () {
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
        expect(Route::has($name))->toBeFalse("route {$name} should be gone");
    }
});

it('leaves the model unobserved and unpoliced — writes fire no plugin hooks', function () {
    expect(app('events')->hasListeners('eloquent.updated: ' . Membership::class))->toBeFalse()
        ->and(\Illuminate\Support\Facades\Gate::getPolicyFor(Membership::class))->toBeNull();
});

it('withdraws the importer contribution from every core surface', function () {
    expect(app(ImporterRegistry::class)->find('memberships'))->toBeNull()
        ->and(collect((new \App\Filament\Pages\ImporterPage())->getPluginImporters())->pluck('slug'))
            ->not->toContain('memberships');

    expect(fn () => CsvTemplateService::headersFor('memberships'))
        ->toThrow(InvalidArgumentException::class);
});

it('skips the demo memberships import source when seeding', function () {
    $this->artisan('seed:fake-import-sources')->assertSuccessful();

    expect(\App\Models\ImportSource::where('name', 'Demo Fake Data — Memberships')->exists())->toBeFalse();
});

it('drops both membership widget rows on the next widgets:sync', function () {
    $this->artisan('widgets:sync')->assertSuccessful();

    $handles = \App\Models\WidgetType::pluck('handle');

    expect($handles)->not->toContain('membership_status')
        ->and($handles)->not->toContain('pricing_chart');
});

it('keeps membership data queryable — vanish is never destructive', function () {
    $tier       = MembershipTier::factory()->create();
    $membership = Membership::factory()->create(['tier_id' => $tier->id]);

    expect(Membership::count())->toBe(1)
        ->and(MembershipTier::count())->toBe(1)
        ->and($membership->fresh()->tier_id)->toBe($tier->id);
});

it('leaves a late membership webhook dispatch with no listener: the membership stays pending, data kept', function () {
    $membership = Membership::factory()->create(['status' => 'pending']);

    event(new CheckoutSettled((object) [
        'id'             => 'cs_mem_late_1',
        'payment_intent' => 'pi_mem_late_1',
        'amount_total'   => 10000,
        'subscription'   => null,
    ], (object) ['membership_id' => $membership->id]));

    expect($membership->fresh()->status)->toBe('pending')
        ->and(Transaction::where('stripe_id', 'pi_mem_late_1')->exists())->toBeFalse();
});

it('leaves a late renewal invoice dispatch with no listener: no renewal transaction, period unchanged', function () {
    $membership = Membership::factory()->create([
        'status'                 => 'active',
        'expires_on'             => now()->toDateString(),
        'stripe_subscription_id' => 'sub_mem_late_1',
    ]);

    event(new InvoiceSettled((object) [
        'id'           => 'in_mem_late_1',
        'subscription' => 'sub_mem_late_1',
        'amount_paid'  => 1000,
    ]));

    expect($membership->fresh()->expires_on->format('Y-m-d'))->toBe(now()->toDateString())
        ->and(Transaction::where('stripe_id', 'in_mem_late_1')->exists())->toBeFalse();
});

// ── The other surfaces run untouched ─────────────────────────────────────────

it('leaves the Payments, Events, and Donations plugins loaded with their surfaces intact', function () {
    expect(app()->providerIsLoaded(\Plugins\Payments\PaymentsServiceProvider::class))->toBeTrue()
        ->and(Route::has('webhooks.stripe'))->toBeTrue()
        ->and(app()->providerIsLoaded(\Plugins\Events\EventsServiceProvider::class))->toBeTrue()
        ->and(Route::has('events.register'))->toBeTrue()
        ->and(app()->providerIsLoaded(\Plugins\Donations\DonationsServiceProvider::class))->toBeTrue()
        ->and(Route::has('donations.checkout'))->toBeTrue();
});

it('streams the core CSV templates unaffected', function () {
    expect(CsvTemplateService::headersFor('contacts'))->not->toBeEmpty()
        ->and(CsvTemplateService::headersFor('notes'))->not->toBeEmpty();
});
