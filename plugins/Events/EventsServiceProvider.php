<?php

namespace Plugins\Events;

use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Plugins\Events\Console\Commands\SendEventReminders;
use Plugins\Events\Observers\EventObserver;
use Plugins\Events\Observers\EventRegistrationObserver;
use Plugins\Events\Policies\EventPolicy;

class EventsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
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
