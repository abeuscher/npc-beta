<?php

namespace Tests\Fixtures\Plugins;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Tests\TestCase;

/**
 * Boots the application with the Payments plugin REMOVED from the plugin list
 * (session 380, arc P4 — the remove-the-line guarantee for the first shipping
 * foundation plugin). The shipped config/plugins.php DOES carry Payments, so
 * this mirror strips the line after configuration loads and before providers
 * register — the inverse of SocketProbeTestCase, which appends a fixture the
 * shipped list lacks. Tests on the plain Tests\TestCase are the enabled twin.
 */
abstract class PaymentsRemovedTestCase extends TestCase
{
    public function createApplication()
    {
        $app = require Application::inferBasePath() . '/bootstrap/app.php';

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app) {
            $config = $app->make('config');
            $config->set('plugins', array_values(array_filter(
                $config->get('plugins', []),
                fn (string $provider) => $provider !== \Plugins\Payments\PaymentsServiceProvider::class,
            )));
        });

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
