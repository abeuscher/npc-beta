<?php

namespace App\Console\Commands;

use App\Services\WidgetRegistry;
use Illuminate\Console\Command;

class WidgetsManifestJsonCommand extends Command
{
    protected $signature = 'widgets:manifest-json';

    protected $description = 'Emit the widget registry manifests as JSON on stdout.';

    public function handle(): int
    {
        $this->line(json_encode(app(WidgetRegistry::class)->manifests(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
