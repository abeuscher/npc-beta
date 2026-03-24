<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
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
        $intentId = $session->payment_intent;

        if (Transaction::where('stripe_id', $intentId)->exists()) {
            return response('OK', 200);
        }

        $metadata    = $session->metadata ?? null;
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
