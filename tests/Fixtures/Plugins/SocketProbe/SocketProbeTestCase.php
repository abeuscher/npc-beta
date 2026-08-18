<?php

namespace Tests\Fixtures\Plugins\SocketProbe;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Tests\TestCase;

/**
 * Boots the application with the SocketProbe fixture plugin enabled: its
 * provider is appended to config('plugins') after configuration loads and
 * before providers register — exactly when a real config/plugins.php entry
 * takes effect. Tests using the plain Tests\TestCase get the shipped plugin
 * list, which is how the remove-the-line tests prove the fixture vanishes.
 */
abstract class SocketProbeTestCase extends TestCase
{
    public function createApplication()
    {
        $app = require Application::inferBasePath() . '/bootstrap/app.php';

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app) {
            $app->make('config')->push('plugins', SocketProbeServiceProvider::class);
        });

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
