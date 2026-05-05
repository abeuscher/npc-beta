<?php

namespace App\Http\Controllers\Api\Fleet;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BackupController extends Controller
{
    public const CONTRACT_VERSION = '2.2.0';

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
