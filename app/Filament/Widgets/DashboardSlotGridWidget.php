<?php

namespace App\Filament\Widgets;

use App\Models\PageWidget;
use App\WidgetPrimitive\Views\DashboardView;
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
        $view = DashboardView::forUser(auth()->user());
        if (! $view) {
            return [];
        }

        return $view->widgets();
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
