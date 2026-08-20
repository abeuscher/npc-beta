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
 * No migrator path is registered: the memberships and membership_tiers tables
 * are core-dump tables until this block's package phase moves the squash
 * boundary — the caveat expires when the schema moves (the 389→390 precedent).
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

        return $app;
    }
}
