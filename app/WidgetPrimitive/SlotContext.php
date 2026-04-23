<?php

namespace App\WidgetPrimitive;

use App\Models\Page;
use App\Services\PageContext;

final class SlotContext
{
    public function __construct(
        public readonly PageContext $pageContext,
        private readonly ?Page $currentPageOverride = null,
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
