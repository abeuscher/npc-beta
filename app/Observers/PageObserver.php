<?php

namespace App\Observers;

use App\Models\Page;

class PageObserver
{
    public function updated(Page $page): void
    {
        // When a page's type is changed to 'event', ensure its slug carries
        // the 'events/' prefix so public routing and landing-page links work correctly.
        if ($page->wasChanged('type') && $page->type === 'event') {
            $eventsPrefix = config('site.events_prefix', 'events');

            if (! str_starts_with($page->slug, $eventsPrefix . '/')) {
                // Update without triggering slug auto-generation (doNotGenerateSlugsOnUpdate)
                $page->updateQuietly(['slug' => $eventsPrefix . '/' . $page->slug]);
            }
        }
    }
}
