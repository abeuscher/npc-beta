<?php

namespace Plugins\Donations\Listeners;

use App\Models\Donation;
use App\Models\Transaction;
use App\Payments\Events\InvoicePaymentFailed;

/**
 * The donation half of the failed-invoice inversion (docs/plugin-contract.md
 * surface 10, donations slice — session 389). Owns what the Payments
 * webhook's invoice.payment_failed branch did before the inversion: mark the
 * recurring donation past_due and record the failed Transaction. Idempotent
 * via the invoice-id Transaction check, verbatim. A subscription that is not
 * a donation's no-ops before any write (only donations ever handled failed
 * invoices — membership failures were and remain unhandled).
 */
class MarkRecurringDonationPastDue
{
    public function handle(InvoicePaymentFailed $event): void
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

        $donation->update(['status' => 'past_due']);

        if (! Transaction::where('stripe_id', $invoice->id)->exists()) {
            Transaction::recordStripe([
                'subject_type' => Donation::class,
                'subject_id'   => $donation->id,
                'contact_id'   => $donation->contact_id,
                'amount'       => ($invoice->amount_due ?? 0) / 100,
                'status'       => 'failed',
                'stripe_id'    => $invoice->id,
            ]);
        }
    }
}
