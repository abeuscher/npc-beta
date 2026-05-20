<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages;
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

class PageResource extends Resource
{
    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_any_page') ?? false;
    }

    use HasPageBuilderForm;

    protected static ?string $model = Page::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'CMS';

    protected static ?int $navigationSort = 2;

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return $record->type !== 'system' && (auth()->user()?->can('delete_page') ?? false);
    }


    public static function form(Form $form): Form
    {
        return $form->schema(
            static::metadataFormSchema(
                type: 'page',
                modelType: 'page',
                tagType: 'page',
                extraTitleFields: [
                    // System pages: show the full stored slug as read-only text.
                    Forms\Components\Placeholder::make('system_slug_display')
                        ->label('Slug')
                        ->content(fn ($record): string => $record?->slug ?? '—')
                        ->helperText('Slug is locked — system page slugs can only be changed via the System Pages Prefix setting.')
                        ->visibleOn('edit')
                        ->hidden(fn (Forms\Get $get): bool => $get('type') !== 'system')
                        ->columnSpanFull(),

                    // Shown on create only — editable type selection.
                    Forms\Components\Select::make('type')
                        ->label('Page Type')
                        ->options([
                            'default' => 'Web Page',
                            'member'  => 'Member Page',
                        ])
                        ->default('default')
                        ->hiddenOn('edit')
                        ->columnSpanFull(),

                    // Shown on edit only for system pages — read-only type label.
                    Forms\Components\Placeholder::make('type_display')
                        ->label('Page Type')
                        ->content('System Page')
                        ->visibleOn('edit')
                        ->hidden(fn (Forms\Get $get): bool => $get('type') !== 'system')
                        ->columnSpanFull(),

                    // Content template — create only, used to prepopulate widgets.
                    Forms\Components\Select::make('content_template_id')
                        ->label('Content Template')
                        ->options(fn () => collect(['none' => 'None (blank)'])->merge(Template::content()->orderBy('name')->pluck('name', 'id')))
                        ->default(fn () => \App\Models\SiteSetting::get('default_content_template_default') ?: 'none')
                        ->helperText('Widget preset — applied once at creation.')
                        ->hiddenOn('edit')
                        ->columnSpanFull(),
                ],
                templateField: Forms\Components\Select::make('template_id')
                    ->label('Page Template')
                    ->options(fn () => Template::page()->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id'))
                    ->default(fn () => Template::page()->where('is_default', true)->value('id'))
                    ->helperText('Header, footer, and styling.'),
                imageFields: [
                    SpatieMediaLibraryFileUpload::make('post_thumbnail')
                        ->label('Thumbnail image')
                        ->helperText('Used in listing widgets and social sharing.')
                        ->collection('post_thumbnail')
                        ->disk('public')
                        ->visibility('public')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                        ->nullable()
                        ->columnSpanFull(),

                    SpatieMediaLibraryFileUpload::make('post_header')
                        ->label('Header image')
                        ->helperText('Used as the header background when a widget opts into the current page\'s header image.')
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

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->where('type', '!=', 'event')
            ->where('type', '!=', 'post');
    }

    public static function resolveRecordRouteBinding(int | string $key): ?\Illuminate\Database\Eloquent\Model
    {
        // getEloquentQuery() excludes event/post pages to keep the CMS list clean,
        // but that scope also blocks direct edits of event landing pages.
        // Resolve records without the type filter so any page type can be edited directly.
        return \App\Models\Page::where('id', $key)->first();
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

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'gray'    => 'default',
                        'info'    => 'post',
                        'warning' => 'event',
                        'success' => 'member',
                        'danger'  => 'system',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'post'    => 'Post',
                        'event'   => 'Event',
                        'member'  => 'Member',
                        'system'  => 'System',
                        default   => 'Page',
                    }),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray'    => 'draft',
                        'success' => 'published',
                    ]),

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

                Tables\Filters\SelectFilter::make('type')
                    ->label('Type')
                    ->multiple()
                    ->options([
                        'default' => 'Page',
                        'post'    => 'Post',
                        'event'   => 'Event',
                        'member'  => 'Member',
                        'system'  => 'System',
                    ])
                    ->default(['default']),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->filtersFormColumns(3)
            ->defaultSort('updated_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (Page $record): bool => $record->type === 'system')
                    ->modalDescription(fn (Page $record): ?string => match ($record->type) {
                        'member' => 'Warning: Deleting this page may render the member portal unusable. Are you sure you want to proceed?',
                        default  => null,
                    }),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make()
                    ->hidden(fn (Page $record): bool => $record->type === 'system')
                    ->modalDescription(fn (Page $record): ?string => match ($record->type) {
                        'member' => 'Warning: Permanently deleting this page may render the member portal unusable.',
                        default  => null,
                    }),
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
                            $filename = now()->format('Ymd-His') . '-pages-' . count($ids) . '.json';

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
                                'pages-' . $records->count(),
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
                        ->modalHeading('Export selected pages with theme')
                        ->modalDescription('The exported bundle will include this site\'s theme colours, typography, and button styles alongside the pages. When imported elsewhere, the importer will surface a "Replace site theme" prompt — opting in will overwrite the target site\'s Theme editor settings.')
                        ->modalSubmitActionLabel('Export with theme')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): StreamedResponse {
                            abort_unless(auth()->user()?->can('update_page'), 403);

                            $ids      = $records->pluck('id')->all();
                            $bundle   = app(ContentExporter::class)->exportPages($ids, ['with_design' => true]);
                            $filename = now()->format('Ymd-His') . '-pages-' . count($ids) . '-with-theme.json';

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
                        ->modalHeading('Export selected pages with theme & media')
                        ->modalDescription('The exported bundle will include this site\'s theme colours, typography, and button styles plus all referenced media files alongside the pages. When imported elsewhere, the importer will surface a "Replace site theme" prompt — opting in will overwrite the target site\'s Theme editor settings.')
                        ->modalSubmitActionLabel('Export with theme & media')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            abort_unless(auth()->user()?->can('update_page'), 403);

                            ExportBundleJob::dispatch(
                                'pages',
                                $records->pluck('id')->all(),
                                (int) auth()->id(),
                                'pages-' . $records->count() . '-full',
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
                                if ($record->type !== 'system') {
                                    $record->delete();
                                }
                            });
                        }),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $records->each(function (Page $record) {
                                if ($record->type !== 'system') {
                                    $record->forceDelete();
                                }
                            });
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'   => Pages\ListPages::route('/'),
            'create'  => Pages\CreatePage::route('/create'),
            'edit'    => Pages\EditPage::route('/{record}/edit'),
            'details' => Pages\EditPageDetails::route('/{record}/details'),
        ];
    }
}
