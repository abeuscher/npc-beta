<?php

namespace App\Filament\Pages;

use App\Jobs\ExportBundleJob;
use App\Jobs\ImportBundleJob;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaLibraryPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationGroup = 'CMS';

    protected static ?string $navigationLabel = 'Media Library';

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?int $navigationSort = 8;

    protected static ?string $title = 'Media Library';

    protected static string $view = 'filament.pages.media-library';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_any_page') ?? false;
    }

    public function getBreadcrumbs(): array
    {
        return [
            self::getUrl() => 'Media Library',
        ];
    }

    /**
     * Standalone ID-preserving media export/import (session 303, Phase 2). The
     * page itself stays gated view_any_page; these mutating actions require
     * update_page (media-portability draft decision #7). All three ride the
     * same queued envelope/zip + notification primitive as the content bundle.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportAllMedia')
                ->label('Export all media')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->visible(fn () => auth()->user()?->can('update_page') ?? false)
                ->requiresConfirmation()
                ->modalHeading('Export all media')
                ->modalDescription('Builds an ID-preserving media bundle (originals only — conversions regenerate on import) in the background.')
                ->action(function (): void {
                    abort_unless(auth()->user()?->can('update_page'), 403);

                    ExportBundleJob::dispatch('all_media', [], (int) auth()->id(), 'all-media');

                    Notification::make()
                        ->title('Export queued')
                        ->body('Your media bundle is being built in the background. You will be notified when it is ready to download.')
                        ->success()
                        ->send();
                }),

            Action::make('importMediaBundle')
                ->label('Import media bundle')
                ->icon('heroicon-o-arrow-up-tray')
                ->visible(fn () => auth()->user()?->can('update_page') ?? false)
                ->modalHeading('Import media bundle')
                ->modalDescription('Upload an ID-preserving media bundle (.zip or JSON) exported from this admin. Existing ids are left untouched; new ids are seeded so by-reference page bundles resolve.')
                ->modalSubmitActionLabel('Import')
                ->form([
                    Forms\Components\FileUpload::make('bundle_file')
                        ->label('Media bundle (JSON or .zip, up to 512 MB)')
                        ->required()
                        ->acceptedFileTypes([
                            'application/json',
                            'text/plain',
                            'text/json',
                            'application/zip',
                            'application/x-zip-compressed',
                            'multipart/x-zip',
                        ])
                        ->maxSize(524288) // 512 MB
                        ->disk('local')
                        ->directory('imports/bundles')
                        ->visibility('private'),
                ])
                ->action(function (array $data): void {
                    abort_unless(auth()->user()?->can('update_page'), 403);

                    $relativePath = $data['bundle_file'] ?? null;
                    if (! $relativePath || ! Storage::disk('local')->exists($relativePath)) {
                        Notification::make()->title('Upload failed')->danger()->send();

                        return;
                    }

                    ImportBundleJob::dispatch($relativePath, (int) auth()->id());

                    Notification::make()
                        ->title('Import queued')
                        ->body('Your media bundle is being imported in the background. You will be notified when it completes.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Media::query())
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail')
                    ->label('')
                    ->circular(false)
                    ->width(48)
                    ->height(48)
                    ->getStateUsing(function (Media $record): ?string {
                        if (str_starts_with($record->mime_type, 'image/')) {
                            return $record->getUrl();
                        }
                        return null;
                    })
                    ->defaultImageUrl(fn () => null)
                    ->extraImgAttributes(['class' => 'rounded'])
                    ->sortable(false)
                    ->searchable(false),

                Tables\Columns\TextColumn::make('file_name')
                    ->label('File Name')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('mime_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(function (?string $state): string {
                        return match (true) {
                            $state === 'image/jpeg'    => 'JPEG',
                            $state === 'image/png'     => 'PNG',
                            $state === 'image/svg+xml' => 'SVG',
                            $state === 'image/webp'    => 'WebP',
                            $state === 'image/gif'     => 'GIF',
                            $state === 'application/pdf' => 'PDF',
                            default => strtoupper(str_replace(['image/', 'application/'], '', $state ?? 'unknown')),
                        };
                    }),

                Tables\Columns\TextColumn::make('size')
                    ->label('Size')
                    ->sortable()
                    ->formatStateUsing(function (int $state): string {
                        if ($state >= 1048576) {
                            return round($state / 1048576, 1) . ' MB';
                        }
                        return round($state / 1024, 1) . ' KB';
                    }),

                Tables\Columns\TextColumn::make('owner')
                    ->label('Owner')
                    ->getStateUsing(function (Media $record): string {
                        $model = $record->model;

                        if (! $model) {
                            return 'Unknown';
                        }

                        $label = match (true) {
                            $model instanceof \App\Models\PageWidget      => $model->label ?: 'Widget',
                            $model instanceof \App\Models\CollectionItem  => $model->data['title'] ?? 'Collection Item',
                            $model instanceof \App\Models\EmailTemplate   => $model->handle ?? 'Email Template',
                            default => class_basename($model),
                        };

                        $type = match (true) {
                            $model instanceof \App\Models\PageWidget      => 'Page Widget',
                            $model instanceof \App\Models\CollectionItem  => 'Collection Item',
                            $model instanceof \App\Models\EmailTemplate   => 'Email Template',
                            default => class_basename($model),
                        };

                        return "{$type}: {$label}";
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('collection_name')
                    ->label('Collection')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('conversions')
                    ->label('Conversions')
                    ->getStateUsing(function (Media $record): string {
                        $generated = $record->generated_conversions ?? [];
                        if (empty($generated)) {
                            return 'none';
                        }
                        $total = count($generated);
                        $completed = count(array_filter($generated));
                        if ($completed === $total) {
                            return 'complete';
                        }
                        return 'partial';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'complete' => 'success',
                        'partial'  => 'warning',
                        default    => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('model_type')
                    ->label('Owner Type')
                    ->options([
                        'App\\Models\\PageWidget'     => 'Page Widget',
                        'App\\Models\\CollectionItem' => 'Collection Item',
                        'App\\Models\\EmailTemplate'  => 'Email Template',
                    ]),

                Tables\Filters\SelectFilter::make('mime_group')
                    ->label('File Type')
                    ->options([
                        'images'    => 'Images',
                        'documents' => 'Documents',
                        'other'     => 'Other',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'images'    => $query->where('mime_type', 'like', 'image/%'),
                            'documents' => $query->where('mime_type', 'like', 'application/pdf'),
                            'other'     => $query->where('mime_type', 'not like', 'image/%')
                                                 ->where('mime_type', 'not like', 'application/pdf'),
                            default     => $query,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete media file')
                    ->modalDescription(fn (Media $record): string => "Are you sure you want to delete \"{$record->file_name}\"? This will remove the file from disk and cannot be undone.")
                    ->action(function (Media $record) {
                        abort_unless(auth()->user()?->can('update_page'), 403);
                        $record->delete();
                    })
                    ->visible(fn (): bool => auth()->user()?->can('update_page') ?? false),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('exportSelectedMedia')
                    ->label('Export selected media')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->visible(fn (): bool => auth()->user()?->can('update_page') ?? false)
                    ->deselectRecordsAfterCompletion()
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                        abort_unless(auth()->user()?->can('update_page'), 403);

                        ExportBundleJob::dispatch(
                            'media',
                            $records->pluck('id')->all(),
                            (int) auth()->id(),
                            'media-' . $records->count(),
                        );

                        Notification::make()
                            ->title('Export queued')
                            ->body('Your media bundle is being built in the background. You will be notified when it is ready to download.')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
