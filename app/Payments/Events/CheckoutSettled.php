<?php

namespace App\Payments\Events;

/**
 * A completed checkout session whose fulfillment belongs to a vertical
 * (session 381, arc P5 — docs/plugin-contract.md surface 10). The Payments
 * plugin's webhook keeps the metadata routing switch (the metadata key is
 * written by the vertical at session creation; routing is payments
 * infrastructure) and dispatches this core-owned event for branches whose
 * vertical has extracted; the vertical's listener owns the fulfillment. With
 * no listener bound (vertical absent) the dispatch is a no-op and the webhook
 * still answers 200 — data kept, writes stopped.
 *
 * Scoped to the events slice at 381: only the event_registration_checkout
 * branch dispatches. The donation / membership / product / invoice / refund
 * branches invert per-vertical as each one extracts.
 */
class CheckoutSettled
{
    /**
     * @param object      $session  The Stripe Checkout Session object (id,
     *                              payment_intent, amount_total,
     *                              customer_details, …).
     * @param object|null $metadata The session's metadata object — the
     *                              routing keys the vertical wrote at
     *                              session creation.
     */
    public function __construct(
        public readonly object $session,
        public readonly ?object $metadata,
    ) {
    }
}
