<?php

namespace App\Filament\Resources\TagResource\Pages;

use App\Filament\Resources\TagResource;
use Filament\Actions;
use App\Filament\Resources\Pages\ReadOnlyAwareEditRecord;

class EditTag extends ReadOnlyAwareEditRecord
{
    protected static string $resource = TagResource::class;

    public function getTitle(): string
    {
        return 'Edit ' . ucfirst($this->record->type) . ' Tag';
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
