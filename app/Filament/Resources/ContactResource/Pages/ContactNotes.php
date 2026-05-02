<?php

namespace App\Filament\Resources\ContactResource\Pages;

use App\Filament\Concerns\RecordTimelinePage;
use App\Filament\Resources\ContactResource;
use App\Models\Contact;

class ContactNotes extends RecordTimelinePage
{
    protected static string $resource = ContactResource::class;

    public Contact $record;

    public function mount(Contact|int|string $record): void
    {
        $this->record = $record instanceof Contact ? $record : Contact::findOrFail($record);
    }

    public function getRecordTitle(): string
    {
        return $this->record->display_name;
    }

    protected function notableType(): string
    {
        return Contact::class;
    }

    protected function recordResourceClass(): string
    {
        return ContactResource::class;
    }
}
