<?php

namespace App\Filament\Resources\RecordDetailViewResource\Pages;

use App\Filament\Resources\RecordDetailViewResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRecordDetailView extends EditRecord
{
    protected static string $resource = RecordDetailViewResource::class;

    protected static string $view = 'filament.resources.record-detail-view-resource.pages.edit-record-detail-view';

    public function getBreadcrumbs(): array
    {
        return [
            RecordDetailViewResource::getUrl('index') => 'Record Detail Views',
            'Edit',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
