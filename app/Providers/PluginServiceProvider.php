<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class PluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach (config('plugins', []) as $provider) {
            $this->app->register($provider);
        }
    }
}
