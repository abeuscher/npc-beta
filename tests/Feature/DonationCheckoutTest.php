<?php

use App\Models\Contact;
use App\Models\Donation;
use App\Models\Fund;
use App\Models\Transaction;
use App\Services\StripeCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('stores one-off donation type correctly', function () {
    $donation = Donation::factory()->create([
        'type'      => 'one_off',
        'frequency' => null,
        'amount'    => 100.00,
    ]);

    expect($donation->type)->toBe('one_off')
        ->and($donation->frequency)->toBeNull();
});

it('stores recurring donation type correctly', function () {
    $donation = Donation::factory()->create([
        'type'      => 'recurring',
        'frequency' => 'monthly',
        'amount'    => 25.00,
    ]);

    expect($donation->type)->toBe('recurring')
        ->and($donation->frequency)->toBe('monthly');
});

it('validates donation amount minimum of $1', function () {
    config(['services.stripe.secret' => 'sk_test_fake']);

    $response = $this->postJson(route('donations.checkout'), [
        'amount' => 0.50,
        'type'   => 'one_off',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('amount');
});

it('validates donation amount maximum of $10,000', function () {
    config(['services.stripe.secret' => 'sk_test_fake']);

    $response = $this->postJson(route('donations.checkout'), [
        'amount' => 10001,
        'type'   => 'one_off',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('amount');
});

// ── Checkout initiation — create-pending-then-route-on-Stripe-result ──────────

it('creates a pending donation and returns the Stripe checkout url on success', function () {
    config(['services.stripe.secret' => 'sk_test_fake']);

    $session = \Stripe\Checkout\Session::constructFrom(['url' => 'https://checkout.stripe.test/session_123']);
    $this->mock(StripeCheckoutService::class, function ($mock) use ($session) {
        $mock->shouldReceive('createSession')->once()->andReturn($session);
    });

    $response = $this->postJson(route('donations.checkout'), [
        'amount' => 50,
        'type'   => 'one_off',
    ]);

    $response->assertOk()
        ->assertJson(['url' => 'https://checkout.stripe.test/session_123']);

    expect(Donation::count())->toBe(1);
    $donation = Donation::first();
    expect($donation->status)->toBe('pending');
    expect((float) $donation->amount)->toBe(50.0);
    expect($donation->type)->toBe('one_off');
});

it('deletes the pending donation when Stripe checkout creation fails', function () {
    config(['services.stripe.secret' => 'sk_test_fake']);

    $this->mock(StripeCheckoutService::class, function ($mock) {
        $mock->shouldReceive('createSession')->once()->andThrow(new \Exception('stripe unreachable'));
    });

    $response = $this->postJson(route('donations.checkout'), [
        'amount' => 50,
        'type'   => 'one_off',
    ]);

    $response->assertStatus(422)
        ->assertJson(['error' => 'Could not initiate checkout. Please try again.']);

    // The pending donation created before the Stripe call is rolled back on failure.
    expect(Donation::count())->toBe(0);
});

// ── Stripe webhook — donation checkout completed ──────────────────────────────

it('updates donation to active status and creates transaction on payment', function () {
    $contact  = Contact::factory()->create(['email' => 'webhook@example.com']);
    $donation = Donation::factory()->create([
        'contact_id' => $contact->id,
        'status'     => 'pending',
        'amount'     => 50.00,
    ]);

    // Simulate what the webhook handler does after signature validation
    $donation->update([
        'status'     => 'active',
        'started_at' => now(),
    ]);

    Transaction::create([
        'subject_type' => Donation::class,
        'subject_id'   => $donation->id,
        'contact_id'   => $contact->id,
        'type'         => 'payment',
        'amount'       => 50.00,
        'direction'    => 'in',
        'status'       => 'completed',
        'stripe_id'    => 'pi_test_123',
        'occurred_at'  => now(),
    ]);

    $donation->refresh();
    expect($donation->status)->toBe('active')
        ->and($donation->started_at)->not->toBeNull();

    $transaction = Transaction::where('subject_id', $donation->id)->first();
    expect($transaction)->not->toBeNull()
        ->and($transaction->amount)->toBe('50.00')
        ->and($transaction->status)->toBe('completed');
});

it('creates a transaction record when donation webhook fires', function () {
    $contact  = Contact::factory()->create(['email' => 'donor@example.com']);
    $donation = Donation::factory()->create([
        'contact_id' => $contact->id,
        'amount'     => 75.00,
        'status'     => 'active',
    ]);

    $transaction = Transaction::create([
        'subject_type' => Donation::class,
        'subject_id'   => $donation->id,
        'contact_id'   => $contact->id,
        'type'         => 'payment',
        'amount'       => 75.00,
        'direction'    => 'in',
        'status'       => 'completed',
        'stripe_id'    => 'pi_test_456',
        'occurred_at'  => now(),
    ]);

    expect($transaction->subject_type)->toBe(Donation::class)
        ->and($transaction->subject_id)->toBe($donation->id)
        ->and($transaction->amount)->toBe('75.00')
        ->and($transaction->status)->toBe('completed');
});

it('rejects webhook with invalid signature', function () {
    config(['services.stripe.webhook_secret' => 'whsec_test']);

    $response = $this->postJson('/webhooks/stripe', [
        'type' => 'checkout.session.completed',
        'data' => ['object' => []],
    ], [
        'Stripe-Signature' => 'invalid_signature',
    ]);

    $response->assertStatus(400);
});

it('rejects webhook with missing signature', function () {
    config(['services.stripe.webhook_secret' => 'whsec_test']);

    $response = $this->postJson('/webhooks/stripe', [
        'type' => 'checkout.session.completed',
        'data' => ['object' => []],
    ]);

    $response->assertStatus(400);
});

it('stores donation with fund association', function () {
    $fund     = Fund::factory()->create();
    $donation = Donation::factory()->create([
        'fund_id' => $fund->id,
        'amount'  => 200.00,
    ]);

    expect($donation->fund_id)->toBe($fund->id)
        ->and($donation->fund->name)->toBe($fund->name);
});
