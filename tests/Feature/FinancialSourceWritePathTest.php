<?php

use App\Models\Contact;
use App\Models\Donation;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Membership;
use App\Models\MembershipTier;
use App\Models\PortalAccount;
use App\Models\Transaction;
use App\WidgetPrimitive\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Free paths (no Stripe) — direct HTTP route assertions ────────────────────

it('free event registration via EventController gets source=human', function () {
    $event = Event::factory()->create(['price' => 0]);

    $this->post(route('events.register', $event->slug), [
        'name'  => 'Free User',
        'email' => 'free@example.com',
    ])->assertRedirect();

    $registration = EventRegistration::where('email', 'free@example.com')->first();

    expect($registration)->not->toBeNull()
        ->and($registration->source)->toBe(Source::HUMAN);
});

it('portal member free event registration via Portal\\EventRegistrationController gets source=human', function () {
    $contact = Contact::factory()->create(['email' => 'portalfree@example.com']);
    $account = PortalAccount::factory()->create(['contact_id' => $contact->id]);
    $event   = Event::factory()->create(['price' => 0]);

    $this->actingAs($account, 'portal')
        ->post(route('portal.events.register', $event->slug))
        ->assertRedirect();

    $registration = EventRegistration::where('contact_id', $contact->id)->first();

    expect($registration)->not->toBeNull()
        ->and($registration->source)->toBe(Source::HUMAN);
});

it('complimentary-tier membership via Portal\\SignupController gets source=human', function () {
    $tier = MembershipTier::factory()->create([
        'default_price'    => null,
        'billing_interval' => 'lifetime',
        'is_active'        => true,
    ]);

    $this->post(route('portal.signup.post'), [
        'first_name'            => 'Free',
        'last_name'             => 'Member',
        'email'                 => 'comp@example.com',
        'password'              => 'securepassword1',
        'password_confirmation' => 'securepassword1',
        'tier_id'               => $tier->id,
    ])->assertRedirect();

    $contact    = Contact::where('email', 'comp@example.com')->first();
    $membership = Membership::where('contact_id', $contact?->id)->first();

    expect($membership)->not->toBeNull()
        ->and($membership->source)->toBe(Source::HUMAN);
});

// ── Stripe paths — capture source via model `creating` event ────────────────
//
// The checkout controllers create the row with `source = stripe_webhook`, then
// pass to Stripe; the Stripe call fails with a fake key and the row is deleted.
// We capture the source field at `creating` time, before the row is purged.

it('paid donation checkout via DonationCheckoutController writes source=stripe_webhook', function () {
    config(['services.stripe.secret' => 'sk_test_fake']);

    $captured = null;
    Donation::creating(function (Donation $d) use (&$captured) {
        $captured = $d->source;
    });

    $this->postJson(route('donations.checkout'), [
        'amount' => 50,
        'type'   => 'one_off',
    ]);

    expect($captured)->toBe(Source::STRIPE_WEBHOOK);
});

it('paid event checkout via EventCheckoutController writes source=stripe_webhook', function () {
    config(['services.stripe.secret' => 'sk_test_fake']);
    $event = Event::factory()->create(['price' => 25]);

    $captured = null;
    EventRegistration::creating(function (EventRegistration $r) use (&$captured) {
        $captured = $r->source;
    });

    $this->post(route('events.checkout', $event->slug), [
        'name'  => 'Paid User',
        'email' => 'paid@example.com',
    ]);

    expect($captured)->toBe(Source::STRIPE_WEBHOOK);
});

it('portal paid event checkout via Portal\\EventCheckoutController writes source=stripe_webhook', function () {
    config(['services.stripe.secret' => 'sk_test_fake']);
    $contact = Contact::factory()->create(['email' => 'portalpaid@example.com']);
    $account = PortalAccount::factory()->create(['contact_id' => $contact->id]);
    $event   = Event::factory()->create(['price' => 25]);

    $captured = null;
    EventRegistration::creating(function (EventRegistration $r) use (&$captured) {
        $captured = $r->source;
    });

    $this->actingAs($account, 'portal')
        ->post(route('portal.events.checkout', $event->slug));

    expect($captured)->toBe(Source::STRIPE_WEBHOOK);
});

it('paid membership checkout via MembershipCheckoutController writes source=stripe_webhook', function () {
    config(['services.stripe.secret' => 'sk_test_fake']);
    $tier = MembershipTier::factory()->create([
        'default_price'    => 100,
        'billing_interval' => 'annual',
        'is_active'        => true,
    ]);

    $captured = null;
    Membership::creating(function (Membership $m) use (&$captured) {
        $captured = $m->source;
    });

    $this->post(route('membership.checkout'), [
        'tier_id'               => $tier->id,
        'first_name'            => 'Paid',
        'last_name'             => 'Member',
        'email'                 => 'paidmember@example.com',
        'password'              => 'securepassword1',
        'password_confirmation' => 'securepassword1',
    ]);

    expect($captured)->toBe(Source::STRIPE_WEBHOOK);
});

// ── Transaction::recordStripe — defaults source to stripe_webhook ───────────

it('Transaction::recordStripe defaults source to stripe_webhook', function () {
    $contact = Contact::factory()->create();

    $transaction = Transaction::recordStripe([
        'subject_type' => null,
        'subject_id'   => null,
        'contact_id'   => $contact->id,
        'amount'       => 10,
        'stripe_id'    => 'pi_test_default',
    ]);

    expect($transaction->source)->toBe(Source::STRIPE_WEBHOOK);
});
