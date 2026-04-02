<?php

namespace App\Filament\Resources\NavigationMenuResource\Pages;

use App\Filament\Resources\NavigationMenuResource;
use Filament\Actions;
use App\Filament\Resources\Pages\ReadOnlyAwareEditRecord;

class EditNavigationMenu extends ReadOnlyAwareEditRecord
{
    protected static string $resource = NavigationMenuResource::class;

    protected array $pendingItems = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $topLevel = $this->record->items()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->with(['children' => fn ($q) => $q->orderBy('sort_order')])
            ->get();

        $data['items'] = $topLevel->map(function ($item) {
            return array_merge(
                NavigationMenuResource::itemToFormArray($item),
                [
                    'children' => $item->children
                        ->map(fn ($child) => NavigationMenuResource::itemToFormArray($child))
                        ->toArray(),
                ]
            );
        })->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingItems = $data['items'] ?? [];
        unset($data['items']);

        return $data;
    }

    protected function afterSave(): void
    {
        NavigationMenuResource::saveItems($this->record, $this->pendingItems);
    }
}
