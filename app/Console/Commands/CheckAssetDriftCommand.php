<?php

namespace App\Console\Commands;

use App\Services\AssetBuildService;
use Illuminate\Console\Command;

class CheckAssetDriftCommand extends Command
{
    protected $signature = 'assets:check-drift';

    protected $description = 'Report whether the served public CSS bundle still matches saved settings (stale-stylesheet drift guard).';

    public function handle(AssetBuildService $service): int
    {
        $reason = $service->bundleDrift();

        if ($reason === null) {
            $this->info('assets:check-drift — FRESH (served bundle matches current source).');

            return self::SUCCESS;
        }

        $this->error('assets:check-drift — STALE');
        $this->line($reason);

        return self::FAILURE;
    }
}
