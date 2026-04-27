<?php

namespace App\Filament\Resources\RecordDetailViewResource\Pages;

use App\Filament\Resources\RecordDetailViewResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRecordDetailViews extends ListRecords
{
    protected static string $resource = RecordDetailViewResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
