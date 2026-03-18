<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\PermissionRegistrar;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $assigned = $this->record->permissions->pluck('name')->toArray();

        foreach (RoleResource::permissionAreas() as $area => $resources) {
            $areaPerms                    = array_keys(RoleResource::permissionsForArea($resources));
            $data["permissions_{$area}"] = array_values(array_intersect($assigned, $areaPerms));
        }

        $data['permissions_advanced'] = array_values(
            array_intersect($assigned, array_keys(RoleResource::standalonePermissions()))
        );

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        foreach (array_keys(RoleResource::permissionAreas()) as $area) {
            unset($data["permissions_{$area}"]);
        }
        unset($data['permissions_advanced']);
        return $data;
    }

    protected function afterSave(): void
    {
        $permissions = [];
        foreach (array_keys(RoleResource::permissionAreas()) as $area) {
            $permissions = array_merge(
                $permissions,
                $this->form->getRawState()["permissions_{$area}"] ?? []
            );
        }
        $permissions = array_merge(
            $permissions,
            $this->form->getRawState()['permissions_advanced'] ?? []
        );

        $this->record->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
