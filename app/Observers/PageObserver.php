<?php

namespace App\Observers;

use App\Models\Page;
use App\Services\ActivityLogger;

class PageObserver
{
    public function created(Page $page): void
    {
        if ($page->type === 'member') {
            $this->applyMemberPrefix($page);
        }

        ActivityLogger::log($page, 'created');
    }

    public function updated(Page $page): void
    {
        // When a page's type is changed to 'event', ensure its slug carries
        // the 'events/' prefix so public routing and landing-page links work correctly.
        if ($page->wasChanged('type') && $page->type === 'event') {
            $eventsPrefix = config('site.events_prefix', 'events');

            if (! str_starts_with($page->slug, $eventsPrefix . '/')) {
                $page->updateQuietly(['slug' => $eventsPrefix . '/' . $page->slug]);
            }
        }

        // When a page's type is changed to 'post', ensure its slug carries
        // the blog prefix so public routing works correctly.
        if ($page->wasChanged('type') && $page->type === 'post') {
            $blogPrefix = config('site.blog_prefix', 'news');

            if (! str_starts_with($page->slug, $blogPrefix . '/')) {
                $page->updateQuietly(['slug' => $blogPrefix . '/' . $page->slug]);
            }
        }

        // When a page's type is changed to 'member', apply the portal prefix.
        if ($page->wasChanged('type') && $page->type === 'member') {
            $this->applyMemberPrefix($page);
        }

        $description = null;
        if ($page->wasChanged('is_published')) {
            $description = $page->is_published ? 'Published' : 'Unpublished';
        }

        ActivityLogger::log($page, 'updated', $description);
    }

    public function deleted(Page $page): void
    {
        ActivityLogger::log($page, 'deleted');
    }

    public function restored(Page $page): void
    {
        ActivityLogger::log($page, 'restored');
    }

    private function applyMemberPrefix(Page $page): void
    {
        $portalPrefix = \App\Models\SiteSetting::get('portal_prefix', 'members');

        if (! str_starts_with($page->slug, $portalPrefix . '/')) {
            $page->updateQuietly(['slug' => $portalPrefix . '/' . $page->slug]);
        }
    }
}
