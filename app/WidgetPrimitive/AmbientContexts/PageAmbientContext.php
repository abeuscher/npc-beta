<?php

namespace App\WidgetPrimitive\AmbientContexts;

use App\Models\Page;
use App\WidgetPrimitive\AmbientContext;

final class PageAmbientContext extends AmbientContext
{
    public function __construct(
        public readonly ?Page $currentPage = null,
    ) {}
}
