<?php

namespace Tests\Fixtures\Plugins;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Tests\TestCase;

/**
 * Boots the application with LogoGarden AND Payments disabled via the
 * per-install activation flag while Events stays enabled (session 384, arc
 * P8) — the generalization proof that the filter subtracts any set of
 * handles, not just the one the removal mirrors already cover, and leaves
 * the remaining plugins untouched. Also LogoGarden's first vanish coverage.
 */
abstract class LogoGardenPaymentsDisabledViaFlagTestCase extends TestCase
{
    public function createApplication()
    {
        $app = require Application::inferBasePath() . '/bootstrap/app.php';

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app) {
            $app->make('config')->set('plugin-activation.disabled', ['logo-garden', 'payments']);
        });

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
