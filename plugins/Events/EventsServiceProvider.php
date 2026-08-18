<?php

namespace Plugins\Events;

use App\Importers\ContactFieldRegistry;
use App\Importers\TransactionFieldRegistry;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Plugins\AdminContribution;
use App\Plugins\ImporterContribution;
use App\Plugins\ImporterRegistry;
use App\Plugins\PluginAdminRegistry;
use App\Services\WidgetRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Plugins\Events\Console\Commands\SendEventReminders;
use Plugins\Events\Filament\Pages\ImportEventsPage;
use Plugins\Events\Filament\Pages\ImportEventsProgressPage;
use Plugins\Events\Importers\EventFieldRegistry;
use Plugins\Events\Importers\RegistrationFieldRegistry;
use Plugins\Events\Observers\EventObserver;
use Plugins\Events\Observers\EventRegistrationObserver;
use Plugins\Events\Policies\EventPolicy;
use Plugins\Events\Widgets\EventDescription\EventDescriptionDefinition;
use Plugins\Events\Widgets\EventImage\EventImageDefinition;
use Plugins\Events\Widgets\EventMiniCalendar\EventMiniCalendarDefinition;
use Plugins\Events\Widgets\EventRegistration\EventRegistrationDefinition;
use Plugins\Events\Widgets\EventsListing\EventsListingDefinition;
use Plugins\Events\Widgets\PortalEventRegistrations\PortalEventRegistrationsDefinition;
use Plugins\Events\Widgets\ThisWeeksEvents\ThisWeeksEventsDefinition;

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
            pagesPath: __DIR__ . '/Filament/Pages',
            pagesNamespace: 'Plugins\\Events\\Filament\\Pages',
        ));

        // Importer socket (surface 8): the events importer pairs its wizard +
        // progress pages with the 'events' slug; the CSV-template shape and
        // the demo-source field map are events-importer domain knowledge, so
        // their composition lives here, not in the core consumers.
        $this->app->make(ImporterRegistry::class)->register(new ImporterContribution(
            slug: 'events',
            label: 'Import Events and Event Registrations',
            icon: 'heroicon-o-calendar-days',
            modelType: 'event',
            pageClass: ImportEventsPage::class,
            progressPageClass: ImportEventsProgressPage::class,
            templateHeaders: function (): array {
                $headers = [];
                foreach (EventFieldRegistry::options() as $label) {
                    $headers[] = "Event {$label}";
                }
                foreach (RegistrationFieldRegistry::options() as $label) {
                    $headers[] = "Registration {$label}";
                }

                // Contact match columns
                $headers[] = 'Contact Email';
                $headers[] = 'Contact External ID';
                $headers[] = 'Contact Phone';

                // Transaction columns — labels are already self-disambiguating
                // ('Transaction ID (external)', 'Transaction Amount') so no
                // prefix is applied here.
                foreach (TransactionFieldRegistry::options() as $label) {
                    $headers[] = $label;
                }

                return $headers;
            },
            fakeSourceFieldMap: function (): array {
                $map = [];
                foreach (EventFieldRegistry::fields() as $key => $def) {
                    $map[strtolower('Event ' . $def['label'])] = "event:{$key}";
                }
                foreach (RegistrationFieldRegistry::fields() as $key => $def) {
                    $map[strtolower('Registration ' . $def['label'])] = "registration:{$key}";
                }
                $map['contact email']       = 'contact:email';
                $map['contact external id'] = 'contact:external_id';
                $map['contact phone']       = 'contact:phone';
                foreach (TransactionFieldRegistry::fields() as $key => $def) {
                    $map[strtolower($def['label'])] = "transaction:{$key}";
                }

                return $map;
            },
        ));
    }

    public function boot(): void
    {
        View::addNamespace('plugin-events', __DIR__ . '/resources/views');
        View::addNamespace('plugin-events-widgets', __DIR__ . '/Widgets');

        $widgets = $this->app->make(WidgetRegistry::class);
        $widgets->register(new EventDescriptionDefinition());
        $widgets->register(new EventImageDefinition());
        $widgets->register(new EventMiniCalendarDefinition());
        $widgets->register(new EventRegistrationDefinition());
        $widgets->register(new EventsListingDefinition());
        $widgets->register(new PortalEventRegistrationsDefinition());
        $widgets->register(new ThisWeeksEventsDefinition());

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
