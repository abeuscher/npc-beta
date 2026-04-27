<?php

namespace App\WidgetPrimitive;

use App\Models\Page;
use App\WidgetPrimitive\AmbientContexts\PageAmbientContext;

final class SlotContext
{
    /**
     * @param  AmbientContext  $ambient        Typed ambient payload for the slot. The
     *                                         per-slot subtype determines what state is
     *                                         available (e.g. PageAmbientContext carries
     *                                         the current Page; DashboardAmbientContext
     *                                         carries nothing today).
     * @param  bool            $publicSurface  Whether this slot renders on a public-web surface.
     *                                         Public surfaces gate SOURCE_WIDGET_CONTENT_TYPE reads to
     *                                         collections where is_public = true. Admin-only surfaces
     *                                         (dashboard_grid, record_detail_sidebar) pass false so
     *                                         admin-scoped collections (memos) resolve for their widgets.
     */
    public function __construct(
        public readonly AmbientContext $ambient,
        public readonly bool $publicSurface = true,
    ) {}

    public function currentPage(): ?Page
    {
        if (! $this->ambient instanceof PageAmbientContext) {
            return null;
        }
        $page = $this->ambient->currentPage;
        return ($page && $page->exists) ? $page : null;
    }
}
