<?php

namespace Tests\Fixtures\Plugins;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Tests\TestCase;

/**
 * Boots the application with the Products plugin DISABLED via the per-install
 * activation flag (contract surface 1's two-layer model). Unlike
 * ProductsRemovedTestCase, config('plugins') keeps its Products line — the
 * installed superset is untouched; the disabled set is written to
 * config('plugin-activation.disabled') after configuration loads, exactly
 * where PLUGINS_DISABLED would put it, and PluginServiceProvider's filter
 * does the subtraction. Runtime-disabled must reproduce the strip-the-line
 * state the removal mirror asserts.
 */
abstract class ProductsDisabledViaFlagTestCase extends TestCase
{
    public function createApplication()
    {
        $app = require Application::inferBasePath() . '/bootstrap/app.php';

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app) {
            $app->make('config')->set('plugin-activation.disabled', ['products']);
        });

        $app->make(Kernel::class)->bootstrap();

        // Disabled ≠ uninstalled (contract surface 5): the provider never
        // registers, so the plugin's migrations never load — register the
        // migrator path directly so the data-kept assertions can query the
        // product tables. Vendor-relative from birth (the
        // ProductsRemovedTestCase note): inert until the D8 redraw, valid
        // ever after.
        $app->make('migrator')->path($app->basePath('vendor/nonprofitcrm/products/database/migrations'));

        return $app;
    }
}
