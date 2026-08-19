<?php

namespace App\Payments\Events;

/**
 * A failed subscription invoice whose consequence belongs to a vertical
 * (session 389, arc D3 — docs/plugin-contract.md surface 10). The Payments
 * plugin's webhook dispatches this core-owned event for every
 * invoice.payment_failed that carries a subscription id; each vertical's
 * listener resolves its own rows by that subscription id and no-ops cheaply
 * when the subscription is not its own. With no listener bound (vertical
 * absent) the dispatch is a no-op and the webhook still answers 200 — data
 * kept, writes stopped.
 *
 * Scoped to the donations slice at 389: the Donations listener marks the
 * recurring donation past_due and records the failed transaction (the
 * pre-inversion webhook behavior, verbatim).
 */
class InvoicePaymentFailed
{
    /**
     * @param object $invoice The Stripe Invoice object (id, subscription,
     *                        amount_due, …).
     */
    public function __construct(
        public readonly object $invoice,
    ) {
    }
}
