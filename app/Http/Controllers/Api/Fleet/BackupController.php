<?php

namespace App\Http\Controllers\Api\Fleet;

use App\Http\Controllers\Api\Fleet\Concerns\HasContractVersion;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\BackupDestination\BackupDestination;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BackupController extends Controller
{
    use HasContractVersion;

    private const TIMEOUT_SECONDS = 600;
    private const SUCCESS_RECORD_PATH = 'fleet/last-backup-at';
    private const MAX_MESSAGE_LENGTH = 500;

    public function trigger(Request $request): JsonResponse
    {
        @set_time_limit(self::TIMEOUT_SECONDS);

        $startTime = microtime(true);
        $previousTimestamp = $this->readSuccessRecord();

        try {
            $exitCode = Artisan::call('backup:run');
        } catch (Throwable $e) {
            return $this->failed($previousTimestamp, $startTime, $this->sanitise($e->getMessage()));
        }

        if ($exitCode !== 0) {
            return $this->failed($previousTimestamp, $startTime, $this->sanitise(Artisan::output()));
        }

        $newTimestamp = $this->readSuccessRecord();

        if ($newTimestamp === null || $newTimestamp->timestamp < (int) $startTime) {
            return $this->failed(
                $newTimestamp ?? $previousTimestamp,
                $startTime,
                'backup:run exited cleanly but success record was not updated',
            );
        }

        return response()->json([
            'contract_version' => self::CONTRACT_VERSION,
            'status'           => 'success',
            'last_backup_at'   => $newTimestamp->toIso8601String(),
            'duration_ms'      => $this->durationMs($startTime),
            'message'          => null,
        ]);
    }

    public function blob(Request $request): StreamedResponse|JsonResponse
    {
        $disks = $this->resolveBackupDiskOrder();

        if ($disks === []) {
            return response()->json([
                'error'   => 'backup_destinations_not_configured',
                'message' => 'BACKUP_DISKS env var resolves to an empty disk list',
            ], 500);
        }

        $backupName = (string) config('backup.backup.name');

        foreach ($disks as $diskName) {
            try {
                $destination = BackupDestination::create($diskName, $backupName);

                if ($destination->connectionError !== null) {
                    continue;
                }

                $newest = $destination->backups()->newest();
            } catch (Throwable $e) {
                return response()->json([
                    'error'   => 'backup_disk_error',
                    'message' => $this->sanitise($e->getMessage()),
                ], 500);
            }

            if ($newest === null) {
                continue;
            }

            try {
                return Storage::disk($diskName)->download(
                    $newest->path(),
                    basename($newest->path()),
                    [
                        'Content-Type'  => 'application/zip',
                        'Cache-Control' => 'no-store',
                    ],
                );
            } catch (Throwable $e) {
                return response()->json([
                    'error'   => 'backup_disk_error',
                    'message' => $this->sanitise($e->getMessage()),
                ], 500);
            }
        }

        return response()->json([
            'error'   => 'no_backup_available',
            'message' => sprintf('No backup found for backup name "%s" on any configured disk', $backupName),
        ], 404);
    }

    /** @return array<int, string> */
    private function resolveBackupDiskOrder(): array
    {
        $disks = array_values(array_filter(
            array_map('trim', (array) config('backup.backup.destination.disks', [])),
            fn ($d) => is_string($d) && $d !== '',
        ));

        if (in_array('local', $disks, true)) {
            $disks = array_merge(
                ['local'],
                array_values(array_filter($disks, fn ($d) => $d !== 'local')),
            );
        }

        return $disks;
    }

    private function readSuccessRecord(): ?Carbon
    {
        $disk = Storage::disk('local');

        if (! $disk->exists(self::SUCCESS_RECORD_PATH)) {
            return null;
        }

        $iso = trim((string) $disk->get(self::SUCCESS_RECORD_PATH));
        if ($iso === '') {
            return null;
        }

        try {
            return Carbon::parse($iso);
        } catch (Throwable $e) {
            return null;
        }
    }

    private function failed(?Carbon $lastBackup, float $startTime, string $message): JsonResponse
    {
        return response()->json([
            'contract_version' => self::CONTRACT_VERSION,
            'status'           => 'failed',
            'last_backup_at'   => $lastBackup?->toIso8601String(),
            'duration_ms'      => $this->durationMs($startTime),
            'message'          => $message,
        ]);
    }

    private function durationMs(float $startTime): int
    {
        return (int) ((microtime(true) - $startTime) * 1000);
    }

    private function sanitise(string $raw): string
    {
        $stripped = str_replace(base_path() . '/', '', $raw);
        $singleLine = (string) preg_replace('/\s*\R\s*/', ' | ', $stripped);
        $singleLine = trim($singleLine);

        if (mb_strlen($singleLine) > self::MAX_MESSAGE_LENGTH) {
            return mb_substr($singleLine, 0, self::MAX_MESSAGE_LENGTH - 1) . '…';
        }

        return $singleLine;
    }
}
