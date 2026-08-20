<?php

namespace Tests\Fixtures\Plugins;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Tests\TestCase;

/**
 * Boots the application with the Blog plugin REMOVED from the plugin list
 * (session 396, arc D6 — the remove-the-line guarantee for the carved Blog
 * vertical). The shipped config/plugins.php DOES carry Blog, so this mirror
 * strips the line after configuration loads and before providers register —
 * the MemberPortalRemovedTestCase pattern. Tests on the plain Tests\TestCase
 * are the enabled twin.
 *
 * No migrator path is registered here — permanently, not as an expiring
 * caveat: the Blog plugin owns no schema. Posts are Page rows of type 'post'
 * (core tables); the first vertical extraction with no owned tables, so
 * there is nothing for the disabled-≠-uninstalled semantics to keep beyond
 * what core's own schema already holds.
 */
abstract class BlogRemovedTestCase extends TestCase
{
    public function createApplication()
    {
        $app = require Application::inferBasePath() . '/bootstrap/app.php';

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app) {
            $config = $app->make('config');
            $config->set('plugins', array_values(array_filter(
                $config->get('plugins', []),
                fn (string $provider) => $provider !== \Plugins\Blog\BlogServiceProvider::class,
            )));
        });

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
