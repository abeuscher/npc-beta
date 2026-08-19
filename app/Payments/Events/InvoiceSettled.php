<?php

namespace App\Payments\Events;

/**
 * A paid subscription invoice whose fulfillment belongs to a vertical
 * (session 389, arc D3 — docs/plugin-contract.md surface 10). The Payments
 * plugin's webhook dispatches this core-owned event for every
 * invoice.payment_succeeded that carries a subscription id; each vertical's
 * listener resolves its own rows by that subscription id and no-ops cheaply
 * when the subscription is not its own (subscription ids are disjoint across
 * verticals). With no listener bound (vertical absent) the dispatch is a
 * no-op and the webhook still answers 200 — data kept, writes stopped.
 *
 * Scoped to the donations slice at 389: the Donations listener records the
 * renewal transaction. The membership-renewal branch stays inline in
 * Payments and inverts when Memberships extracts (arc D4).
 */
class InvoiceSettled
{
    /**
     * @param object $invoice The Stripe Invoice object (id, subscription,
     *                        amount_paid, billing_reason, …).
     */
    public function __construct(
        public readonly object $invoice,
    ) {
    }
}
