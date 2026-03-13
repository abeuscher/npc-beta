<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['roles']);
        return $data;
    }

    protected function afterCreate(): void
    {
        $roles = $this->form->getRawState()['roles'] ?? [];
        if (! empty($roles)) {
            $this->record->syncRoles($roles);
        }
    }
}
