<?php

namespace App\Filament\Actions;

use App\Jobs\ImportBundleJob;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class ImportBundleAction
{
    /**
     * Build the "Import bundle" header action used on ListPages, ListPosts, and
     * ListTemplates. Accepts a JSON bundle or a self-contained zip (with media
     * bytes); both are detected by file type and run through the queued
     * ImportBundleJob (media-portability draft decisions #1/#2/#8 — no
     * synchronous fallback). Pages with matching slugs overwrite in place.
     */
    public static function make(): Action
    {
        return Action::make('importBundle')
            ->label('Import Bundle')
            ->icon('heroicon-o-arrow-up-tray')
            ->visible(fn () => auth()->user()?->can('update_page') ?? false)
            ->modalHeading('Import Content Bundle')
            ->modalDescription('Upload a JSON bundle or a self-contained .zip (with media) exported from this admin. Pages with matching slugs will be overwritten in place. Large bundles are imported in the background — you will be notified when it completes.')
            ->modalSubmitActionLabel('Import')
            ->form([
                Forms\Components\FileUpload::make('bundle_file')
                    ->label('Bundle file (JSON or .zip, up to 512 MB)')
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
                    ->body('Your bundle is being imported in the background. You will be notified when it completes.')
                    ->success()
                    ->send();
            });
    }
}
