<?php

namespace App\Providers;

use App\Plugins\PluginAdminRegistry;
use Illuminate\Support\ServiceProvider;

class PluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bound before the plugin loop so plugin providers can declare admin
        // contributions into it from their own register() methods.
        $this->app->singleton(PluginAdminRegistry::class);

        foreach (config('plugins', []) as $provider) {
            $this->app->register($provider);
        }
    }
}
