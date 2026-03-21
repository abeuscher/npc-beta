<?php

namespace App\Filament\Resources\FormResource\Pages;

use App\Filament\Resources\FormResource;
use Filament\Resources\Pages\CreateRecord;

class CreateForm extends CreateRecord
{
    protected static string $resource = FormResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            FormResource::getUrl() => 'Forms',
            'Create Form',
        ];
    }
}
