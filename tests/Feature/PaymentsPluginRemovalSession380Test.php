<?php

use App\Models\Contact;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Membership;
use App\Models\MembershipTier;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\SiteSetting;
use App\Payments\Contracts\CheckoutProvider;
use App\Payments\Contracts\PaymentModeProvider;
use App\Payments\PaymentMode;
use App\Plugins\CapabilityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Plugins\PaymentsRemovedTestCase;

uses(PaymentsRemovedTestCase::class, RefreshDatabase::class);

// Session 380 (Plugin Architecture arc P4): the remove-the-line mirror for the
// Payments foundation plugin. The application boots with the Payments line
// stripped from config('plugins'); everything the enabled twin
// (PaymentsPluginSession380Test) asserts present must be absent, every
// checkout endpoint must degrade to its existing not-configured response, and
// the free/$0 paths — what "Events without Payments" degrades to — must work
// untouched.

it('does not load the Payments provider or its capability', function () {
    expect(config('plugins'))->not->toContain(\Plugins\Payments\PaymentsServiceProvider::class)
        ->and(app()->providerIsLoaded(\Plugins\Payments\PaymentsServiceProvider::class))->toBeFalse();

    $registry = app(CapabilityRegistry::class);
    expect($registry->present('payments'))->toBeFalse()
        ->and($registry->enabled('payments'))->toBeFalse();
});

it('binds neither core payments contract', function () {
    expect(app()->bound(CheckoutProvider::class))->toBeFalse()
        ->and(app()->bound(PaymentModeProvider::class))->toBeFalse();
});

it('never reports live mode, even with a live key in the settings row', function () {
    SiteSetting::set('stripe_secret_key', 'sk_live_abc123');

    expect(PaymentMode::isLive())->toBeFalse();
});

it('leaves services.stripe.* config unpopulated', function () {
    expect(config('services.stripe.secret'))->toBeNull()
        ->and(config('services.stripe.webhook_secret'))->toBeNull()
        ->and(config('services.stripe.publishable_key'))->toBeNull();
});

it('drops the webhook route', function () {
    expect(Route::has('webhooks.stripe'))->toBeFalse();

    // No POST endpoint exists at the URI any more. The page catch-all
    // (GET /{slug}) still claims the path for GET, so the framework answers
    // 405 method-not-allowed rather than a bare 404 — either way the webhook
    // controller is unreachable.
    $this->postJson('/webhooks/stripe', ['type' => 'checkout.session.completed'])
        ->assertStatus(405);
});

it('degrades donation checkout to the existing not-configured response', function () {
    SiteSetting::set('stripe_secret_key', 'sk_test_abc123');

    $this->postJson(route('donations.checkout'), [
        'amount' => 50,
        'type'   => 'one_off',
    ])->assertStatus(422)
        ->assertJson(['error' => 'Payment processing is not configured.']);
});

it('degrades product checkout to the existing not-configured response', function () {
    $product = Product::factory()->create();
    $price   = ProductPrice::factory()->create(['product_id' => $product->id, 'amount' => 50.00]);

    $this->post(route('products.checkout'), ['product_price_id' => $price->id])
        ->assertSessionHasErrors('checkout');
});

it('degrades membership checkout to the existing not-configured response', function () {
    $tier = MembershipTier::factory()->create(['default_price' => 50.00, 'is_active' => true]);

    $this->post(route('membership.checkout'), [
        'tier_id'               => $tier->id,
        'first_name'            => 'Test',
        'last_name'             => 'User',
        'email'                 => 'member@example.com',
        'password'              => 'securepassword1',
        'password_confirmation' => 'securepassword1',
    ])->assertSessionHasErrors('checkout');
});

it('degrades paid event registration to the existing not-configured response', function () {
    $event = Event::factory()->paid(25.00)->create();
    $tier  = $event->ticketTiers()->first();

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Test User',
        'email'       => 'test@example.com',
        'quantities'  => [$tier->id => 1],
        '_form_start' => time() - 10,
    ])->assertSessionHasErrors('register');
});

it('leaves the Stripe price observer unregistered and ProductPrice writes inert', function () {
    expect(app('events')->hasListeners('eloquent.saved: ' . ProductPrice::class))->toBeFalse();

    config(['services.stripe.secret' => 'sk_test_fake']);

    $product = Product::factory()->create();
    $price   = ProductPrice::factory()->create(['product_id' => $product->id, 'amount' => 25.00]);
    $price->update(['amount' => 30.00]);

    expect($price->fresh()->stripe_price_id)->toBeNull();
});

it('hides Stripe on the dashboard integration widget', function () {
    SiteSetting::set('stripe_secret_key', 'sk_test_abc123');

    expect((new \App\Filament\Widgets\DashboardIntegrationStatusWidget())->getIntegrations())
        ->not->toHaveKey('Stripe');
});

// ── The degradation proof: the free/$0 paths work untouched ──────────────────

it('registers a tier-less free event registration end to end', function () {
    $event = Event::factory()->create(['status' => 'published']);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Free User',
        'email'       => 'free@example.com',
        '_form_start' => time() - 10,
    ])->assertRedirect();

    $registration = EventRegistration::where('email', 'free@example.com')->first();
    expect($registration)->not->toBeNull()
        ->and($registration->status)->toBe('registered');
});

it('registers a $0 multi-tier order through the free path', function () {
    $event = Event::factory()->create(['status' => 'published']);
    $a     = \App\Models\TicketTier::factory()->for($event)->create(['name' => 'A', 'price' => 0, 'sort_order' => 0]);
    $b     = \App\Models\TicketTier::factory()->for($event)->create(['name' => 'B', 'price' => 0, 'sort_order' => 1]);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Jane',
        'email'       => 'jane@example.com',
        'quantities'  => [$a->id => 1, $b->id => 2],
        '_form_start' => time() - 10,
    ])->assertRedirect();

    $rows = EventRegistration::where('email', 'jane@example.com')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('status')->unique()->all())->toEqual(['registered'])
        ->and($rows->pluck('stripe_session_id')->unique()->all())->toEqual([null]);
});

it('creates a complimentary membership through the signup path', function () {
    $tier = MembershipTier::factory()->create([
        'default_price'    => null,
        'billing_interval' => 'lifetime',
        'is_active'        => true,
    ]);

    $this->post(route('portal.signup.post'), [
        'first_name'            => 'Free',
        'last_name'             => 'Member',
        'email'                 => 'freemember@example.com',
        'password'              => 'securepassword1',
        'password_confirmation' => 'securepassword1',
        'tier_id'               => $tier->id,
    ])->assertRedirect(route('portal.verification.notice'));

    $contact = Contact::where('email', 'freemember@example.com')->first();
    expect($contact)->not->toBeNull();

    $membership = Membership::where('contact_id', $contact->id)->first();
    expect($membership)->not->toBeNull()
        ->and($membership->status)->toBe('active');
});
