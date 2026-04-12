<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected static string $view = 'filament.resources.post-resource.pages.edit-post';

    public function getTitle(): string
    {
        return 'Edit Post';
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
            Actions\Action::make('postDetails')
                ->label('Edit Post Details')
                ->icon('heroicon-o-cog-6-tooth')
                ->url(fn () => PostResource::getUrl('details', ['record' => $this->record])),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            ListPosts::getUrl() => 'Blog Posts',
            'Edit Post',
        ];
    }
}
