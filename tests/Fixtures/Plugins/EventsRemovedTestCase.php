<?php

namespace Tests\Fixtures\Plugins;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Tests\TestCase;

/**
 * Boots the application with the Events plugin REMOVED from the plugin list
 * (session 381, arc P5 — the remove-the-line guarantee for the first domain
 * vertical). The shipped config/plugins.php DOES carry Events, so this mirror
 * strips the line after configuration loads and before providers register —
 * the PaymentsRemovedTestCase pattern. Tests on the plain Tests\TestCase are
 * the enabled twin.
 */
abstract class EventsRemovedTestCase extends TestCase
{
    public function createApplication()
    {
        $app = require Application::inferBasePath() . '/bootstrap/app.php';

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app) {
            $config = $app->make('config');
            $config->set('plugins', array_values(array_filter(
                $config->get('plugins', []),
                fn (string $provider) => $provider !== \Plugins\Events\EventsServiceProvider::class,
            )));
        });

        $app->make(Kernel::class)->bootstrap();

        // Disabled ≠ uninstalled (session 383, arc P7 — contract surface 5).
        // This fixture models installed-then-disabled: activation stripped,
        // schema present, data kept. The provider never boots, so its
        // loadMigrationsFrom never runs — register the plugin's migration
        // path directly so migrate:fresh still creates the events tables the
        // data-kept assertions query. A never-installed composition is proven
        // by the per-composition fresh-install identity check, not here.
        $app->make('migrator')->path($app->basePath('plugins/Events/database/migrations'));

        return $app;
    }
}
