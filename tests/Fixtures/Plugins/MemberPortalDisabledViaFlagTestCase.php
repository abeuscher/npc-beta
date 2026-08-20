<?php

namespace Tests\Fixtures\Plugins;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Tests\TestCase;

/**
 * Boots the application with the MemberPortal plugin DISABLED via the
 * per-install activation flag (contract surface 1's two-layer model).
 * Unlike MemberPortalRemovedTestCase, config('plugins') keeps its
 * MemberPortal line — the installed superset is untouched; the disabled set
 * is written to config('plugin-activation.disabled') after configuration
 * loads, exactly where PLUGINS_DISABLED would put it, and
 * PluginServiceProvider's filter does the subtraction. Runtime-disabled must
 * reproduce the strip-the-line state the removal mirror asserts.
 */
abstract class MemberPortalDisabledViaFlagTestCase extends TestCase
{
    public function createApplication()
    {
        $app = require Application::inferBasePath() . '/bootstrap/app.php';

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app) {
            $app->make('config')->set('plugin-activation.disabled', ['member-portal']);
        });

        $app->make(Kernel::class)->bootstrap();

        // Disabled ≠ uninstalled (contract surface 5) — the filtered provider
        // never boots, so register the plugin's migration path directly; see
        // MemberPortalRemovedTestCase.
        $app->make('migrator')->path($app->basePath('vendor/nonprofitcrm/member-portal/database/migrations'));

        return $app;
    }
}
