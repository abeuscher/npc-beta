<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use App\Models\SiteSetting;
use App\Services\ImportExport\ContentExporter;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use App\Filament\Resources\Pages\ReadOnlyAwareEditRecord;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EditPostDetails extends ReadOnlyAwareEditRecord
{
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

            Actions\ActionGroup::make([
                Actions\Action::make('exportPost')
                    ->label('Export Post')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn () => auth()->user()?->can('update_page') ?? false)
                    ->action(function (): StreamedResponse {
                        abort_unless(auth()->user()?->can('update_page'), 403);

                        $bundle   = app(ContentExporter::class)->exportPages([$this->record->id]);
                        $filename = now()->format('Ymd-His') . '-post-' . $this->record->slug . '.json';

                        return response()->streamDownload(
                            fn () => print(json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                            $filename,
                            ['Content-Type' => 'application/json'],
                        );
                    }),

                Actions\DeleteAction::make()
                    ->label('Delete Post'),
            ])
                ->icon('heroicon-m-ellipsis-vertical')
                ->tooltip('More actions'),
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
