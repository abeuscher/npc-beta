<?php

namespace App\Filament\Resources\CustomFieldDefResource\Pages;

use App\Filament\Resources\CustomFieldDefResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomFieldDefs extends ListRecords
{
    protected static string $resource = CustomFieldDefResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
