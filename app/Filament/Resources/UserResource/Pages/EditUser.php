<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['roles'] = $this->record->getRoleNames()->toArray();
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['roles']);
        return $data;
    }

    protected function afterSave(): void
    {
        $roles = $this->form->getRawState()['roles'] ?? [];
        $this->record->syncRoles($roles);
    }
}
