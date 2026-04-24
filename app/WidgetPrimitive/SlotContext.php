<?php

namespace App\WidgetPrimitive;

use App\Models\Page;
use App\Services\PageContext;

final class SlotContext
{
    /**
     * @param  bool  $publicSurface  Whether this slot renders on a public-web surface.
     *                               Public surfaces gate SOURCE_WIDGET_CONTENT_TYPE reads to
     *                               collections where is_public = true. Admin-only surfaces
     *                               (dashboard_grid, record_detail_sidebar) pass false so
     *                               admin-scoped collections (memos) resolve for their widgets.
     */
    public function __construct(
        public readonly PageContext $pageContext,
        private readonly ?Page $currentPageOverride = null,
        public readonly bool $publicSurface = true,
    ) {}

    public function currentPage(): ?Page
    {
        if ($this->currentPageOverride !== null) {
            return $this->currentPageOverride;
        }
        $page = $this->pageContext->currentPage;
        return ($page && $page->exists) ? $page : null;
    }
}
