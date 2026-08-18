<?php

namespace Tests\Fixtures\Plugins\SocketProbe;

use App\Plugins\AdminContribution;
use App\Plugins\PluginAdminRegistry;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Test-fixture plugin proving the admin-shell socket (session 379, arc P3 —
 * docs/plugin-contract.md surfaces 3 and 4). Never listed in
 * config/plugins.php: SocketProbeTestCase appends it to the plugin list at
 * bootstrap, so it exists only inside the tests that opt in. It declares the
 * minimum of each admin surface — one Filament page in a core nav group with
 * a sort weight, one route file, one permission.
 */
class SocketProbeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(PluginAdminRegistry::class)->register(new AdminContribution(
            plugin: 'socket-probe',
            pagesPath: __DIR__ . '/Filament/Pages',
            pagesNamespace: 'Tests\\Fixtures\\Plugins\\SocketProbe\\Filament\\Pages',
            routesFile: __DIR__ . '/routes/admin.php',
            permissions: ['use_socket_probe'],
        ));
    }

    public function boot(): void
    {
        View::addNamespace('socket-probe', __DIR__ . '/views');
    }
}
