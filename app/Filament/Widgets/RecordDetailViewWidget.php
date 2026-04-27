<?php

namespace App\Filament\Widgets;

use App\Models\Contact;
use App\Models\PageWidget;
use App\Models\WidgetType;
use App\WidgetPrimitive\Views\RecordDetailView;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class RecordDetailViewWidget extends Widget
{
    protected static string $view = 'filament.widgets.record-detail-view';

    protected int | string | array $columnSpan = 'full';

    public ?Model $record = null;

    /**
     * Build the hardcoded RecordDetailView for the current record's type. 5b only
     * binds Contact::class — additional record types land in 5c when the
     * record_detail_views table replaces this hardcoded array.
     */
    protected function view(): ?RecordDetailView
    {
        if ($this->record === null) {
            return null;
        }

        if ($this->record instanceof Contact) {
            return new RecordDetailView(
                handle: 'contact_overview',
                recordType: Contact::class,
                widgets: [$this->makePlaceholderWidget()],
            );
        }

        return null;
    }

    /**
     * @return array<int, PageWidget>
     */
    protected function widgets(): array
    {
        if ($this->record === null) {
            return [];
        }

        return $this->view()?->widgets() ?? [];
    }

    private function makePlaceholderWidget(): PageWidget
    {
        $wt = WidgetType::where('handle', 'record_detail_placeholder')->first();

        $pw = new PageWidget([
            'widget_type_id' => $wt?->id,
            'config'         => [],
        ]);
        if ($wt !== null) {
            $pw->setRelation('widgetType', $wt);
        }

        return $pw;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $widgets = $this->widgets();

        return [
            'widgets'       => $widgets,
            'record'        => $this->record,
            'hasWidgets'    => ! empty($widgets) && $this->record !== null,
        ];
    }
}
