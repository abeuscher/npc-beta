<?php

use App\Models\Contact;
use App\Models\Membership;
use App\Models\MembershipTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Free membership signup ────────────────────────────────────────────────────
// Lifted out of core's PaidCheckoutTest at the session-394 carve (the rule-5
// block-split precedent): the subject is the plugin's SignupController; the
// membership rows are fixture data reached through core models (legal —
// models are core, plan § 6.7). The signup-seam memberships gates travel
// with the controller (session 393): on a memberships-absent composition the
// tier handling self-skips, proven by the Memberships plugin's own suite.

it('free membership signup still works through existing path', function () {
    $tier = MembershipTier::factory()->create([
        'default_price'    => null,
        'billing_interval' => 'lifetime',
        'is_active'        => true,
    ]);

    $response = $this->post(route('portal.signup.post'), [
        'first_name'            => 'Free',
        'last_name'             => 'Member',
        'email'                 => 'freemember@example.com',
        'password'              => 'securepassword1',
        'password_confirmation' => 'securepassword1',
        'tier_id'               => $tier->id,
    ]);

    $response->assertRedirect(route('portal.verification.notice'));

    $contact = Contact::where('email', 'freemember@example.com')->first();
    expect($contact)->not->toBeNull();

    $membership = Membership::where('contact_id', $contact->id)->first();
    expect($membership)->not->toBeNull()
        ->and($membership->status)->toBe('active')
        ->and($membership->tier_id)->toBe($tier->id);
});

it('signup without tier creates no membership', function () {
    $response = $this->post(route('portal.signup.post'), [
        'first_name'            => 'No',
        'last_name'             => 'Tier',
        'email'                 => 'notier@example.com',
        'password'              => 'securepassword1',
        'password_confirmation' => 'securepassword1',
    ]);

    $response->assertRedirect(route('portal.verification.notice'));

    $contact = Contact::where('email', 'notier@example.com')->first();
    expect($contact)->not->toBeNull();
    expect(Membership::where('contact_id', $contact->id)->count())->toBe(0);
});
