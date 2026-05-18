<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\ImportExport\BundleArchive;
use App\Services\ImportExport\ContentExporter;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Queued, stored-artifact bundle export (media-portability draft decision #5).
 * Serializes the envelope, writes a self-contained zip to the private local
 * disk, and notifies the requesting operator with a gated download action — no
 * streamDownload anywhere on this path.
 */
class ExportBundleJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * @param  'pages'|'templates'|'design'|'media'|'all_media'  $kind
     * @param  array<int, int|string>  $ids
     */
    public function __construct(
        public string $kind,
        public array $ids,
        public int $userId,
        public string $label,
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        $exporter = app(ContentExporter::class);

        try {
            $envelope = match ($this->kind) {
                'pages'     => $exporter->exportPages($this->ids),
                'templates' => $exporter->exportTemplates($this->ids),
                'design'    => $exporter->exportDesign(),
                'media'     => $exporter->exportMedia($this->ids),
                'all_media' => $exporter->exportAllMedia(),
                default     => throw new \InvalidArgumentException("Unknown export kind {$this->kind}."),
            };

            $token        = (string) Str::uuid();
            $friendly     = now()->format('Ymd-His') . '-' . Str::slug($this->label) . '.zip';
            $relativePath = "exports/bundles/{$token}/{$friendly}";

            app(BundleArchive::class)->build(
                $envelope,
                Storage::disk('local')->path($relativePath),
            );

            Notification::make()
                ->title('Export ready')
                ->body("Your bundle “{$friendly}” is ready to download.")
                ->success()
                ->actions([
                    Action::make('download')
                        ->label('Download bundle')
                        ->url(route('filament.admin.exports.bundle.download', ['token' => $token]), shouldOpenInNewTab: true)
                        ->markAsRead(),
                ])
                ->sendToDatabase($user);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Export failed')
                ->body('The bundle could not be built: ' . $e->getMessage())
                ->danger()
                ->sendToDatabase($user);

            throw $e;
        }
    }
}
