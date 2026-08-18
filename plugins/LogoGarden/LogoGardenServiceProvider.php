<?php

namespace Plugins\LogoGarden;

use App\Services\WidgetRegistry;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class LogoGardenServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::addNamespace('plugin-logo-garden', __DIR__);

        $this->app->make(WidgetRegistry::class)->register(new LogoGardenDefinition());
    }
}
