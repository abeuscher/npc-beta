<?php

namespace Tests\Fixtures\Plugins;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Tests\TestCase;

/**
 * Boots the application with the Memberships plugin DISABLED via the
 * per-install activation flag (contract surface 1's two-layer model).
 * Unlike MembershipsRemovedTestCase, config('plugins') keeps its Memberships
 * line — the installed superset is untouched; the disabled set is written to
 * config('plugin-activation.disabled') after configuration loads, exactly
 * where PLUGINS_DISABLED would put it, and PluginServiceProvider's filter
 * does the subtraction. Runtime-disabled must reproduce the strip-the-line
 * state the removal mirror asserts.
 */
abstract class MembershipsDisabledViaFlagTestCase extends TestCase
{
    public function createApplication()
    {
        $app = require Application::inferBasePath() . '/bootstrap/app.php';

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app) {
            $app->make('config')->set('plugin-activation.disabled', ['memberships']);
        });

        $app->make(Kernel::class)->bootstrap();

        // Disabled ≠ uninstalled (contract surface 5): the provider never
        // registers, so its loadMigrationsFrom never runs — register the
        // plugin's migration path directly so the two membership tables exist
        // for the data-kept assertions (the DonationsDisabledViaFlagTestCase
        // mechanics; session 393, arc D4).
        $app->make('migrator')->path($app->basePath('vendor/nonprofitcrm/memberships/database/migrations'));

        return $app;
    }
}
