<?php

namespace App\WidgetPrimitive\Slots;

use App\Models\Page;
use App\Services\PageContext;
use App\WidgetPrimitive\Slot;
use App\WidgetPrimitive\SlotContext;

final class PageBuilderCanvasSlot extends Slot
{
    public function handle(): string
    {
        return 'page_builder_canvas';
    }

    public function label(): string
    {
        return 'Page Builder Canvas';
    }

    public function ambientContext(PageContext $pageContext, ?Page $currentPageOverride = null): SlotContext
    {
        return new SlotContext($pageContext, $currentPageOverride);
    }

    public function layoutConstraints(): array
    {
        return [
            'allowed_appearance_fields' => '*',
            'dimensions'                => null,
            'column_stackable'          => true,
            'full_width_allowed'        => true,
        ];
    }

    public function configSurface(): ?string
    {
        return 'page_builder_vue';
    }
}
