<?php

namespace App\Observers;

use App\Mail\EventCancellation;
use App\Models\Event;
use Illuminate\Support\Facades\Mail;

class EventObserver
{
    public function updated(Event $event): void
    {
        if ($event->wasChanged('status') && $event->status === 'cancelled') {
            $event->registrations()
                ->where('status', 'registered')
                ->get()
                ->each(function ($registration) {
                    if (! empty($registration->email)) {
                        Mail::to($registration->email)->send(new EventCancellation($registration));
                    }
                });
        }
    }
}
