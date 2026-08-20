<?php

namespace Plugins\Memberships;

use App\Models\Membership;
use App\Plugins\AdminContribution;
use App\Plugins\ImporterContribution;
use App\Plugins\ImporterRegistry;
use App\Plugins\PluginAdminRegistry;
use App\Services\WidgetRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Plugins\Memberships\Filament\Pages\ImportMembershipsPage;
use Plugins\Memberships\Filament\Pages\ImportMembershipsProgressPage;
use Plugins\Memberships\Importers\MembershipFieldRegistry;
use Plugins\Memberships\Observers\MembershipObserver;
use Plugins\Memberships\Policies\MembershipPolicy;
use Plugins\Memberships\Widgets\MembershipStatus\MembershipStatusDefinition;
use Plugins\Memberships\Widgets\PricingChart\PricingChartDefinition;

class MembershipsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Admin socket (docs/plugin-contract.md surfaces 3/4): the membership,
        // tier, and member resources plus the import pages are discovered from
        // the plugin. No permission names declared — view_any_membership,
        // view_any_member, and manage_membership_tiers are part of core's
        // PermissionSeeder role matrix (the extracted-vertical nuance,
        // surface 3).
        $this->app->make(PluginAdminRegistry::class)->register(new AdminContribution(
            plugin: 'memberships',
            resourcesPath: __DIR__ . '/Filament/Resources',
            resourcesNamespace: 'Plugins\\Memberships\\Filament\\Resources',
            pagesPath: __DIR__ . '/Filament/Pages',
            pagesNamespace: 'Plugins\\Memberships\\Filament\\Pages',
        ));

        // Importer socket (surface 8): the memberships importer pairs its
        // wizard + progress pages with the 'memberships' slug; the CSV-template
        // shape and the demo-source field map are memberships-importer domain
        // knowledge, so their composition lives here, not in the core
        // consumers. Header and map shapes are byte-identical to the
        // pre-carve core methods (CsvTemplateService::membershipHeaders,
        // SeedFakeImportSourcesCommand::membershipsFieldMap).
        $this->app->make(ImporterRegistry::class)->register(new ImporterContribution(
            slug: 'memberships',
            label: 'Import Memberships',
            icon: 'heroicon-o-identification',
            modelType: 'membership',
            pageClass: ImportMembershipsPage::class,
            progressPageClass: ImportMembershipsProgressPage::class,
            templateHeaders: function (): array {
                $headers = [];
                foreach (MembershipFieldRegistry::options() as $label) {
                    $headers[] = $label;
                }

                // Contact match columns
                $headers[] = 'Email';
                $headers[] = 'User ID';
                $headers[] = 'Phone';

                return $headers;
            },
            fakeSourceFieldMap: function (): array {
                $map = [];
                foreach (MembershipFieldRegistry::fields() as $key => $def) {
                    $map[strtolower($def['label'])] = "membership:{$key}";
                }
                $map['email']   = 'contact:email';
                $map['user id'] = 'contact:external_id';
                $map['phone']   = 'contact:phone';

                return $map;
            },
        ));
    }

    public function boot(): void
    {
        // Plugin-owned schema (contract surface 5, session 393): the
        // membership_tiers and memberships create-table migrations run after
        // core's dump in the deterministic install order.
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        View::addNamespace('plugin-memberships', __DIR__ . '/resources/views');
        View::addNamespace('plugin-memberships-widgets', __DIR__ . '/Widgets');

        $widgets = $this->app->make(WidgetRegistry::class);
        $widgets->register(new MembershipStatusDefinition());
        $widgets->register(new PricingChartDefinition());

        Membership::observe(MembershipObserver::class);

        Gate::policy(Membership::class, MembershipPolicy::class);

        // The inbound payments inversion (contract surface 10, memberships
        // slice — the second cross-repo inversion, Payments v0.3.0): the
        // Payments webhook dispatches the core events; these listeners own
        // the fulfillment. Each no-ops cheaply on dispatches routed to
        // another vertical. Membership invoice failures were never handled —
        // no InvoicePaymentFailed listener (behavior preservation).
        \Illuminate\Support\Facades\Event::listen(
            \App\Payments\Events\CheckoutSettled::class,
            \Plugins\Memberships\Listeners\PromotePaidMemberships::class,
        );
        \Illuminate\Support\Facades\Event::listen(
            \App\Payments\Events\InvoiceSettled::class,
            \Plugins\Memberships\Listeners\RecordMembershipRenewal::class,
        );

        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
    }
}
