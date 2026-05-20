<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\ImportExport\BundleArchive;
use App\Services\ImportExport\ContentImporter;
use App\Services\ImportExport\ImportLog;
use App\Services\ImportExport\InvalidImportBundleException;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Queued bundle import (media-portability draft decisions #2/#5/#8). Detects a
 * zip vs a JSON bundle by file type; a zip is extracted with BundleArchive's
 * zip-slip + zip-bomb guards and imported archive-first. The uploaded file and
 * any temp extraction dir are always removed. The operator is notified with
 * the ImportLog summary via the persistent bell.
 */
class ImportBundleJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * @param  array{merge_design?: bool, import_media?: bool, replace_duplicate_pages?: bool}  $opts
     */
    public function __construct(
        public string $relativePath,
        public int $userId,
        public array $opts = [],
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            Storage::disk('local')->delete($this->relativePath);

            return;
        }

        // So newly-created pages are authored by the operator who uploaded the
        // bundle (mirrors the prior synchronous behaviour).
        auth()->setUser($user);

        if (! Storage::disk('local')->exists($this->relativePath)) {
            Notification::make()
                ->title('Import failed')
                ->body('The uploaded bundle could not be found on the server.')
                ->danger()
                ->sendToDatabase($user);

            return;
        }

        $absPath = Storage::disk('local')->path($this->relativePath);
        $tempDir = null;

        try {
            $log = new ImportLog();

            if ($this->isZip($absPath)) {
                $extracted = app(BundleArchive::class)->extract($absPath);
                $tempDir   = $extracted['tempDir'];
                app(ContentImporter::class)->import(
                    $extracted['envelope'],
                    $log,
                    $this->opts,
                    $extracted['mediaRoot'],
                );
                $payload = $extracted['envelope']['payload'] ?? [];
            } else {
                $bundle = json_decode((string) Storage::disk('local')->get($this->relativePath), true);
                if (! is_array($bundle)) {
                    throw new InvalidImportBundleException('File is not a valid JSON bundle or zip.');
                }
                app(ContentImporter::class)->import($bundle, $log, $this->opts);
                $payload = $bundle['payload'] ?? [];
            }

            Notification::make()
                ->title('Import complete')
                ->body($this->summary($payload, $log))
                ->{$log->hasWarnings() ? 'warning' : 'success'}()
                ->sendToDatabase($user);
        } catch (InvalidImportBundleException $e) {
            Notification::make()
                ->title('Import rejected')
                ->body($e->getMessage())
                ->danger()
                ->sendToDatabase($user);
        } finally {
            Storage::disk('local')->delete($this->relativePath);
            if ($tempDir !== null) {
                $this->rmrf($tempDir);
            }
        }
    }

    private function isZip(string $absPath): bool
    {
        $fh = fopen($absPath, 'rb');
        if ($fh === false) {
            return false;
        }
        $magic = fread($fh, 4);
        fclose($fh);

        return $magic === "PK\x03\x04" || $magic === "PK\x05\x06" || $magic === "PK\x07\x08";
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function summary(array $payload, ImportLog $log): string
    {
        $parts = [];
        $parts[] = count($payload['pages'] ?? []) . ' page(s)';
        $parts[] = count($payload['templates'] ?? []) . ' template(s)';
        if (! empty($payload['design'])) {
            $parts[] = 'theme/design';
        }
        if (! empty($payload['media'])) {
            $parts[] = count($payload['media']) . ' media';
        }

        $body = 'Imported ' . implode(', ', $parts) . '.';

        if ($log->hasWarnings()) {
            $warnings = $log->warnings();
            $body .= ' ' . count($warnings) . ' warning(s):';
            foreach (array_slice($warnings, 0, 5) as $w) {
                $body .= "\n• " . $w['message'];
            }
            if (count($warnings) > 5) {
                $body .= "\n• … and " . (count($warnings) - 5) . ' more.';
            }
        }

        return $body;
    }

    private function rmrf(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
