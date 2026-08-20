<?php

namespace Tests\Fixtures\Plugins;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Tests\TestCase;

/**
 * Boots the application with the Memberships plugin REMOVED from the plugin
 * list (session 392, arc D4 — the remove-the-line guarantee for the carved
 * Memberships vertical). The shipped config/plugins.php DOES carry
 * Memberships, so this mirror strips the line after configuration loads and
 * before providers register — the DonationsRemovedTestCase pattern. Tests on
 * the plain Tests\TestCase are the enabled twin.
 *
 */
abstract class MembershipsRemovedTestCase extends TestCase
{
    public function createApplication()
    {
        $app = require Application::inferBasePath() . '/bootstrap/app.php';

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app) {
            $config = $app->make('config');
            $config->set('plugins', array_values(array_filter(
                $config->get('plugins', []),
                fn (string $provider) => $provider !== \Plugins\Memberships\MembershipsServiceProvider::class,
            )));
        });

        $app->make(Kernel::class)->bootstrap();

        // Disabled ≠ uninstalled (contract surface 5): this fixture models
        // installed-then-disabled — activation stripped, schema present, data
        // kept. The provider never boots, so its loadMigrationsFrom never runs
        // — register the plugin's migration path directly so migrate:fresh
        // still creates the two membership tables the data-kept assertions
        // query (the DonationsRemovedTestCase mechanics; session 393, arc D4 —
        // the squash-boundary redraw moved the membership schema to the
        // plugin). A never-installed composition is proven by the
        // per-composition fresh-install identity check, not here.
        $app->make('migrator')->path($app->basePath('vendor/nonprofitcrm/memberships/database/migrations'));

        return $app;
    }
}
