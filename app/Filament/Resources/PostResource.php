<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PostResource\Pages;
use App\Models\Page;
use App\Models\Template;
use App\Jobs\ExportBundleJob;
use App\Services\ImportExport\ContentExporter;
use App\Traits\HasPageBuilderForm;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PostResource extends Resource
{
    use HasPageBuilderForm;

    protected static ?string $model = Page::class;

    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';

    protected static ?string $navigationGroup = 'CMS';

    protected static ?string $navigationLabel = 'Blog Posts';

    protected static ?string $modelLabel = 'Post';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->where('type', 'post');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_post') ?? false;
    }

    /** A locked page/post is off-limits to users without the edit-lock permission (session 328). */
    protected static function isLockedFromCurrentUser(Page $record): bool
    {
        return $record->locked && ! (auth()->user()?->can('edit_locked_pages') ?? false);
    }

    public static function form(Form $form): Form
    {
        return $form->schema(
            static::metadataFormSchema(
                type: 'post',
                modelType: 'page',
                tagType: 'post',
                extraTitleFields: [
                    Forms\Components\Hidden::make('type')
                        ->default('post'),

                    Forms\Components\Select::make('content_template_id')
                        ->label('Content Template')
                        ->options(fn () => collect(['none' => 'None (blank)'])->merge(Template::content()->orderBy('name')->pluck('name', 'id')))
                        ->default(fn () => \App\Models\SiteSetting::get('default_content_template_post') ?: 'none')
                        ->helperText('Widget preset — applied once at creation.')
                        ->hiddenOn('edit')
                        ->columnSpanFull(),

                    // Edit lock — only holders of edit_locked_pages see or set it.
                    Forms\Components\Toggle::make('locked')
                        ->label('Lock editing (Published & Locked)')
                        ->helperText('When on, only users with the “edit locked pages” permission can edit this post. The post stays publicly visible.')
                        ->visible(fn (): bool => auth()->user()?->can('edit_locked_pages') ?? false)
                        ->columnSpanFull(),
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

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn ($record): string => static::getUrl('edit', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray'    => 'draft',
                        'success' => 'published',
                    ])
                    ->icon(fn (Page $record): ?string => $record->locked ? 'heroicon-m-lock-closed' : null)
                    ->tooltip(fn (Page $record): ?string => $record->locked
                        ? 'Locked — only editors with the lock permission can edit this post.'
                        : null),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Published At')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft'     => 'Draft',
                        'published' => 'Published',
                    ]),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->defaultSort('published_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->visible(fn () => auth()->user()?->can('update_page') ?? false)
                    ->requiresConfirmation()
                    ->modalHeading('Duplicate post')
                    ->modalDescription('Creates a draft copy of this post, including its blocks. The copy opens in the editor.')
                    ->modalSubmitActionLabel('Duplicate')
                    ->action(function (Page $record) {
                        abort_unless(auth()->user()?->can('update_page'), 403);

                        $copy = $record->duplicate();

                        Notification::make()
                            ->title('Post duplicated')
                            ->body('A draft copy was created. You are now editing the copy.')
                            ->success()
                            ->send();

                        return redirect(PostResource::getUrl('edit', ['record' => $copy]));
                    }),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('exportSelected')
                        ->label('Export selected')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->visible(fn () => auth()->user()?->can('update_page') ?? false)
                        ->deselectRecordsAfterCompletion()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): StreamedResponse {
                            abort_unless(auth()->user()?->can('update_page'), 403);

                            $ids      = $records->pluck('id')->all();
                            $bundle   = app(ContentExporter::class)->exportPages($ids);
                            $filename = now()->format('Ymd-His') . '-posts-' . count($ids) . '.json';

                            return response()->streamDownload(
                                fn () => print(json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                                $filename,
                                ['Content-Type' => 'application/json'],
                            );
                        }),

                    Tables\Actions\BulkAction::make('exportSelectedWithMedia')
                        ->label('Export with media (zip)')
                        ->icon('heroicon-o-archive-box-arrow-down')
                        ->visible(fn () => auth()->user()?->can('update_page') ?? false)
                        ->deselectRecordsAfterCompletion()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            abort_unless(auth()->user()?->can('update_page'), 403);

                            ExportBundleJob::dispatch(
                                'pages',
                                $records->pluck('id')->all(),
                                (int) auth()->id(),
                                'posts-' . $records->count(),
                                ['with_media' => true],
                            );

                            Notification::make()
                                ->title('Export queued')
                                ->body('Your bundle is being built in the background. You will be notified when it is ready to download.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('exportSelectedWithTheme')
                        ->label('Export with theme (JSON)')
                        ->icon('heroicon-o-paint-brush')
                        ->visible(fn () => auth()->user()?->can('update_page') ?? false)
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation()
                        ->modalHeading('Export selected posts with theme')
                        ->modalDescription('The exported bundle will include this site\'s theme colours, typography, and button styles alongside the posts. When imported elsewhere, the importer will surface a "Replace site theme" prompt — opting in will overwrite the target site\'s Theme editor settings.')
                        ->modalSubmitActionLabel('Export with theme')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): StreamedResponse {
                            abort_unless(auth()->user()?->can('update_page'), 403);

                            $ids      = $records->pluck('id')->all();
                            $bundle   = app(ContentExporter::class)->exportPages($ids, ['with_design' => true]);
                            $filename = now()->format('Ymd-His') . '-posts-' . count($ids) . '-with-theme.json';

                            return response()->streamDownload(
                                fn () => print(json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                                $filename,
                                ['Content-Type' => 'application/json'],
                            );
                        }),

                    Tables\Actions\BulkAction::make('exportSelectedWithThemeAndMedia')
                        ->label('Export with theme & media (zip)')
                        ->icon('heroicon-o-rectangle-stack')
                        ->visible(fn () => auth()->user()?->can('update_page') ?? false)
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation()
                        ->modalHeading('Export selected posts with theme & media')
                        ->modalDescription('The exported bundle will include this site\'s theme colours, typography, and button styles plus all referenced media files alongside the posts. When imported elsewhere, the importer will surface a "Replace site theme" prompt — opting in will overwrite the target site\'s Theme editor settings.')
                        ->modalSubmitActionLabel('Export with theme & media')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            abort_unless(auth()->user()?->can('update_page'), 403);

                            ExportBundleJob::dispatch(
                                'pages',
                                $records->pluck('id')->all(),
                                (int) auth()->id(),
                                'posts-' . $records->count() . '-full',
                                ['with_design' => true, 'with_media' => true],
                            );

                            Notification::make()
                                ->title('Export queued')
                                ->body('Your bundle is being built in the background. You will be notified when it is ready to download.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $records->each(function (Page $record) {
                                if (self::isLockedFromCurrentUser($record)) {
                                    return;
                                }
                                $record->delete();
                            });
                        }),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $records->each(function (Page $record) {
                                if (self::isLockedFromCurrentUser($record)) {
                                    return;
                                }
                                $record->forceDelete();
                            });
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'   => Pages\ListPosts::route('/'),
            'create'  => Pages\CreatePost::route('/create'),
            'edit'    => Pages\EditPost::route('/{record}/edit'),
            'details' => Pages\EditPostDetails::route('/{record}/details'),
        ];
    }
}
