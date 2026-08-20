<?php

namespace Plugins\Memberships\Listeners;

use App\Models\Membership;
use App\Models\Transaction;
use App\Payments\Events\CheckoutSettled;
use Illuminate\Support\Facades\Log;

/**
 * The inbound half of the Memberships↔Payments seam (docs/plugin-contract.md
 * surface 10, memberships slice — session 392, the second cross-repo
 * inversion). Owns what the Payments webhook's membership_id branch did
 * before the inversion: promote the pending membership to active by its
 * metadata membership_id, set the start/expiry window from the tier's
 * billing interval, and record one Transaction for the session total.
 * Idempotent via the status guard carried over verbatim — a webhook replay
 * finds the membership already active and no-ops.
 *
 * CheckoutSettled is dispatched for every extracted vertical's branch; the
 * metadata key routes it — a session without membership_id belongs to another
 * vertical and this listener no-ops before any read.
 */
class PromotePaidMemberships
{
    public function handle(CheckoutSettled $event): void
    {
        if (empty($event->metadata->membership_id)) {
            return;
        }

        $session      = $event->session;
        $membershipId = $event->metadata->membership_id;
        $membership   = Membership::find($membershipId);

        if (! $membership) {
            Log::warning('Stripe membership checkout: membership not found', ['membership_id' => $membershipId]);
            return;
        }

        if ($membership->status !== 'pending') {
            return;
        }

        $intentId    = $session->payment_intent;
        $amountTotal = $session->amount_total ?? 0;

        $tier = $membership->tier;

        $membership->update([
            'status'                  => 'active',
            'starts_on'               => now()->toDateString(),
            'expires_on'              => match ($tier?->billing_interval) {
                'monthly' => now()->addMonth()->toDateString(),
                'annual'  => now()->addYear()->toDateString(),
                default   => null, // lifetime / one_time
            },
            'amount_paid'             => $amountTotal / 100,
            'stripe_subscription_id'  => $session->subscription ?? null,
        ]);

        Transaction::recordStripe([
            'subject_type' => Membership::class,
            'subject_id'   => $membership->id,
            'contact_id'   => $membership->contact_id,
            'amount'       => $amountTotal / 100,
            'stripe_id'    => $intentId ?? ($session->subscription ?? $session->id),
        ]);
    }
}
