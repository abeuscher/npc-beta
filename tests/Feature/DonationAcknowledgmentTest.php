<?php

use App\Mail\DonationAcknowledgment;
use App\Mail\DonationReceipt as DonationReceiptMail;
use App\Models\Contact;
use App\Models\Donation;
use App\Models\EmailTemplate;
use App\Models\Fund;
use App\Models\SiteSetting;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Build a validly-signed checkout.session.completed body + Stripe-Signature so
 * StripeWebhookController::handle() runs end to end — constructEvent() verifies
 * the HMAC, so an unsigned synthetic body would be rejected with a 400.
 */
function signedDonationWebhook(array $sessionObject): array
{
    $secret  = 'whsec_test';
    $payload = json_encode([
        'id'     => 'evt_'.bin2hex(random_bytes(8)),
        'object' => 'event',
        'type'   => 'checkout.session.completed',
        'data'   => ['object' => $sessionObject],
    ]);

    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

    return [$payload, "t={$timestamp},v1={$signature}"];
}

function donationSession(Donation $donation, string $email, int $amountTotal = 5000, string $intent = 'pi_test_ack'): array
{
    return [
        'id'               => 'cs_test_ack',
        'object'           => 'checkout.session',
        'payment_intent'   => $intent,
        'customer'         => 'cus_test_ack',
        'subscription'     => null,
        'amount_total'     => $amountTotal,
        'customer_details' => ['email' => $email, 'name' => 'Ada Donor'],
        'metadata'         => ['donation_id' => $donation->id],
    ];
}

beforeEach(function () {
    config(['services.stripe.webhook_secret' => 'whsec_test']);
    SiteSetting::firstOrCreate(
        ['key' => 'site_name'],
        ['value' => 'Hope Foundation', 'group' => 'general', 'type' => 'string'],
    );
});

it('dispatches one acknowledgment when a donation checkout completes', function () {
    Mail::fake();

    Contact::factory()->create(['email' => 'donor@example.com']);
    $donation = Donation::factory()->create(['status' => 'pending', 'amount' => 50.00, 'contact_id' => null]);

    [$payload, $sig] = signedDonationWebhook(donationSession($donation, 'donor@example.com'));

    $this->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => $sig,
        'CONTENT_TYPE'          => 'application/json',
    ], $payload)->assertOk();

    Mail::assertQueued(DonationAcknowledgment::class, 1);
    Mail::assertQueued(DonationAcknowledgment::class, fn ($mail) => $mail->hasTo('donor@example.com'));

    expect($donation->fresh()->acknowledged_at)->not->toBeNull();
});

it('does not send a second acknowledgment when the webhook is replayed', function () {
    Mail::fake();

    Contact::factory()->create(['email' => 'donor@example.com']);
    $donation = Donation::factory()->create(['status' => 'pending', 'amount' => 50.00, 'contact_id' => null]);

    [$payload, $sig] = signedDonationWebhook(donationSession($donation, 'donor@example.com'));
    $server = ['HTTP_STRIPE_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'];

    // Same signed event delivered twice — Stripe retries webhooks.
    $this->call('POST', '/webhooks/stripe', [], [], [], $server, $payload)->assertOk();
    $this->call('POST', '/webhooks/stripe', [], [], [], $server, $payload)->assertOk();

    Mail::assertQueued(DonationAcknowledgment::class, 1);
    expect(Transaction::where('subject_id', $donation->id)->count())->toBe(1);
});

it('carries the gift amount, fund, and IRS acknowledgment language', function () {
    Mail::fake();

    $fund = Fund::factory()->create(['name' => 'Scholarship Fund']);
    Contact::factory()->create(['email' => 'donor@example.com']);
    $donation = Donation::factory()->create([
        'status'     => 'pending',
        'amount'     => 50.00,
        'contact_id' => null,
        'fund_id'    => $fund->id,
    ]);

    [$payload, $sig] = signedDonationWebhook(donationSession($donation, 'donor@example.com'));

    $this->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => $sig,
        'CONTENT_TYPE'          => 'application/json',
    ], $payload)->assertOk();

    $transaction = Transaction::where('subject_type', Donation::class)
        ->where('subject_id', $donation->id)
        ->first();

    Mail::assertQueued(DonationAcknowledgment::class, function ($mail) use ($fund, $transaction) {
        $rendered = $mail->render();

        return $mail->amount === '50.00'
            && $mail->fundName === $fund->name
            && $mail->reference === $transaction->id
            && str_contains($rendered, '$50.00')
            && str_contains($rendered, $fund->name)
            && str_contains($rendered, 'No goods or services');
    });
});

it('does not acknowledge a donation whose payer has no email', function () {
    Mail::fake();

    $donation = Donation::factory()->create(['status' => 'pending', 'amount' => 50.00, 'contact_id' => null]);

    $session = donationSession($donation, '');
    $session['customer_details'] = ['name' => 'No Email'];

    [$payload, $sig] = signedDonationWebhook($session);

    $this->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => $sig,
        'CONTENT_TYPE'          => 'application/json',
    ], $payload)->assertOk();

    Mail::assertNotQueued(DonationAcknowledgment::class);
    expect($donation->fresh()->acknowledged_at)->toBeNull();
});

it('leaves the annual year-end donation receipt document intact', function () {
    $contact = Contact::factory()->create();

    // The annual, aggregated statement is a separate document and is untouched.
    $annual = new DonationReceiptMail($contact, 2025, [
        ['fund_label' => 'General Fund', 'restriction_type' => 'unrestricted', 'amount' => 100],
    ], '100.00');

    expect($annual->envelope()->subject)->toContain('2025');

    // Per-gift and annual are distinct, separately-editable templates.
    expect(EmailTemplate::forHandle('donation_receipt')->handle)->toBe('donation_receipt')
        ->and(EmailTemplate::forHandle('donation_acknowledgment')->handle)->toBe('donation_acknowledgment');
});
