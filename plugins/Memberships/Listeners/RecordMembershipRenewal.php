<?php

namespace Plugins\Memberships\Listeners;

use App\Models\Membership;
use App\Models\Transaction;
use App\Payments\Events\InvoiceSettled;

/**
 * The membership-renewal half of the invoice inversion (docs/plugin-contract.md
 * surface 10, memberships slice — session 392). Owns what the Payments
 * webhook's invoice.payment_succeeded membership branch did before the
 * inversion: extend the membership period by the tier's billing interval and
 * record one Transaction per paid invoice. Idempotent via the invoice-id
 * Transaction check, verbatim. A subscription that is not a membership's
 * no-ops before any write — subscription ids are disjoint across verticals.
 * Membership invoice FAILURES were never handled and remain unhandled
 * (behavior preservation cuts both ways — no InvoicePaymentFailed listener).
 */
class RecordMembershipRenewal
{
    public function handle(InvoiceSettled $event): void
    {
        $invoice        = $event->invoice;
        $subscriptionId = $invoice->subscription ?? null;

        if (! $subscriptionId) {
            return;
        }

        $membership = Membership::where('stripe_subscription_id', $subscriptionId)->first();

        if (! $membership) {
            return;
        }

        $invoiceId = $invoice->id;

        if (Transaction::where('stripe_id', $invoiceId)->exists()) {
            return;
        }

        // Extend the membership period on renewal
        $tier = $membership->tier;
        if ($tier && in_array($tier->billing_interval, ['monthly', 'annual'])) {
            $membership->update([
                'expires_on' => match ($tier->billing_interval) {
                    'monthly' => now()->addMonth()->toDateString(),
                    'annual'  => now()->addYear()->toDateString(),
                },
            ]);
        }

        Transaction::recordStripe([
            'subject_type' => Membership::class,
            'subject_id'   => $membership->id,
            'contact_id'   => $membership->contact_id,
            'amount'       => ($invoice->amount_paid ?? 0) / 100,
            'stripe_id'    => $invoiceId,
        ]);
    }
}
