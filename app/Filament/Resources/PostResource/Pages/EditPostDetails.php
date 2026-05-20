<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use App\Jobs\ExportBundleJob;
use App\Services\ImportExport\ContentExporter;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
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

                Actions\Action::make('exportPostWithMedia')
                    ->label('Export Post with media (zip)')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->visible(fn () => auth()->user()?->can('update_page') ?? false)
                    ->action(function (): void {
                        abort_unless(auth()->user()?->can('update_page'), 403);

                        ExportBundleJob::dispatch(
                            'pages',
                            [$this->record->id],
                            (int) auth()->id(),
                            'post-' . $this->record->slug,
                            ['with_media' => true],
                        );

                        Notification::make()
                            ->title('Export queued')
                            ->body('Your bundle is being built in the background. You will be notified when it is ready to download.')
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('exportPostWithTheme')
                    ->label('Export Post with theme (JSON)')
                    ->icon('heroicon-o-paint-brush')
                    ->visible(fn () => auth()->user()?->can('update_page') ?? false)
                    ->requiresConfirmation()
                    ->modalHeading('Export Post with theme')
                    ->modalDescription('The exported bundle will include this site\'s theme colours, typography, and button styles alongside the post. When imported elsewhere, the importer will surface a "Replace site theme" prompt — opting in will overwrite the target site\'s Theme editor settings.')
                    ->modalSubmitActionLabel('Export with theme')
                    ->action(function (): StreamedResponse {
                        abort_unless(auth()->user()?->can('update_page'), 403);

                        $bundle   = app(ContentExporter::class)->exportPages([$this->record->id], ['with_design' => true]);
                        $filename = now()->format('Ymd-His') . '-post-' . $this->record->slug . '-with-theme.json';

                        return response()->streamDownload(
                            fn () => print(json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                            $filename,
                            ['Content-Type' => 'application/json'],
                        );
                    }),

                Actions\Action::make('exportPostWithThemeAndMedia')
                    ->label('Export Post with theme & media (zip)')
                    ->icon('heroicon-o-rectangle-stack')
                    ->visible(fn () => auth()->user()?->can('update_page') ?? false)
                    ->requiresConfirmation()
                    ->modalHeading('Export Post with theme & media')
                    ->modalDescription('The exported bundle will include this site\'s theme colours, typography, and button styles plus all referenced media files alongside the post. When imported elsewhere, the importer will surface a "Replace site theme" prompt — opting in will overwrite the target site\'s Theme editor settings.')
                    ->modalSubmitActionLabel('Export with theme & media')
                    ->action(function (): void {
                        abort_unless(auth()->user()?->can('update_page'), 403);

                        ExportBundleJob::dispatch(
                            'pages',
                            [$this->record->id],
                            (int) auth()->id(),
                            'post-' . $this->record->slug . '-full',
                            ['with_design' => true, 'with_media' => true],
                        );

                        Notification::make()
                            ->title('Export queued')
                            ->body('Your bundle is being built in the background. You will be notified when it is ready to download.')
                            ->success()
                            ->send();
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
