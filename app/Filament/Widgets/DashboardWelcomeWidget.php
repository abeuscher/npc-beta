<?php

namespace App\Filament\Widgets;

use App\Models\SiteSetting;
use Filament\Widgets\Widget;

class DashboardWelcomeWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard-welcome-widget';

    protected static ?int $sort = -10;

    public static function canView(): bool
    {
        return SiteSetting::get('dashboard_welcome', '') !== '';
    }
}
