<?php

namespace Tests\Fixtures\Plugins;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Tests\TestCase;

/**
 * Boots the application with the Forms plugin DISABLED via the per-install
 * activation flag (contract surface 1's two-layer model). Unlike
 * FormsRemovedTestCase, config('plugins') keeps its Forms line — the
 * installed superset is untouched; the disabled set is written to
 * config('plugin-activation.disabled') after configuration loads, exactly
 * where PLUGINS_DISABLED would put it, and PluginServiceProvider's filter
 * does the subtraction. Runtime-disabled must reproduce the strip-the-line
 * state the removal mirror asserts.
 */
abstract class FormsDisabledViaFlagTestCase extends TestCase
{
    public function createApplication()
    {
        $app = require Application::inferBasePath() . '/bootstrap/app.php';

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app) {
            $app->make('config')->set('plugin-activation.disabled', ['forms']);
        });

        $app->make(Kernel::class)->bootstrap();

        // Disabled ≠ uninstalled (contract surface 5): the provider never
        // registers, so its loadMigrationsFrom never runs — register the
        // plugin's migration path directly so the two form tables exist for
        // the data-kept assertions (the MembershipsDisabledViaFlagTestCase
        // mechanics; session 397, arc D7).
        $app->make('migrator')->path($app->basePath('vendor/nonprofitcrm/forms/database/migrations'));

        return $app;
    }
}
