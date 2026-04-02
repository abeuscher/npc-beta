<?php

namespace App\Filament\Resources\WidgetTypeResource\Pages;

use App\Filament\Resources\WidgetTypeResource;
use Filament\Actions;
use App\Filament\Resources\Pages\ReadOnlyAwareEditRecord;

class EditWidgetType extends ReadOnlyAwareEditRecord
{
    protected static string $resource = WidgetTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
