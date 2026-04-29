<?php

namespace App\Http\Controllers\Api\Fleet;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HealthController extends Controller
{
    public const CONTRACT_VERSION = '1.2.0';

    private const DISK_YELLOW_THRESHOLD = 80;
    private const DISK_RED_THRESHOLD = 95;

    public function index(): JsonResponse
    {
        $subchecks = [
            'app'            => $this->checkApp(),
            'database'       => $this->checkDatabase(),
            'redis'          => $this->checkRedis(),
            'disk'           => $this->checkDisk(),
            'last_backup_at' => $this->checkLastBackupAt(),
            'version'        => $this->checkVersion(),
        ];

        return response()->json([
            'status'           => $this->overallStatus($subchecks),
            'version'          => (string) config('fleet.agent.app_version'),
            'timestamp'        => now()->toIso8601String(),
            'contract_version' => self::CONTRACT_VERSION,
            'subchecks'        => $subchecks,
        ]);
    }

    private function checkApp(): array
    {
        return [
            'status'    => 'green',
            'value'     => 'responding',
            'threshold' => null,
            'message'   => null,
        ];
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return [
                'status'    => 'green',
                'value'     => 'reachable',
                'threshold' => null,
                'message'   => null,
            ];
        } catch (Throwable $e) {
            return [
                'status'    => 'red',
                'value'     => 'unreachable',
                'threshold' => null,
                'message'   => class_basename($e),
            ];
        }
    }

    private function checkRedis(): array
    {
        try {
            Redis::ping();

            return [
                'status'    => 'green',
                'value'     => 'reachable',
                'threshold' => null,
                'message'   => null,
            ];
        } catch (Throwable $e) {
            return [
                'status'    => 'red',
                'value'     => 'unreachable',
                'threshold' => null,
                'message'   => class_basename($e),
            ];
        }
    }

    private function checkDisk(): array
    {
        $free = @disk_free_space('/');
        $total = @disk_total_space('/');

        if ($free === false || $total === false || $total <= 0) {
            return [
                'status'    => 'red',
                'value'     => null,
                'threshold' => [self::DISK_YELLOW_THRESHOLD, self::DISK_RED_THRESHOLD],
                'message'   => 'unable to read disk usage',
            ];
        }

        $percentUsed = (int) floor((($total - $free) / $total) * 100);

        $status = match (true) {
            $percentUsed >= self::DISK_RED_THRESHOLD    => 'red',
            $percentUsed >= self::DISK_YELLOW_THRESHOLD => 'yellow',
            default                                     => 'green',
        };

        return [
            'status'    => $status,
            'value'     => $percentUsed,
            'threshold' => [self::DISK_YELLOW_THRESHOLD, self::DISK_RED_THRESHOLD],
            'message'   => null,
        ];
    }

    private function checkLastBackupAt(): array
    {
        $threshold = [24, 36];

        $disk = Storage::disk('local');

        if (! $disk->exists('fleet/last-backup-at')) {
            return [
                'status'    => 'unknown',
                'value'     => null,
                'threshold' => $threshold,
                'message'   => 'no successful backup yet',
            ];
        }

        $iso = trim((string) $disk->get('fleet/last-backup-at'));

        if ($iso === '') {
            return [
                'status'    => 'unknown',
                'value'     => null,
                'threshold' => $threshold,
                'message'   => 'last-backup-at file is empty',
            ];
        }

        try {
            $timestamp = Carbon::parse($iso);
        } catch (Throwable $e) {
            return [
                'status'    => 'unknown',
                'value'     => null,
                'threshold' => $threshold,
                'message'   => 'last-backup-at file unparseable',
            ];
        }

        $hours = $timestamp->diffInHours(now(), absolute: true);

        $status = match (true) {
            $hours > $threshold[1]  => 'red',
            $hours >= $threshold[0] => 'yellow',
            default                 => 'green',
        };

        return [
            'status'    => $status,
            'value'     => $timestamp->toIso8601String(),
            'threshold' => $threshold,
            'message'   => null,
        ];
    }

    private function checkVersion(): array
    {
        return [
            'status'    => 'green',
            'value'     => (string) config('fleet.agent.app_version'),
            'threshold' => null,
            'message'   => null,
        ];
    }

    private function overallStatus(array $subchecks): string
    {
        $statuses = array_column($subchecks, 'status');

        if (in_array('red', $statuses, true)) {
            return 'red';
        }

        if (in_array('yellow', $statuses, true) || in_array('unknown', $statuses, true)) {
            return 'yellow';
        }

        return 'green';
    }
}
