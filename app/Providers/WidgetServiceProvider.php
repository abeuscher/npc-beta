<?php

namespace App\Providers;

use App\Services\WidgetRegistry;
use App\Widgets\Nav\NavDefinition;
use Illuminate\Support\ServiceProvider;

class WidgetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WidgetRegistry::class, fn () => new WidgetRegistry());
    }

    public function boot(): void
    {
        $registry = $this->app->make(WidgetRegistry::class);
        $registry->register(new NavDefinition());
    }
}
