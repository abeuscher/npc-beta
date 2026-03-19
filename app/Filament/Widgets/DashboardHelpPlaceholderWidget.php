<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class DashboardHelpPlaceholderWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard-help-placeholder-widget';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 1;
}
