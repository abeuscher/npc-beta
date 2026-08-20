<?php

use App\Models\Contact;
use App\Models\Membership;
use App\Models\MembershipTier;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Lifted from core's PaidCheckoutTest at the session-392 carve (rule-5
// membership pass): the subject is the plugin-owned checkout controller and
// promotion-on-settlement behavior. Assertions unchanged. The free membership
// signup tests stay core — their subject is Portal\SignupController.

// ── MembershipCheckoutController — validation ───────────────────────────────

it('rejects membership checkout for complimentary tier', function () {
    $tier = MembershipTier::factory()->create(['default_price' => null, 'is_active' => true]);

    $response = $this->post(route('membership.checkout'), [
        'tier_id'    => $tier->id,
        'first_name' => 'Test',
        'last_name'  => 'User',
        'email'      => 'member@example.com',
        'password'   => 'securepassword1',
        'password_confirmation' => 'securepassword1',
    ]);

    $response->assertSessionHasErrors('tier_id');
});

it('rejects membership checkout for inactive tier', function () {
    $tier = MembershipTier::factory()->create(['default_price' => 50.00, 'is_active' => false]);

    $response = $this->post(route('membership.checkout'), [
        'tier_id'    => $tier->id,
        'first_name' => 'Test',
        'last_name'  => 'User',
        'email'      => 'member@example.com',
        'password'   => 'securepassword1',
        'password_confirmation' => 'securepassword1',
    ]);

    $response->assertSessionHasErrors('tier_id');
});

it('rejects membership checkout when stripe is not configured', function () {
    config(['services.stripe.secret' => null]);
    $tier = MembershipTier::factory()->create(['default_price' => 50.00, 'is_active' => true]);

    $response = $this->post(route('membership.checkout'), [
        'tier_id'    => $tier->id,
        'first_name' => 'Test',
        'last_name'  => 'User',
        'email'      => 'member@example.com',
        'password'   => 'securepassword1',
        'password_confirmation' => 'securepassword1',
    ]);

    $response->assertSessionHasErrors('checkout');
});

it('creates pending membership for paid tier', function () {
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

    expect($membership->status)->toBe('pending')
        ->and($membership->tier_id)->toBe($tier->id)
        ->and($membership->amount_paid)->toBe('100.00');
});

it('uses subscription mode for monthly tier', function () {
    $tier = MembershipTier::factory()->create([
        'default_price'    => 10.00,
        'billing_interval' => 'monthly',
        'is_active'        => true,
    ]);

    expect(in_array($tier->billing_interval, ['monthly', 'annual']))->toBeTrue();
});

it('uses payment mode for one_time tier', function () {
    $tier = MembershipTier::factory()->create([
        'default_price'    => 250.00,
        'billing_interval' => 'one_time',
        'is_active'        => true,
    ]);

    expect(in_array($tier->billing_interval, ['monthly', 'annual']))->toBeFalse();
});

// ── Webhook — membership checkout completed ─────────────────────────────────

it('activates membership and creates transaction on webhook', function () {
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

    // Simulate webhook handler
    $membership->update([
        'status'     => 'active',
        'starts_on'  => now()->toDateString(),
        'expires_on' => now()->addYear()->toDateString(),
        'amount_paid' => 100.00,
    ]);

    $transaction = Transaction::create([
        'subject_type' => Membership::class,
        'subject_id'   => $membership->id,
        'contact_id'   => $contact->id,
        'type'         => 'payment',
        'amount'       => 100.00,
        'direction'    => 'in',
        'status'       => 'completed',
        'stripe_id'    => 'pi_test_member_123',
        'occurred_at'  => now(),
    ]);

    $membership->refresh();
    expect($membership->status)->toBe('active')
        ->and($membership->starts_on)->not->toBeNull()
        ->and($membership->expires_on)->not->toBeNull();
    expect($transaction->subject_type)->toBe(Membership::class)
        ->and($transaction->subject_id)->toBe($membership->id)
        ->and($transaction->amount)->toBe('100.00');
});
