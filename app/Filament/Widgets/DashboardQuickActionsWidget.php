<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class DashboardQuickActionsWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard-quick-actions-widget';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        return false;
    }
}
