<?php

namespace App\Filament\Resources\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * Edit page that allows users with view (but not update) permission to
 * access the form in read-only mode. Fields are disabled and save/delete
 * buttons are hidden. The save() method still enforces update permission
 * server-side via Filament's built-in authorizeAccess() call.
 */
abstract class ReadOnlyAwareEditRecord extends EditRecord
{
    protected function authorizeAccess(): void
    {
        $resource = static::getResource();
        $record = $this->getRecord();

        // Allow access if user can update (normal) OR just view the record.
        abort_unless(
            $resource::canEdit($record) || auth()->user()?->can('view', $record),
            403,
        );
    }

    protected function isReadOnly(): bool
    {
        return ! static::getResource()::canEdit($this->getRecord());
    }

    protected function fillForm(): void
    {
        parent::fillForm();

        if ($this->isReadOnly()) {
            $this->form->disabled();
        }
    }

    protected function getFormActions(): array
    {
        if ($this->isReadOnly()) {
            return [];
        }

        return parent::getFormActions();
    }

    protected function getHeaderActions(): array
    {
        if ($this->isReadOnly()) {
            return $this->getReadOnlyHeaderActions();
        }

        return parent::getHeaderActions();
    }

    /**
     * Header actions shown when the page is in read-only mode.
     * Subclasses can override to keep specific view-only actions.
     */
    protected function getReadOnlyHeaderActions(): array
    {
        return [];
    }
}
