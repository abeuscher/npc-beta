<?php

namespace Plugins\Donations\Listeners;

use App\Models\Donation;
use App\Models\Transaction;
use App\Payments\Events\InvoiceSettled;
use App\Services\ActivityLogger;

/**
 * The donation-renewal half of the invoice inversion (docs/plugin-contract.md
 * surface 10, donations slice — session 389). Owns what the Payments
 * webhook's invoice.payment_succeeded donation branch did before the
 * inversion: record one Transaction per paid invoice for a recurring
 * donation, and log the donation_received activity on true renewals (not the
 * subscription-creating first invoice, which the checkout path acknowledges).
 * Idempotent via the invoice-id Transaction check, verbatim. A subscription
 * that is not a donation's no-ops before any write — subscription ids are
 * disjoint across verticals, and the membership branch stays inline in
 * Payments until Memberships extracts (arc D4).
 */
class RecordRecurringDonationPayment
{
    public function handle(InvoiceSettled $event): void
    {
        $invoice        = $event->invoice;
        $subscriptionId = $invoice->subscription ?? null;

        if (! $subscriptionId) {
            return;
        }

        $donation = Donation::where('stripe_subscription_id', $subscriptionId)->first();

        if (! $donation) {
            return;
        }

        if (Transaction::where('stripe_id', $invoice->id)->exists()) {
            return;
        }

        Transaction::recordStripe([
            'subject_type' => Donation::class,
            'subject_id'   => $donation->id,
            'contact_id'   => $donation->contact_id,
            'amount'       => ($invoice->amount_paid ?? 0) / 100,
            'stripe_id'    => $invoice->id,
        ]);

        if (($invoice->billing_reason ?? '') !== 'subscription_create' && $donation->contact_id) {
            $donation->loadMissing('contact');
            ActivityLogger::log($donation->contact, 'donation_received');
        }
    }
}
