<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\PermissionRegistrar;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        foreach (array_keys(RoleResource::permissionAreas()) as $area) {
            unset($data["permissions_{$area}"]);
        }
        return $data;
    }

    protected function afterCreate(): void
    {
        $permissions = [];
        foreach (array_keys(RoleResource::permissionAreas()) as $area) {
            $permissions = array_merge(
                $permissions,
                $this->form->getRawState()["permissions_{$area}"] ?? []
            );
        }

        if (! empty($permissions)) {
            $this->record->syncPermissions($permissions);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
}
