<?php

namespace App\Observers;

use App\Models\Event;
use App\Models\Page;
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
        // An event's landing page lives and dies with the event (the inverse
        // guard — you can't delete the landing page while the event exists —
        // lives in PageResource). Force-delete it here so a deleted event never
        // leaves an orphaned, admin-invisible page that bloats every export.
        // Force-delete (not soft) so PageObserver::deleting tears down the
        // page's widgets + layouts too. NOTE: Eloquent mass deletes
        // (Event::where(...)->delete()) bypass this hook — those paths must
        // clean landing pages themselves (the scrub wipe does so via source
        // inheritance + wipeScrubPages).
        if ($event->landing_page_id) {
            Page::withTrashed()->find($event->landing_page_id)?->forceDelete();
        }

        ActivityLogger::log($event, 'deleted');
    }
}
