<?php

namespace App\Http\Controllers\Api\Fleet;

use App\Http\Controllers\Api\Fleet\Concerns\HasContractVersion;
use App\Http\Controllers\Controller;
use App\Services\Billing\BillingStateReader;
use App\Services\Billing\SuspensionState;
use App\Services\DataHygieneAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HealthController extends Controller
{
    use HasContractVersion;

    private const DISK_YELLOW_THRESHOLD = 80;
    private const DISK_RED_THRESHOLD = 95;

    /**
     * Total accumulated-cruft count at/above which data_hygiene reports a soft
     * yellow. Informational only (never red, never drags overall) — a generous,
     * adjustable bound so a heavily-crufted node surfaces for operator attention.
     */
    private const DATA_HYGIENE_YELLOW_THRESHOLD = 100;

    /** Cache key + TTL keeping the FS-walking, media-scanning audit off the hot path. */
    public const DATA_HYGIENE_CACHE_KEY = 'fleet.health.data_hygiene.counts';
    private const DATA_HYGIENE_CACHE_TTL = 600; // seconds (10 min)

    public function index(): JsonResponse
    {
        $subchecks = [
            'app'            => $this->checkApp(),
            'database'       => $this->checkDatabase(),
            'redis'          => $this->checkRedis(),
            'disk'           => $this->checkDisk(),
            'last_backup_at' => $this->checkLastBackupAt(),
            'version'        => $this->checkVersion(),
            'data_hygiene'   => $this->checkDataHygiene(),
            'suspension'     => $this->checkSuspension(),
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

    /**
     * Count-only data-hygiene signal (Fleet Data Hygiene track, Phase 2). The
     * value is the four-category non-PII aggregate from DataHygieneAudit::counts()
     * — orphan event pages, residual scrub records, orphan media directories,
     * dead-owner media rows. PRIVACY BOUNDARY: counts only ever cross the FM wire;
     * no record, slug, title, id, or path is ever surfaced here. The deep records
     * mode stays node-local (the `app:data-hygiene --deep` CLI).
     *
     * counts() walks the media filesystem and scans every media row, and
     * /api/health is polled ~once a minute per node, so the result is cached
     * behind a short TTL: cruft accumulates over hours/days, so a 10-minute-stale
     * count is operationally identical to a live one, while the expensive path
     * runs at most once per TTL window regardless of poll rate. (A scheduled
     * precompute is not an option — the worker runs no schedule:work; see
     * docs/app-reference.md "Scheduler runner — known gap".)
     *
     * Informational: a cruft pile is never a health emergency, so this subcheck
     * never goes red and is excluded from the worst-of overall status
     * (see overallStatus()). At most a soft yellow above a generous total.
     */
    private function checkDataHygiene(): array
    {
        $counts = Cache::remember(
            self::DATA_HYGIENE_CACHE_KEY,
            self::DATA_HYGIENE_CACHE_TTL,
            fn () => app(DataHygieneAudit::class)->counts(),
        );

        $total = array_sum($counts);

        $status = $total >= self::DATA_HYGIENE_YELLOW_THRESHOLD ? 'yellow' : 'green';

        return [
            'status'    => $status,
            'value'     => $counts,
            'threshold' => self::DATA_HYGIENE_YELLOW_THRESHOLD,
            'message'   => $status === 'yellow'
                ? "{$total} items of accumulated cruft (counts only)"
                : null,
        ];
    }

    /**
     * Suspension state signal (client billing, contract v2.6.0). Reports the
     * node's currently-*enforced* suspension state (from config — the pushed
     * SUSPENSION_STATE flag, fail-safe-resolved) plus the pushed billing-state
     * document's `as_of` (null when no document), so Fleet Manager gets read-back
     * verification that a suspension push took effect.
     *
     * Informational: a deliberately-suspended node is not an unhealthy node, so
     * this subcheck is `green` when `none`, a soft `yellow` otherwise, NEVER
     * `red`, and is excluded from the worst-of overall status (see
     * overallStatus()) — exactly the data_hygiene posture. Value is a state
     * string + a timestamp only: no email, no amounts, nothing personal, within
     * the contract's counts-only / no-PII wire discipline.
     */
    private function checkSuspension(): array
    {
        $state = SuspensionState::current();

        return [
            'status'    => $state === SuspensionState::None ? 'green' : 'yellow',
            'value'     => [
                'state'               => $state->value,
                'billing_state_as_of' => app(BillingStateReader::class)->read()->asOf(),
            ],
            'threshold' => null,
            'message'   => $state === SuspensionState::None
                ? null
                : "node suspension state: {$state->value}",
        ];
    }

    private function overallStatus(array $subchecks): string
    {
        // data_hygiene and suspension are informational — accumulated cruft is
        // not a node-health emergency, and a deliberately-suspended node is not an
        // unhealthy one — so both are excluded from the worst-of derivation. Their
        // own statuses still surface a soft yellow for attention; they just never
        // drag the top-level status. Every other subcheck rolls in.
        $statuses = array_column(
            array_diff_key($subchecks, ['data_hygiene' => true, 'suspension' => true]),
            'status',
        );

        if (in_array('red', $statuses, true)) {
            return 'red';
        }

        if (in_array('yellow', $statuses, true) || in_array('unknown', $statuses, true)) {
            return 'yellow';
        }

        return 'green';
    }
}
