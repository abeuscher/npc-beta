<?php

namespace Tests\Fixtures\Plugins;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Tests\TestCase;

/**
 * Boots the application with the Forms plugin REMOVED from the plugin list
 * (session 397, arc D7 — the remove-the-line guarantee for the carved Forms
 * vertical). The shipped config/plugins.php DOES carry Forms, so this mirror
 * strips the line after configuration loads and before providers register —
 * the MembershipsRemovedTestCase pattern. Tests on the plain Tests\TestCase
 * are the enabled twin.
 */
abstract class FormsRemovedTestCase extends TestCase
{
    public function createApplication()
    {
        $app = require Application::inferBasePath() . '/bootstrap/app.php';

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app) {
            $config = $app->make('config');
            $config->set('plugins', array_values(array_filter(
                $config->get('plugins', []),
                fn (string $provider) => $provider !== \Plugins\Forms\FormsServiceProvider::class,
            )));
        });

        $app->make(Kernel::class)->bootstrap();

        // Disabled ≠ uninstalled (contract surface 5): this fixture models
        // installed-then-disabled — activation stripped, schema present, data
        // kept. The provider never boots, so its loadMigrationsFrom never runs
        // — register the plugin's migration path directly so migrate:fresh
        // still creates the two form tables the data-kept assertions query
        // (the MembershipsRemovedTestCase mechanics; session 397, arc D7 —
        // the squash-boundary redraw moved the forms schema to the plugin;
        // the vendor path is valid from the package step via the path-repo
        // symlink). A never-installed composition is proven by the
        // per-composition fresh-install identity check, not here.
        $app->make('migrator')->path($app->basePath('vendor/nonprofitcrm/forms/database/migrations'));

        return $app;
    }
}
