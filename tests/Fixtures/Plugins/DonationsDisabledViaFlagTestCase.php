<?php

namespace Tests\Fixtures\Plugins;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Tests\TestCase;

/**
 * Boots the application with the Donations plugin DISABLED via the
 * per-install activation flag (contract surface 1's two-layer model).
 * Unlike DonationsRemovedTestCase, config('plugins') keeps its Donations
 * line — the installed superset is untouched; the disabled set is written to
 * config('plugin-activation.disabled') after configuration loads, exactly
 * where PLUGINS_DISABLED would put it, and PluginServiceProvider's filter
 * does the subtraction. Runtime-disabled must reproduce the strip-the-line
 * state the removal mirror asserts.
 */
abstract class DonationsDisabledViaFlagTestCase extends TestCase
{
    public function createApplication()
    {
        $app = require Application::inferBasePath() . '/bootstrap/app.php';

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app) {
            $app->make('config')->set('plugin-activation.disabled', ['donations']);
        });

        $app->make(Kernel::class)->bootstrap();

        // Disabled ≠ uninstalled (contract surface 5): a runtime-disabled
        // install keeps its schema and data. The provider never boots, so its
        // loadMigrationsFrom never runs — register the plugin's migration
        // path directly so migrate:fresh still creates the four donation
        // tables the data-kept assertions query (the DonationsRemovedTestCase
        // mechanics; session 390, arc D3).
        $app->make('migrator')->path($app->basePath('plugins/Donations/database/migrations'));

        return $app;
    }
}
