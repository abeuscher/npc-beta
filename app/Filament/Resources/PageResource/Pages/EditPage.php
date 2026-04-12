<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use App\Models\SiteSetting;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected static string $view = 'filament.resources.page-resource.pages.edit-page';

    public function getTitle(): string
    {
        $typeLabel = match ($this->record->type) {
            'system' => 'System Page',
            'member' => 'Member Page',
            'post'   => 'Post',
            'event'  => 'Event Landing Page',
            default  => 'Page',
        };

        return 'Edit ' . $typeLabel;
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pageDetails')
                ->label('Edit Page Details')
                ->icon('heroicon-o-cog-6-tooth')
                ->url(fn () => PageResource::getUrl('details', ['record' => $this->record])),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            ListPages::getUrl() => 'Pages',
            'Edit Page',
        ];
    }
}
