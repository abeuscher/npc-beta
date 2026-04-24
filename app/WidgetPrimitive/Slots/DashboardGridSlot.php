<?php

namespace App\WidgetPrimitive\Slots;

use App\Services\PageContext;
use App\WidgetPrimitive\Slot;
use App\WidgetPrimitive\SlotContext;

final class DashboardGridSlot extends Slot
{
    public function handle(): string
    {
        return 'dashboard_grid';
    }

    public function label(): string
    {
        return 'Dashboard Grid';
    }

    public function ambientContext(): SlotContext
    {
        return new SlotContext(app(PageContext::class), null, publicSurface: false);
    }

    public function layoutConstraints(): array
    {
        return [
            'allowed_appearance_fields' => ['background', 'text'],
            'dimensions'                => ['width' => 'int', 'height' => 'int'],
            'column_stackable'          => false,
            'full_width_allowed'        => false,
        ];
    }

    public function configSurface(): ?string
    {
        return null;
    }
}
