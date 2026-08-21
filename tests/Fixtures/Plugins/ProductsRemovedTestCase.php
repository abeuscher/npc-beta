<?php

namespace Tests\Fixtures\Plugins;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Tests\TestCase;

/**
 * Boots the application with the Products plugin REMOVED from the plugin list
 * (session 398, arc D8 — the remove-the-line guarantee for the carved
 * Products vertical). The shipped config/plugins.php DOES carry Products, so
 * this mirror strips the line after configuration loads and before providers
 * register — the FormsRemovedTestCase pattern. Tests on the plain
 * Tests\TestCase are the enabled twin.
 */
abstract class ProductsRemovedTestCase extends TestCase
{
    public function createApplication()
    {
        $app = require Application::inferBasePath() . '/bootstrap/app.php';

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app) {
            $config = $app->make('config');
            $config->set('plugins', array_values(array_filter(
                $config->get('plugins', []),
                fn (string $provider) => $provider !== \Plugins\Products\ProductsServiceProvider::class,
            )));
        });

        $app->make(Kernel::class)->bootstrap();

        // Disabled ≠ uninstalled (contract surface 5): this fixture models
        // installed-then-disabled — activation stripped, schema present, data
        // kept. The migrator path is vendor-relative FROM BIRTH (the matured
        // 393–397 compression nuance): inert while the four product tables
        // still live in core's dump (the path doesn't exist yet), valid the
        // moment the D8 squash-boundary redraw moves them to the package —
        // zero re-points at any later step. A never-installed composition is
        // proven by the per-composition fresh-install identity check, not
        // here.
        $app->make('migrator')->path($app->basePath('vendor/nonprofitcrm/products/database/migrations'));

        return $app;
    }
}
