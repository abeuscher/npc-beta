<?php

namespace Plugins\Donations\Listeners;

use App\Models\Contact;
use App\Models\Donation;
use App\Models\Transaction;
use App\Payments\Events\CheckoutSettled;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Plugins\Donations\Mail\DonationAcknowledgment;

/**
 * The inbound half of the Donations↔Payments seam (docs/plugin-contract.md
 * surface 10, donations slice — session 389). Owns what the Payments
 * webhook's donation_id branch did before the inversion: promote the pending
 * donation to active by its metadata donation_id, record one Transaction for
 * the session total, and queue the per-gift tax acknowledgment. Idempotent by
 * two independent guards carried over verbatim: the payment_intent
 * Transaction check early-returns on a webhook replay, and
 * donations.acknowledged_at is the explicit per-gift marker so the same gift
 * is never emailed twice even if the transaction guard is ever bypassed.
 *
 * CheckoutSettled is dispatched for every extracted vertical's branch; the
 * metadata key routes it — a session without donation_id belongs to another
 * vertical and this listener no-ops before any read.
 */
class PromotePaidDonations
{
    public function handle(CheckoutSettled $event): void
    {
        if (empty($event->metadata->donation_id)) {
            return;
        }

        $session  = $event->session;
        $intentId = $session->payment_intent;

        if ($intentId && Transaction::where('stripe_id', $intentId)->exists()) {
            return;
        }

        $donationId = $event->metadata->donation_id;
        $donation   = Donation::find($donationId);

        if (! $donation) {
            Log::warning('Stripe donation checkout: donation not found', ['donation_id' => $donationId]);
            return;
        }

        $contact     = $this->findOrCreateContact($session->customer_details ?? null);
        $amountTotal = $session->amount_total ?? 0;

        $donation->update([
            'contact_id'             => $contact?->id,
            'stripe_customer_id'     => $session->customer ?? null,
            'stripe_subscription_id' => $session->subscription ?? null,
            'status'                 => 'active',
            'started_at'             => now(),
        ]);

        $transaction = Transaction::recordStripe([
            'subject_type' => Donation::class,
            'subject_id'   => $donation->id,
            'contact_id'   => $contact?->id,
            'amount'       => $amountTotal / 100,
            'stripe_id'    => $intentId,
        ]);

        $this->sendDonationAcknowledgment($donation, $contact, $amountTotal, $transaction);
    }

    /**
     * Queue the automatic per-gift tax acknowledgment for a completed donation.
     *
     * Scope: the initial gift (checkout.session.completed). Recurring renewals
     * (invoice.payment_succeeded) are a separate per-charge concern — one
     * recurring donation row spans many charges, so acknowledged_at is the wrong
     * grain for them — and are a deliberate fast-follow (session 373 / C3b).
     * Queued (not sent) so a mail-provider hiccup cannot fail the webhook,
     * which must return 200 fast.
     */
    private function sendDonationAcknowledgment(
        Donation $donation,
        ?Contact $contact,
        int $amountTotal,
        Transaction $transaction,
    ): void {
        if ($donation->acknowledged_at !== null) {
            return;
        }

        if (! $contact || ! $contact->email) {
            return;
        }

        $donation->loadMissing('fund');

        Mail::to($contact->email)->queue(new DonationAcknowledgment(
            contact:      $contact,
            amount:       number_format($amountTotal / 100, 2),
            donationDate: now()->format('F j, Y'),
            reference:    $transaction->id,
            fundName:     $donation->fund?->name,
        ));

        $donation->update(['acknowledged_at' => now()]);
    }

    private function findOrCreateContact(?object $customerDetails): ?Contact
    {
        $email = $customerDetails->email ?? null;
        $name  = $customerDetails->name ?? null;

        if (! $email) {
            return null;
        }

        $contact = Contact::where('email', $email)->first();

        if (! $contact) {
            $nameParts = explode(' ', trim($name ?? ''), 2);
            $contact   = Contact::create([
                'first_name' => $nameParts[0] ?? '',
                'last_name'  => $nameParts[1] ?? '',
                'email'      => $email,
                'source'     => 'web_form',
            ]);
        }

        return $contact;
    }
}
