<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use App\Filament\Resources\PostResource\Concerns\HasPostSecondaryActions;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use App\Filament\Resources\Pages\ReadOnlyAwareEditRecord;

class EditPostDetails extends ReadOnlyAwareEditRecord
{
    use HasPostSecondaryActions;

    protected static string $resource = PostResource::class;

    public function getTitle(): string
    {
        return 'Post Details';
    }

    public function form(Form $form): Form
    {
        return $form->schema(
            PostResource::metadataFormSchema(
                type: 'post',
                modelType: 'page',
                tagType: 'post',
                extraTitleFields: [
                    Forms\Components\Hidden::make('type')
                        ->default('post'),
                ],
                imageFields: [
                    SpatieMediaLibraryFileUpload::make('post_thumbnail')
                        ->label('Thumbnail image')
                        ->helperText('Used in blog listing widgets and social sharing.')
                        ->collection('post_thumbnail')
                        ->disk('public')
                        ->visibility('public')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                        ->nullable()
                        ->columnSpanFull(),

                    SpatieMediaLibraryFileUpload::make('post_header')
                        ->label('Header image')
                        ->helperText('Optional banner image displayed at the top of the post.')
                        ->collection('post_header')
                        ->disk('public')
                        ->visibility('public')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                        ->nullable()
                        ->columnSpanFull(),

                    SpatieMediaLibraryFileUpload::make('og_image')
                        ->label('Open Graph image')
                        ->helperText('Used for social sharing previews.')
                        ->collection('og_image')
                        ->disk('public')
                        ->visibility('public')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                        ->nullable()
                        ->columnSpanFull(),
                ],
                withSeo: true,
            )
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('editPost')
                ->label('Edit Post')
                ->icon('heroicon-o-pencil-square')
                ->url(fn () => PostResource::getUrl('edit', ['record' => $this->record])),

            $this->postSecondaryActionsGroup(),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            ListPosts::getUrl() => 'Blog Posts',
            EditPost::getUrl(['record' => $this->record]) => 'Edit Post',
            'Post Details',
        ];
    }
}
