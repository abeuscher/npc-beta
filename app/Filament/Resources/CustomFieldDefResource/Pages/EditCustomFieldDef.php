<?php

namespace App\Filament\Resources\CustomFieldDefResource\Pages;

use App\Filament\Resources\CustomFieldDefResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomFieldDef extends EditRecord
{
    protected static string $resource = CustomFieldDefResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
