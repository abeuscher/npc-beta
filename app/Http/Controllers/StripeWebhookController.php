<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Donation;
use App\Models\ProductPrice;
use App\Models\Purchase;
use App\Models\Transaction;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $payload   = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                config('services.stripe.webhook_secret'),
            );
        } catch (SignatureVerificationException $e) {
            return response('Invalid signature', 400);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook signature check failed', ['error' => $e->getMessage()]);
            return response('Invalid signature', 400);
        }

        try {
            return match ($event->type) {
                'checkout.session.completed'    => $this->handleCheckoutSessionCompleted($event),
                'invoice.payment_succeeded'     => $this->handleInvoicePaymentSucceeded($event),
                'invoice.payment_failed'        => $this->handleInvoicePaymentFailed($event),
                'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($event),
                'refund.created'                => $this->handleRefundCreated($event),
                default                         => response('OK', 200),
            };
        } catch (\Throwable $e) {
            Log::error('Stripe webhook handler failed', [
                'event_type' => $event->type,
                'error'      => $e->getMessage(),
            ]);
            return response('OK', 200);
        }
    }

    private function handleCheckoutSessionCompleted(\Stripe\Event $event): Response
    {
        $session  = $event->data->object;
        $metadata = $session->metadata ?? null;

        // ── Donation ──────────────────────────────────────────────────────────
        if (! empty($metadata->donation_id)) {
            return $this->handleDonationCheckout($event);
        }

        // ── Product purchase ─────────────────────────────────────────────────
        if (! empty($metadata->product_price_id)) {
            return $this->handleProductPurchase($session, $metadata);
        }

        // ── General transaction (existing behaviour) ─────────────────────────
        $intentId = $session->payment_intent;

        if (Transaction::where('stripe_id', $intentId)->exists()) {
            return response('OK', 200);
        }

        $subjectType = $metadata->subject_type ?? null;
        $subjectId   = $metadata->subject_id ?? null;
        $amountTotal = $session->amount_total ?? 0;

        Transaction::create([
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'type'         => 'payment',
            'amount'       => $amountTotal / 100,
            'direction'    => 'in',
            'status'       => 'completed',
            'stripe_id'    => $intentId,
            'occurred_at'  => now(),
        ]);

        return response('OK', 200);
    }

    private function handleDonationCheckout(\Stripe\Event $event): Response
    {
        $session   = $event->data->object;
        $sessionId = $session->id;

        if (Transaction::where('stripe_id', $sessionId)->exists()) {
            return response('OK', 200);
        }

        $donationId = $session->metadata->donation_id ?? null;
        $donation   = $donationId ? Donation::find($donationId) : null;

        if (! $donation) {
            Log::warning('Stripe donation checkout: donation not found', ['donation_id' => $donationId]);
            return response('OK', 200);
        }

        $contact     = $this->findOrCreateContact($session->customer_details ?? null);
        $amountTotal = $session->amount_total ?? 0;

        $donation->update([
            'contact_id'            => $contact?->id,
            'stripe_customer_id'    => $session->customer ?? null,
            'stripe_subscription_id' => $session->subscription ?? null,
            'status'                => 'active',
            'started_at'            => now(),
        ]);

        Transaction::create([
            'subject_type' => Donation::class,
            'subject_id'   => $donation->id,
            'type'         => 'payment',
            'amount'       => $amountTotal / 100,
            'direction'    => 'in',
            'status'       => 'completed',
            'stripe_id'    => $sessionId,
            'occurred_at'  => now(),
        ]);

        return response('OK', 200);
    }

    private function handleInvoicePaymentSucceeded(\Stripe\Event $event): Response
    {
        $invoice        = $event->data->object;
        $invoiceId      = $invoice->id;
        $subscriptionId = $invoice->subscription ?? null;

        if (! $subscriptionId) {
            return response('OK', 200);
        }

        $donation = Donation::where('stripe_subscription_id', $subscriptionId)->first();

        if (! $donation) {
            return response('OK', 200);
        }

        if (Transaction::where('stripe_id', $invoiceId)->exists()) {
            return response('OK', 200);
        }

        Transaction::create([
            'subject_type' => Donation::class,
            'subject_id'   => $donation->id,
            'type'         => 'payment',
            'amount'       => ($invoice->amount_paid ?? 0) / 100,
            'direction'    => 'in',
            'status'       => 'completed',
            'stripe_id'    => $invoiceId,
            'occurred_at'  => now(),
        ]);

        if (($invoice->billing_reason ?? '') !== 'subscription_create' && $donation->contact_id) {
            $donation->loadMissing('contact');
            ActivityLogger::log($donation->contact, 'donation_received');
        }

        return response('OK', 200);
    }

    private function handleInvoicePaymentFailed(\Stripe\Event $event): Response
    {
        $invoice        = $event->data->object;
        $invoiceId      = $invoice->id;
        $subscriptionId = $invoice->subscription ?? null;

        if (! $subscriptionId) {
            return response('OK', 200);
        }

        $donation = Donation::where('stripe_subscription_id', $subscriptionId)->first();

        if (! $donation) {
            return response('OK', 200);
        }

        $donation->update(['status' => 'past_due']);

        if (! Transaction::where('stripe_id', $invoiceId)->exists()) {
            Transaction::create([
                'subject_type' => Donation::class,
                'subject_id'   => $donation->id,
                'type'         => 'payment',
                'amount'       => ($invoice->amount_due ?? 0) / 100,
                'direction'    => 'in',
                'status'       => 'failed',
                'stripe_id'    => $invoiceId,
                'occurred_at'  => now(),
            ]);
        }

        return response('OK', 200);
    }

    private function handleProductPurchase(object $session, object $metadata): Response
    {
        $sessionId = $session->id;

        if (Purchase::where('stripe_session_id', $sessionId)->exists()) {
            return response('OK', 200);
        }

        $priceId = $metadata->product_price_id ?? null;
        $price   = $priceId ? ProductPrice::with('product')->find($priceId) : null;

        if (! $price) {
            Log::warning('Stripe product purchase: price not found', ['product_price_id' => $priceId]);
            return response('OK', 200);
        }

        $contact     = $this->findOrCreateContact($session->customer_details ?? null);
        $amountTotal = $session->amount_total ?? 0;

        Purchase::create([
            'product_id'        => $price->product_id,
            'product_price_id'  => $price->id,
            'contact_id'        => $contact?->id,
            'stripe_session_id' => $sessionId,
            'amount_paid'       => $amountTotal / 100,
            'status'            => 'active',
            'occurred_at'       => now(),
        ]);

        return response('OK', 200);
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

    private function handlePaymentIntentFailed(\Stripe\Event $event): Response
    {
        $intent   = $event->data->object;
        $intentId = $intent->id;

        if (Transaction::where('stripe_id', $intentId)->exists()) {
            return response('OK', 200);
        }

        $metadata    = $intent->metadata ?? null;
        $subjectType = $metadata->subject_type ?? null;
        $subjectId   = $metadata->subject_id ?? null;
        $amount      = $intent->amount ?? 0;

        Transaction::create([
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'type'         => 'payment',
            'amount'       => $amount / 100,
            'direction'    => 'in',
            'status'       => 'failed',
            'stripe_id'    => $intentId,
            'occurred_at'  => now(),
        ]);

        return response('OK', 200);
    }

    private function handleRefundCreated(\Stripe\Event $event): Response
    {
        $refund   = $event->data->object;
        $refundId = $refund->id;

        if (Transaction::where('stripe_id', $refundId)->exists()) {
            return response('OK', 200);
        }

        // Inherit subject from the original payment transaction
        $original    = Transaction::where('stripe_id', $refund->payment_intent)->first();
        $subjectType = $original?->subject_type;
        $subjectId   = $original?->subject_id;

        Transaction::create([
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'type'         => 'refund',
            'amount'       => $refund->amount / 100,
            'direction'    => 'out',
            'status'       => 'completed',
            'stripe_id'    => $refundId,
            'occurred_at'  => now(),
        ]);

        return response('OK', 200);
    }
}
