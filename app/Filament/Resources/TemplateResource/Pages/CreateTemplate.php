<?php

namespace App\Filament\Resources\TemplateResource\Pages;

use App\Filament\Resources\TemplateResource;
use App\Models\Page;
use Filament\Resources\Pages\CreateRecord;

class CreateTemplate extends CreateRecord
{
    protected static string $resource = TemplateResource::class;

    public function getTitle(): string
    {
        return 'New Page Template';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type']       = 'page';
        $data['is_default'] = false;
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return EditPageTemplate::getUrl(['record' => $this->record]);
    }
}
