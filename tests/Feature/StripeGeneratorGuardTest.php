<?php

use App\Models\Contact;
use App\Models\SiteSetting;
use App\Models\User;
use App\Payments\PaymentMode;
use App\WidgetPrimitive\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Live-Stripe guard on the Random Data Generator (session 370, Security S1):
 * synthetic donations/transactions must never be generated on a real-payments
 * install. Generation is refused when a live key is configured; wipe stays open.
 */

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
});

it('PaymentMode detects sk_live_ and rk_live_ keys as live, test keys as not', function () {
    // Through the core seam (App\Payments\PaymentMode → the plugin's
    // StripeMode binding) — the path both RDG consumers use since session 380.
    expect(PaymentMode::isLive())->toBeFalse();

    SiteSetting::set('stripe_secret_key', 'sk_live_abc123');
    expect(PaymentMode::isLive())->toBeTrue();

    SiteSetting::set('stripe_secret_key', 'rk_live_abc123');
    expect(PaymentMode::isLive())->toBeTrue();

    SiteSetting::set('stripe_secret_key', 'sk_test_abc123');
    expect(PaymentMode::isLive())->toBeFalse();

    SiteSetting::set('stripe_secret_key', 'rk_test_abc123');
    expect(PaymentMode::isLive())->toBeFalse();
});

it('refuses to generate synthetic data when a live Stripe key is configured', function () {
    SiteSetting::set('stripe_secret_key', 'sk_live_abc123');

    $res = $this->actingAs($this->admin)->post(route('filament.admin.dev-tools.random-data.store'), [
        'counts' => ['contacts' => 3, 'events' => 0, 'registrations' => 0, 'donations' => 0, 'memberships' => 0],
    ]);

    $res->assertRedirect();
    $res->assertSessionHasErrors('rdg');
    expect(Contact::where('source', Source::SCRUB_DATA)->count())->toBe(0);
});

it('allows generation on a test-mode Stripe key', function () {
    SiteSetting::set('stripe_secret_key', 'sk_test_abc123');

    $res = $this->actingAs($this->admin)->post(route('filament.admin.dev-tools.random-data.store'), [
        'counts' => ['contacts' => 2, 'events' => 0, 'registrations' => 0, 'donations' => 0, 'memberships' => 0],
    ]);

    $res->assertRedirect();
    $res->assertSessionHas('rdg_status');
    expect(Contact::where('source', Source::SCRUB_DATA)->count())->toBe(2);
});

it('allows generation when Stripe is unconfigured (empty key)', function () {
    $res = $this->actingAs($this->admin)->post(route('filament.admin.dev-tools.random-data.store'), [
        'counts' => ['contacts' => 1, 'events' => 0, 'registrations' => 0, 'donations' => 0, 'memberships' => 0],
    ]);

    $res->assertSessionHas('rdg_status');
    expect(Contact::where('source', Source::SCRUB_DATA)->count())->toBe(1);
});

it('keeps the wipe action available even on a live Stripe key', function () {
    SiteSetting::set('stripe_secret_key', 'sk_live_abc123');

    $res = $this->actingAs($this->admin)->post(route('filament.admin.dev-tools.random-data.wipe'));

    $res->assertRedirect();
    $res->assertSessionHas('rdg_status');
    $res->assertSessionHasNoErrors();
});
