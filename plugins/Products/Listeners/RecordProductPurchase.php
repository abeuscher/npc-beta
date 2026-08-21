<?php

namespace Plugins\Products\Listeners;

use App\Models\Contact;
use App\Models\ProductPrice;
use App\Models\Purchase;
use App\Models\Transaction;
use App\Payments\Events\CheckoutSettled;
use Illuminate\Support\Facades\Log;

/**
 * The inbound half of the Products↔Payments seam (docs/plugin-contract.md
 * surface 10, products slice — session 398, the third cross-repo inversion).
 * Owns what the Payments webhook's product_price_id branch did before the
 * inversion: record the Purchase against the price's product, resolve or
 * create the buying Contact from the Stripe customer details (a core Contact
 * write — allowed), and record one Transaction for the session total.
 * Idempotent via the stripe_session_id guard carried over verbatim — a
 * webhook replay finds the purchase already recorded and no-ops.
 *
 * CheckoutSettled is dispatched for every extracted vertical's branch; the
 * metadata key routes it — a session without product_price_id belongs to
 * another vertical and this listener no-ops before any read.
 */
class RecordProductPurchase
{
    public function handle(CheckoutSettled $event): void
    {
        if (empty($event->metadata->product_price_id)) {
            return;
        }

        $session   = $event->session;
        $sessionId = $session->id;

        if (Purchase::where('stripe_session_id', $sessionId)->exists()) {
            return;
        }

        $priceId = $event->metadata->product_price_id;
        $price   = ProductPrice::with('product')->find($priceId);

        if (! $price) {
            Log::warning('Stripe product purchase: price not found', ['product_price_id' => $priceId]);
            return;
        }

        $contact     = $this->findOrCreateContact($session->customer_details ?? null);
        $amountTotal = $session->amount_total ?? 0;

        $purchase = Purchase::create([
            'product_id'        => $price->product_id,
            'product_price_id'  => $price->id,
            'contact_id'        => $contact?->id,
            'stripe_session_id' => $sessionId,
            'amount_paid'       => $amountTotal / 100,
            'status'            => 'active',
            'occurred_at'       => now(),
        ]);

        Transaction::recordStripe([
            'subject_type' => Purchase::class,
            'subject_id'   => $purchase->id,
            'contact_id'   => $contact?->id,
            'amount'       => $amountTotal / 100,
            'stripe_id'    => $session->payment_intent,
        ]);
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
