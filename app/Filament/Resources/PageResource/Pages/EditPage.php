<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    public function getTitle(): string
    {
        $typeLabel = match ($this->record->type) {
            'member' => 'Member Page',
            'post'   => 'Post',
            'event'  => 'Event Landing Page',
            default  => 'Page',
        };

        return 'Edit ' . $typeLabel;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
