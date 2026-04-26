<?php

namespace App\Observers;

use App\Models\Event;
use App\Services\ActivityLogger;

class EventObserver
{
    public function creating(Event $event): void
    {
        if ($event->status === 'published' && $event->published_at === null) {
            $event->published_at = now();
        }
    }

    public function updating(Event $event): void
    {
        if ($event->isDirty('status')
            && $event->status === 'published'
            && $event->published_at === null
        ) {
            $event->published_at = now();
        }
    }

    public function created(Event $event): void
    {
        ActivityLogger::log($event, 'created');
    }

    public function updated(Event $event): void
    {
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
