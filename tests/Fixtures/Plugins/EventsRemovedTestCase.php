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

        return $app;
    }
}
