<?php

namespace App\Filament\Resources\CustomFieldDefResource\Pages;

use App\Filament\Resources\CustomFieldDefResource;
use Filament\Actions;
use App\Filament\Resources\Pages\ReadOnlyAwareEditRecord;

class EditCustomFieldDef extends ReadOnlyAwareEditRecord
{
    protected static string $resource = CustomFieldDefResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
