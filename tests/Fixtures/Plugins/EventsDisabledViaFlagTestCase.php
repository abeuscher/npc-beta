<?php

namespace Tests\Fixtures\Plugins;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Tests\TestCase;

/**
 * Boots the application with the Events plugin DISABLED via the per-install
 * activation flag (session 384, arc P8 — contract surface 1's two-layer
 * model). Unlike EventsRemovedTestCase, config('plugins') keeps its Events
 * line — the installed superset is untouched; the disabled set is written to
 * config('plugin-activation.disabled') after configuration loads, exactly
 * where PLUGINS_DISABLED would put it, and PluginServiceProvider's filter
 * does the subtraction. Runtime-disabled must reproduce the strip-the-line
 * state the removal mirror asserts.
 */
abstract class EventsDisabledViaFlagTestCase extends TestCase
{
    public function createApplication()
    {
        $app = require Application::inferBasePath() . '/bootstrap/app.php';

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app) {
            $app->make('config')->set('plugin-activation.disabled', ['events']);
        });

        $app->make(Kernel::class)->bootstrap();

        // Disabled ≠ uninstalled (contract surface 5): a runtime-disabled
        // install keeps its schema and data. The provider never boots, so its
        // loadMigrationsFrom never runs — register the plugin's migration
        // path directly so migrate:fresh still creates the events tables the
        // data-kept assertions query (the EventsRemovedTestCase mechanics).
        $app->make('migrator')->path($app->basePath('vendor/nonprofitcrm/events/database/migrations'));

        return $app;
    }
}
