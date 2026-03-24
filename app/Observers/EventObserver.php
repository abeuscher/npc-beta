<?php

namespace App\Observers;

use App\Mail\EventCancellation;
use App\Models\Event;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Mail;

class EventObserver
{
    public function created(Event $event): void
    {
        ActivityLogger::log($event, 'created');
    }

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

        $description = null;
        if ($event->wasChanged('status')) {
            $description = 'Status changed to ' . $event->status;
        }

        ActivityLogger::log($event, 'updated', $description);
    }

    public function deleted(Event $event): void
    {
        ActivityLogger::log($event, 'deleted');
    }
}
