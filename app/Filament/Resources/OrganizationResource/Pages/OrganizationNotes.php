<?php

namespace App\Filament\Resources\OrganizationResource\Pages;

use App\Filament\Concerns\RecordTimelinePage;
use App\Filament\Resources\OrganizationResource;
use App\Models\Organization;

class OrganizationNotes extends RecordTimelinePage
{
    protected static string $resource = OrganizationResource::class;

    public Organization $record;

    public function mount(Organization|string $record): void
    {
        $this->record = $record instanceof Organization ? $record : Organization::findOrFail($record);
    }

    public function getRecordTitle(): string
    {
        return $this->record->name;
    }

    protected function notableType(): string
    {
        return Organization::class;
    }

    protected function recordResourceClass(): string
    {
        return OrganizationResource::class;
    }
}
