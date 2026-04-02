<?php

namespace App\Filament\Resources\TemplateResource\Pages;

use App\Filament\Resources\TemplateResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use App\Filament\Resources\Pages\ReadOnlyAwareEditRecord;

class EditContentTemplate extends ReadOnlyAwareEditRecord
{
    protected static string $resource = TemplateResource::class;

    public function getTitle(): string
    {
        return 'Edit Content Template';
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),

            Forms\Components\Textarea::make('description')
                ->rows(3)
                ->maxLength(1000),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->hidden(fn () => $this->record->is_default),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            TemplateResource::getUrl() => 'Templates',
            'Edit Content Template',
        ];
    }
}
