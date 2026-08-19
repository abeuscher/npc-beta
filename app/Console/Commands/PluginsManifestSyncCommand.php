<?php

namespace App\Console\Commands;

use App\Plugins\DistributionManifest;
use Illuminate\Console\Command;

class PluginsManifestSyncCommand extends Command
{
    protected $signature = 'plugins:manifest-sync';

    protected $description = 'Regenerate config/plugins.php from distribution.json (the distribution manifest). Deterministic; DistributionManifestGuardTest fails while the generated file is stale.';

    public function handle(): int
    {
        $manifest = DistributionManifest::load();
        $target = config_path('plugins.php');
        $generated = $manifest->generatedConfig();

        if (is_file($target) && file_get_contents($target) === $generated) {
            $this->info('plugins:manifest-sync — config/plugins.php already matches distribution.json ('.count($manifest->providers()).' plugin(s)).');

            return self::SUCCESS;
        }

        file_put_contents($target, $generated);
        $this->info('plugins:manifest-sync — regenerated config/plugins.php from distribution.json ('.count($manifest->providers()).' plugin(s)).');

        return self::SUCCESS;
    }
}
