<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class DashboardWelcomeWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard-welcome-widget';

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        return true;
    }
}
