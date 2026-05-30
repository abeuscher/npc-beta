<?php

namespace App\Console\Commands;

use App\Models\Page;
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

        // Lock every page on the demo node so the shared `demo` account cannot
        // edit the sample site's content (pages, posts, event landing pages, and
        // the header/footer system pages — all the Page model). Idempotent and
        // re-applied on every reset so the lock survives the daily wipe.
        Page::query()->update(['locked' => true]);

        $this->info('Demo baseline restored and pages locked.');

        return self::SUCCESS;
    }
}
