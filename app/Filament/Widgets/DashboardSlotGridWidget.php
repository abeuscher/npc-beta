<?php

namespace App\Filament\Widgets;

use App\Models\DashboardConfig;
use App\Models\PageWidget;
use Filament\Widgets\Widget;

class DashboardSlotGridWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard-slot-grid';

    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    /**
     * @return array<int, PageWidget>
     */
    protected function widgets(): array
    {
        $config = DashboardConfig::forUser(auth()->user());
        if (! $config) {
            return [];
        }

        return $config->widgets()
            ->where('is_active', true)
            ->with('widgetType')
            ->orderBy('sort_order')
            ->get()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $widgets = $this->widgets();

        return [
            'widgets'         => $widgets,
            'hasArrangement'  => ! empty($widgets),
        ];
    }
}
