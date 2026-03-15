<?php

namespace App\Observers;

use App\Mail\RegistrationConfirmation;
use App\Models\Contact;
use App\Models\EventRegistration;
use Illuminate\Support\Facades\Mail;

class EventRegistrationObserver
{
    public function created(EventRegistration $registration): void
    {
        if (! empty($registration->email)) {
            Mail::to($registration->email)->send(new RegistrationConfirmation($registration));
        }

        $registration->loadMissing('event');

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
