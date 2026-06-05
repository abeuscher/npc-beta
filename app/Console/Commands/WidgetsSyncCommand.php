<?php

namespace App\Console\Commands;

use App\Services\WidgetRegistry;
use Illuminate\Console\Command;

class WidgetsSyncCommand extends Command
{
    protected $signature = 'widgets:sync';

    protected $description = 'Sync widget definitions into the widget_types config_schema column. Idempotent; safe to run on every deploy.';

    public function handle(): int
    {
        $registry = app(WidgetRegistry::class);
        $registry->sync();

        $count = count($registry->all());
        $this->info("widgets:sync — synced {$count} widget definition(s) into widget_types.");

        return self::SUCCESS;
    }
}
