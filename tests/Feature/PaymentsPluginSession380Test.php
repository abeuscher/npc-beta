<?php

use App\Models\ProductPrice;
use App\Payments\Contracts\CheckoutProvider;
use App\Payments\Contracts\PaymentModeProvider;
use App\Payments\PaymentMode;
use App\Plugins\CapabilityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Session 380 (Plugin Architecture arc P4): the enabled twin for the Payments
// foundation plugin. The shipped config/plugins.php carries the plugin, so the
// plain TestCase boots it; PaymentsPluginRemovalSession380Test is the
// remove-the-line mirror.

it('ships the Payments provider in the plugin list and loads it', function () {
    expect(config('plugins'))->toContain(\Plugins\Payments\PaymentsServiceProvider::class)
        ->and(app()->providerIsLoaded(\Plugins\Payments\PaymentsServiceProvider::class))->toBeTrue();
});

it('declares the payments capability: present always, enabled only with a configured secret', function () {
    $registry = app(CapabilityRegistry::class);

    expect($registry->present('payments'))->toBeTrue();

    config(['services.stripe.secret' => '']);
    expect($registry->enabled('payments'))->toBeFalse();

    config(['services.stripe.secret' => 'sk_test_fake']);
    expect($registry->enabled('payments'))->toBeTrue();
});

it('binds the core checkout contract to the plugin implementation', function () {
    expect(app(CheckoutProvider::class))
        ->toBeInstanceOf(\Plugins\Payments\Services\StripeCheckoutService::class);
});

it('binds the core payment-mode contract to the plugin key sniff', function () {
    expect(app()->bound(PaymentModeProvider::class))->toBeTrue()
        ->and(PaymentMode::isLive())->toBeFalse();
});

it('registers the webhook route under its established name', function () {
    expect(Route::has('webhooks.stripe'))->toBeTrue();

    // Reachable (not 404) and signature-guarded: an unsigned body is rejected.
    config(['services.stripe.webhook_secret' => 'whsec_test']);
    $this->postJson('/webhooks/stripe', ['type' => 'checkout.session.completed'])
        ->assertStatus(400);
});

it('registers the Stripe price observer on ProductPrice', function () {
    expect(app('events')->hasListeners('eloquent.saved: ' . ProductPrice::class))->toBeTrue();
});

it('populates services.stripe.* config from the SiteSetting rows at boot', function () {
    // The injection ran during bootstrap (empty-string defaults from a fresh
    // DB) — the keys exist, where the removal mirror shows them null.
    expect(config('services.stripe.secret'))->not->toBeNull()
        ->and(config('services.stripe.webhook_secret'))->not->toBeNull()
        ->and(config('services.stripe.publishable_key'))->not->toBeNull();
});

it('shows Stripe on the dashboard integration widget exactly when payments is enabled', function () {
    $widget = new \App\Filament\Widgets\DashboardIntegrationStatusWidget();

    config(['services.stripe.secret' => '']);
    expect($widget->getIntegrations())->not->toHaveKey('Stripe');

    config(['services.stripe.secret' => 'sk_test_fake']);
    expect($widget->getIntegrations())->toHaveKey('Stripe');
});
