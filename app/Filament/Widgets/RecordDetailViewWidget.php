<?php

namespace App\Filament\Widgets;

use App\Models\PageWidget;
use App\WidgetPrimitive\ViewRegistry;
use App\WidgetPrimitive\Views\RecordDetailView;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class RecordDetailViewWidget extends Widget
{
    protected static string $view = 'filament.widgets.record-detail-view';

    protected static bool $isDiscovered = false;

    protected int | string | array $columnSpan = 'full';

    public ?Model $record = null;

    public ?RecordDetailView $detailView = null;

    protected function resolveView(): ?RecordDetailView
    {
        if ($this->detailView !== null) {
            return $this->detailView;
        }

        if ($this->record === null) {
            return null;
        }

        return app(ViewRegistry::class)->forRecordType($this->record::class)->first();
    }

    /**
     * @return array<int, PageWidget>
     */
    protected function widgets(): array
    {
        $view = $this->resolveView();

        if ($view === null || $this->record === null) {
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
            'widgets'    => $widgets,
            'record'     => $this->record,
            'hasWidgets' => ! empty($widgets) && $this->record !== null,
        ];
    }
}
