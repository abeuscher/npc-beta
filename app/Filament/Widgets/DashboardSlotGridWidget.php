<?php

namespace App\Filament\Widgets;

use App\Models\PageWidget;
use App\Models\WidgetType;
use Filament\Widgets\Widget;

class DashboardSlotGridWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard-slot-grid';

    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    /**
     * @return array<string, PageWidget>
     */
    protected function widgets(): array
    {
        $widgets = [];

        $memos = WidgetType::where('handle', 'memos')->first();
        if ($memos) {
            $widgets['memos'] = $this->makeWidget($memos, ['limit' => 5]);
        }

        $quickActions = WidgetType::where('handle', 'quick_actions')->first();
        if ($quickActions) {
            $widgets['quick_actions'] = $this->makeWidget($quickActions, [
                'actions' => ['new_contact', 'new_event', 'new_post'],
            ]);
        }

        $thisWeeksEvents = WidgetType::where('handle', 'this_weeks_events')->first();
        if ($thisWeeksEvents) {
            $widgets['this_weeks_events'] = $this->makeWidget($thisWeeksEvents, ['days_ahead' => 7]);
        }

        return $widgets;
    }

    /**
     * @param  array<string, mixed>  $configOverrides
     */
    private function makeWidget(WidgetType $widgetType, array $configOverrides): PageWidget
    {
        $pw = new PageWidget([
            'widget_type_id' => $widgetType->id,
            'config'         => $configOverrides,
        ]);
        $pw->setRelation('widgetType', $widgetType);

        return $pw;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'widgets' => $this->widgets(),
        ];
    }
}
