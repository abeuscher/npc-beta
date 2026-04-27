<?php

namespace App\Filament\Resources\ContactResource\Pages;

use App\Filament\Resources\ContactResource;
use App\Filament\Resources\Pages\RecordDetailViewPage;

class EditContactView extends RecordDetailViewPage
{
    protected static string $resource = ContactResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;
        if ($record === null) {
            return false;
        }

        return ContactResource::canEdit($record) || auth()->user()?->can('view', $record);
    }

    public function getBreadcrumbs(): array
    {
        return [
            ContactResource::getUrl() => 'Contacts',
            EditContact::getUrl(['record' => $this->record]) => 'Edit Contact',
            $this->resolvedView?->label ?? '',
        ];
    }

    protected function subNavigationEntryPage(): ?string
    {
        return EditContact::class;
    }

    protected function recordDetailViewSubPageClass(): ?string
    {
        return self::class;
    }
}
