<?php

namespace App\Observers;

use App\Mail\RegistrationConfirmation;
use App\Models\EventRegistration;
use Illuminate\Support\Facades\Mail;

class EventRegistrationObserver
{
    public function created(EventRegistration $registration): void
    {
        if (! empty($registration->email)) {
            Mail::to($registration->email)->send(new RegistrationConfirmation($registration));
        }
    }
}
