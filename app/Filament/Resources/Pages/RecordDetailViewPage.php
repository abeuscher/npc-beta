<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Concerns\HasRecordDetailSubNavigation;
use App\Filament\Widgets\RecordDetailViewWidget;
use App\WidgetPrimitive\ViewRegistry;
use App\WidgetPrimitive\Views\RecordDetailView;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\ModelNotFoundException;

abstract class RecordDetailViewPage extends Page
{
    use HasRecordDetailSubNavigation, InteractsWithRecord {
        HasRecordDetailSubNavigation::getSubNavigation insteadof InteractsWithRecord;
    }

    protected static string $view = 'filament.resources.pages.record-detail-view-page';

    public string $viewHandle = '';

    public ?RecordDetailView $resolvedView = null;

    public function mount(int | string $record, string $view): void
    {
        $this->record = $this->resolveRecord($record);
        $this->viewHandle = $view;

        $resolved = app(ViewRegistry::class)->findByHandle($this->record::class, $view);

        if ($resolved === null) {
            throw (new ModelNotFoundException)->setModel(RecordDetailView::class, [$view]);
        }

        $this->resolvedView = $resolved;
    }

    public function getTitle(): string
    {
        return $this->resolvedView?->label ?? '';
    }

    public function getBreadcrumb(): ?string
    {
        return $this->resolvedView?->label;
    }

    /**
     * @return array<class-string>
     */
    protected function getFooterWidgets(): array
    {
        return [
            RecordDetailViewWidget::class,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return [
            'record'     => $this->record,
            'detailView' => $this->resolvedView,
        ];
    }
}
