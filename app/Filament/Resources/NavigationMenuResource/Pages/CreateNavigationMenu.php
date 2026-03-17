<?php

namespace App\Filament\Resources\NavigationMenuResource\Pages;

use App\Filament\Resources\NavigationMenuResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNavigationMenu extends CreateRecord
{
    protected static string $resource = NavigationMenuResource::class;

    protected array $pendingItems = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingItems = $data['items'] ?? [];
        unset($data['items']);

        return $data;
    }

    protected function afterCreate(): void
    {
        NavigationMenuResource::saveItems($this->record, $this->pendingItems);
    }
}
