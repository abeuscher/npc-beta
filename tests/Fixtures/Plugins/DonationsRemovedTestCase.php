<?php

namespace Tests\Fixtures\Plugins;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Tests\TestCase;

/**
 * Boots the application with the Donations plugin REMOVED from the plugin
 * list (session 389, arc D3 — the remove-the-line guarantee for the carved
 * Donations vertical). The shipped config/plugins.php DOES carry Donations,
 * so this mirror strips the line after configuration loads and before
 * providers register — the EventsRemovedTestCase pattern. Tests on the plain
 * Tests\TestCase are the enabled twin.
 *
 * Unlike the Events fixture, no migrator path is registered: the four
 * donation tables stay in core's schema dump this session (the squash
 * redraw is the block's package session), so migrate:fresh creates them on
 * every composition regardless of the plugin.
 */
abstract class DonationsRemovedTestCase extends TestCase
{
    public function createApplication()
    {
        $app = require Application::inferBasePath() . '/bootstrap/app.php';

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app) {
            $config = $app->make('config');
            $config->set('plugins', array_values(array_filter(
                $config->get('plugins', []),
                fn (string $provider) => $provider !== \Plugins\Donations\DonationsServiceProvider::class,
            )));
        });

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
