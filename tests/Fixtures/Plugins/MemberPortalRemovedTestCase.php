<?php

namespace Tests\Fixtures\Plugins;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Tests\TestCase;

/**
 * Boots the application with the MemberPortal plugin REMOVED from the plugin
 * list (session 394, arc D5 — the remove-the-line guarantee for the carved
 * Member Portal vertical). The shipped config/plugins.php DOES carry
 * MemberPortal, so this mirror strips the line after configuration loads and
 * before providers register — the MembershipsRemovedTestCase pattern. Tests
 * on the plain Tests\TestCase are the enabled twin.
 *
 * No migrator path is registered: the portal_accounts and
 * portal_password_reset_tokens tables are core-dump tables this session.
 * THIS CAVEAT EXPIRES at the D5 package phase — the moment the squash
 * boundary redraw moves the portal schema into the plugin, this fixture must
 * register the plugin's migration path so the data-kept assertions still
 * have their tables (the MembershipsRemovedTestCase mechanics).
 */
abstract class MemberPortalRemovedTestCase extends TestCase
{
    public function createApplication()
    {
        $app = require Application::inferBasePath() . '/bootstrap/app.php';

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app) {
            $config = $app->make('config');
            $config->set('plugins', array_values(array_filter(
                $config->get('plugins', []),
                fn (string $provider) => $provider !== \Plugins\MemberPortal\MemberPortalServiceProvider::class,
            )));
        });

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
