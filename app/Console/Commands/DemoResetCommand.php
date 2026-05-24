<?php

namespace App\Console\Commands;

use Database\Seeders\DemoBaselineSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class DemoResetCommand extends Command
{
    protected $signature = 'demo:reset {--soft : Re-seed the CRM baseline only, skipping the database rebuild}';

    protected $description = 'Reset the demo node to its curated, known-good baseline (intended to run daily on a cron).';

    public function handle(): int
    {
        if (! isDemoMode()) {
            $this->error('demo:reset refused — this install is not in demo mode (APP_ENV is not "demo").');
            $this->line('This guard exists so a misconfigured cron can never wipe a production database.');

            return self::FAILURE;
        }

        if ($this->option('soft')) {
            $this->info('Soft reset — re-seeding the CRM baseline (database left in place).');
        } else {
            $this->info('Full reset — rebuilding the database, then seeding the baseline.');
            Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true], $this->output);
        }

        (new DemoBaselineSeeder())->run();

        $this->info('Demo baseline restored.');

        return self::SUCCESS;
    }
}
