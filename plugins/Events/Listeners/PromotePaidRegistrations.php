<?php

namespace Plugins\Events\Listeners;

use App\Models\Contact;
use App\Models\EventRegistration;
use App\Models\Transaction;
use App\Payments\Events\CheckoutSettled;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Plugins\Events\Mail\RegistrationConfirmation;

/**
 * The inbound half of the Events↔Payments seam (docs/plugin-contract.md
 * surface 10, events slice — session 381). Owns what the Payments webhook's
 * event_registration_checkout branch did before the inversion: promote the
 * order's pending registrations by stripe_session_id, record one Transaction
 * for the order total, and queue one confirmation email. Idempotent via the
 * pending-only filter — a webhook replay finds no pending rows and returns
 * before any write.
 */
class PromotePaidRegistrations
{
    public function handle(CheckoutSettled $event): void
    {
        $session   = $event->session;
        $sessionId = $session->id;

        $registrations = EventRegistration::where('stripe_session_id', $sessionId)
            ->where('status', 'pending')
            ->get();

        if ($registrations->isEmpty()) {
            Log::warning('Stripe event registration checkout: no pending registrations found', ['stripe_session_id' => $sessionId]);
            return;
        }

        $intentId    = $session->payment_intent;
        $amountTotal = $session->amount_total ?? 0;

        $first   = $registrations->first();
        $contact = $first->contact_id
            ? $first->contact
            : $this->findOrCreateContact($session->customer_details ?? null);

        foreach ($registrations as $registration) {
            $registration->update([
                'status'                   => 'registered',
                'stripe_payment_intent_id' => $intentId,
                'contact_id'               => $registration->contact_id ?? $contact?->id,
            ]);
        }

        // One Transaction records the order total; subject linkage stays single-
        // valued (the first registration on the session). Sibling rows share
        // the linkage implicitly via the shared stripe_session_id.
        Transaction::recordStripe([
            'subject_type' => EventRegistration::class,
            'subject_id'   => $first->id,
            'contact_id'   => $contact?->id,
            'amount'       => $amountTotal / 100,
            'stripe_id'    => $intentId,
        ]);

        // One thank-you per order, at confirm time — payment is the confirm-
        // point for the paid path (the free path emails in the registration
        // controllers). Idempotent via the pending-only filter above: a webhook
        // replay finds no pending rows and early-returns before reaching here.
        // Queued (not sent) so a mail-provider hiccup cannot fail the webhook.
        if (! empty($first->email)) {
            Mail::to($first->email)->queue(new RegistrationConfirmation($first));
        }
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
