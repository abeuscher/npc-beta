<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Donation;
use App\Models\EventRegistration;
use App\Models\Membership;
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

        // ── Event registration ───────────────────────────────────────────────
        if (! empty($metadata->event_registration_id)) {
            return $this->handleEventRegistrationCheckout($session, $metadata);
        }

        // ── Membership ──────────────────────────────────────────────────────
        if (! empty($metadata->membership_id)) {
            return $this->handleMembershipCheckout($session, $metadata);
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

        Transaction::recordStripe([
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'contact_id'   => null,
            'amount'       => $amountTotal / 100,
            'stripe_id'    => $intentId,
        ]);

        return response('OK', 200);
    }

    private function handleDonationCheckout(\Stripe\Event $event): Response
    {
        $session  = $event->data->object;
        $intentId = $session->payment_intent;

        if ($intentId && Transaction::where('stripe_id', $intentId)->exists()) {
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
            'contact_id'             => $contact?->id,
            'stripe_customer_id'     => $session->customer ?? null,
            'stripe_subscription_id' => $session->subscription ?? null,
            'status'                 => 'active',
            'started_at'             => now(),
        ]);

        Transaction::recordStripe([
            'subject_type' => Donation::class,
            'subject_id'   => $donation->id,
            'contact_id'   => $contact?->id,
            'amount'       => $amountTotal / 100,
            'stripe_id'    => $intentId,
        ]);

        return response('OK', 200);
    }

    private function handleEventRegistrationCheckout(object $session, object $metadata): Response
    {
        $registrationId = $metadata->event_registration_id;
        $registration   = EventRegistration::find($registrationId);

        if (! $registration) {
            Log::warning('Stripe event registration checkout: registration not found', ['event_registration_id' => $registrationId]);
            return response('OK', 200);
        }

        if ($registration->status !== 'pending') {
            return response('OK', 200);
        }

        $intentId    = $session->payment_intent;
        $amountTotal = $session->amount_total ?? 0;

        $contact = $registration->contact_id
            ? $registration->contact
            : $this->findOrCreateContact($session->customer_details ?? null);

        if ($contact && ! $registration->contact_id) {
            $registration->contact_id = $contact->id;
        }

        $registration->update([
            'status'                   => 'registered',
            'stripe_payment_intent_id' => $intentId,
        ]);

        Transaction::recordStripe([
            'subject_type' => EventRegistration::class,
            'subject_id'   => $registration->id,
            'contact_id'   => $contact?->id,
            'amount'       => $amountTotal / 100,
            'stripe_id'    => $intentId,
        ]);

        return response('OK', 200);
    }

    private function handleMembershipCheckout(object $session, object $metadata): Response
    {
        $membershipId = $metadata->membership_id;
        $membership   = Membership::find($membershipId);

        if (! $membership) {
            Log::warning('Stripe membership checkout: membership not found', ['membership_id' => $membershipId]);
            return response('OK', 200);
        }

        if ($membership->status !== 'pending') {
            return response('OK', 200);
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

        // ── Donation renewal ────────────────────────────────────────────────
        $donation = Donation::where('stripe_subscription_id', $subscriptionId)->first();

        if (! $donation) {
            // ── Membership renewal ──────────────────────────────────────────
            return $this->handleMembershipInvoice($invoice, $subscriptionId);
        }

        if (Transaction::where('stripe_id', $invoiceId)->exists()) {
            return response('OK', 200);
        }

        Transaction::recordStripe([
            'subject_type' => Donation::class,
            'subject_id'   => $donation->id,
            'contact_id'   => $donation->contact_id,
            'amount'       => ($invoice->amount_paid ?? 0) / 100,
            'stripe_id'    => $invoiceId,
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
            Transaction::recordStripe([
                'subject_type' => Donation::class,
                'subject_id'   => $donation->id,
                'contact_id'   => $donation->contact_id,
                'amount'       => ($invoice->amount_due ?? 0) / 100,
                'status'       => 'failed',
                'stripe_id'    => $invoiceId,
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

        return response('OK', 200);
    }

    private function handleMembershipInvoice(object $invoice, string $subscriptionId): Response
    {
        $membership = Membership::where('stripe_subscription_id', $subscriptionId)->first();

        if (! $membership) {
            return response('OK', 200);
        }

        $invoiceId = $invoice->id;

        if (Transaction::where('stripe_id', $invoiceId)->exists()) {
            return response('OK', 200);
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

        Transaction::recordStripe([
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'contact_id'   => null,
            'amount'       => $amount / 100,
            'status'       => 'failed',
            'stripe_id'    => $intentId,
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

        Transaction::recordStripe([
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'contact_id'   => $original?->contact_id,
            'type'         => 'refund',
            'amount'       => $refund->amount / 100,
            'direction'    => 'out',
            'stripe_id'    => $refundId,
        ]);

        return response('OK', 200);
    }
}
