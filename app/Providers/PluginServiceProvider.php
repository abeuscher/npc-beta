<?php

namespace App\Providers;

use App\Plugins\CapabilityRegistry;
use App\Plugins\ImporterRegistry;
use App\Plugins\PluginAdminRegistry;
use Illuminate\Support\ServiceProvider;

class PluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bound before the plugin loop so plugin providers can declare admin
        // contributions, capabilities, and importers into them from their own
        // register() methods.
        $this->app->singleton(PluginAdminRegistry::class);
        $this->app->singleton(CapabilityRegistry::class);
        $this->app->singleton(ImporterRegistry::class);

        foreach (config('plugins', []) as $provider) {
            $this->app->register($provider);
        }
    }
}
