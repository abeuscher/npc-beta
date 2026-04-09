<?php

namespace App\Filament\Actions;

use App\Services\ImportExport\ContentImporter;
use App\Services\ImportExport\ImportLog;
use App\Services\ImportExport\InvalidImportBundleException;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class ImportBundleAction
{
    /**
     * Build the "Import bundle" header action used on ListPages, ListPosts, and ListTemplates.
     * Accepts a single-record bundle or a multi-record bundle — both go through the same
     * ContentImporter::import() path.
     */
    public static function make(): Action
    {
        return Action::make('importBundle')
            ->label('Import Bundle')
            ->icon('heroicon-o-arrow-up-tray')
            ->visible(fn () => auth()->user()?->can('update_page') ?? false)
            ->modalHeading('Import Content Bundle')
            ->modalDescription('Upload a JSON bundle exported from this admin. Pages with matching slugs will be overwritten in place.')
            ->modalSubmitActionLabel('Import')
            ->form([
                Forms\Components\FileUpload::make('bundle_file')
                    ->label('Bundle file (JSON)')
                    ->required()
                    ->acceptedFileTypes(['application/json', 'text/plain', 'text/json'])
                    ->maxSize(51200) // 50 MB
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

                try {
                    $contents = Storage::disk('local')->get($relativePath);
                    $bundle   = json_decode($contents, true);

                    if (! is_array($bundle)) {
                        Notification::make()
                            ->title('Invalid bundle')
                            ->body('File is not valid JSON.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $log = new ImportLog();
                    app(ContentImporter::class)->import($bundle, $log);

                    $payload   = $bundle['payload'] ?? [];
                    $pageCount = count($payload['pages'] ?? []);
                    $tplCount  = count($payload['templates'] ?? []);

                    $body = "Imported {$pageCount} page(s), {$tplCount} template(s).";
                    if ($log->hasWarnings()) {
                        $body .= ' ' . count($log->warnings()) . ' warning(s):';
                        foreach (array_slice($log->warnings(), 0, 5) as $w) {
                            $body .= "\n• " . $w['message'];
                        }
                        if (count($log->warnings()) > 5) {
                            $body .= "\n• … and " . (count($log->warnings()) - 5) . ' more.';
                        }
                    }

                    Notification::make()
                        ->title('Import complete')
                        ->body($body)
                        ->{$log->hasWarnings() ? 'warning' : 'success'}()
                        ->persistent()
                        ->send();
                } catch (InvalidImportBundleException $e) {
                    Notification::make()
                        ->title('Import rejected')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                } finally {
                    Storage::disk('local')->delete($relativePath);
                }
            });
    }
}
