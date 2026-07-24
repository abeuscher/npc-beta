<?php

namespace App\Observers;

use App\Models\Contact;
use App\Models\EventRegistration;

class EventRegistrationObserver
{
    // The confirmation email is NOT sent here. Row creation is not order
    // confirmation: paid rows are created `pending` before payment, and one
    // order spans multiple rows. Dispatch lives at the confirm-points —
    // EventController::register / Portal\EventRegistrationController::store
    // (free path) and StripeWebhookController::handleEventRegistrationCheckout
    // (paid path) — one email per order.
    public function created(EventRegistration $registration): void
    {
        $registration->loadMissing('event');

        // Best-effort, one-way: a registration that fills the last tier flips the
        // event to sold out. Never auto-cleared — a manual reopen stays until the
        // next capacity hit.
        if ($registration->event
            && ! $registration->event->sold_out
            && $registration->event->isAtCapacity()) {
            $registration->event->update(['sold_out' => true]);
        }

        if (empty($registration->email)) {
            return;
        }

        if (! $registration->event || ! $registration->event->auto_create_contacts) {
            return;
        }

        $nameParts = explode(' ', trim($registration->name ?? ''), 2);
        $firstName = $nameParts[0] ?? '';
        $lastName  = $nameParts[1] ?? '';

        $contact = Contact::firstOrCreate(
            ['email' => $registration->email],
            [
                'first_name'     => $firstName,
                'last_name'      => $lastName,
                'phone'          => $registration->phone,
                'address_line_1' => $registration->address_line_1,
                'address_line_2' => $registration->address_line_2,
                'city'           => $registration->city,
                'state'          => $registration->state,
                'postal_code'    => $registration->zip,
            ]
        );

        if (! $registration->contact_id) {
            $registration->update(['contact_id' => $contact->id]);
        }
    }
}
