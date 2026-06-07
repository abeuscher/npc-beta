<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Container-level liveness probe invoked by the Docker HEALTHCHECK. Reaching
 * handle() at all already proves the framework bootstrapped and every
 * auto-discovered service provider registered cleanly — that is the load-bearing
 * check (a stale package manifest naming a removed provider fatals before this
 * point, flipping the container unhealthy so `compose up --wait` catches a bad
 * image at docker_up instead of false-greening into migrate). The DB ping is an
 * additional readiness signal.
 *
 * NOT the Fleet Manager `/api/health` contract endpoint — that is the HTTP
 * surface FM polls (HealthController); this is the container's own probe.
 */
class HealthCheckCommand extends Command
{
    protected $signature = 'app:health-check';

    protected $description = 'Container liveness probe: boot the framework and ping the database (used by the Docker HEALTHCHECK).';

    public function handle(): int
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->error('app:health-check — UNHEALTHY: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info('app:health-check — OK');

        return self::SUCCESS;
    }
}
