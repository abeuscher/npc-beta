<?php

namespace Plugins\Events;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Plugins\AdminContribution;
use App\Plugins\PluginAdminRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Plugins\Events\Console\Commands\SendEventReminders;
use Plugins\Events\Observers\EventObserver;
use Plugins\Events\Observers\EventRegistrationObserver;
use Plugins\Events\Policies\EventPolicy;

class EventsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Admin socket (docs/plugin-contract.md surfaces 3/4): the event
        // resource + pages are discovered from the plugin. No permission
        // names declared — the `event` resource permissions are part of
        // core's PermissionSeeder role matrix (extracted-vertical nuance,
        // front-loaded decision 6).
        $this->app->make(PluginAdminRegistry::class)->register(new AdminContribution(
            plugin: 'events',
            resourcesPath: __DIR__ . '/Filament/Resources',
            resourcesNamespace: 'Plugins\\Events\\Filament\\Resources',
        ));
    }

    public function boot(): void
    {
        View::addNamespace('plugin-events', __DIR__ . '/resources/views');

        Event::observe(EventObserver::class);
        EventRegistration::observe(EventRegistrationObserver::class);

        Gate::policy(Event::class, EventPolicy::class);

        // Command + schedule registered here so removing the plugin's
        // config/plugins.php line unschedules the reminders job (disabled =
        // inert). Production runs `php artisan schedule:run` via cron.
        $this->commands([SendEventReminders::class]);
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('events:send-reminders')->dailyAt('08:00');
        });

        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
    }
}
