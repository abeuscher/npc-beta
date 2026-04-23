<?php

namespace App\WidgetPrimitive\Slots;

use App\WidgetPrimitive\Slot;
use RuntimeException;

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

    public function ambientContext(): object
    {
        throw new RuntimeException('Slot ambient context not yet wired — lands with Phase 3');
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
