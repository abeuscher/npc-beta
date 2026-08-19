<?php

namespace Plugins\Donations;

use App\Importers\TransactionFieldRegistry;
use App\Models\Donation;
use App\Models\DonationCredit;
use App\Models\Fund;
use App\Plugins\AdminContribution;
use App\Plugins\EmailTemplateRegistry;
use App\Plugins\ImporterContribution;
use App\Plugins\ImporterRegistry;
use App\Plugins\PluginAdminRegistry;
use App\Services\WidgetRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Plugins\Donations\Filament\Pages\ImportDonationsPage;
use Plugins\Donations\Filament\Pages\ImportDonationsProgressPage;
use Plugins\Donations\Importers\DonationFieldRegistry;
use Plugins\Donations\Observers\DonationCreditObserver;
use Plugins\Donations\Observers\DonationObserver;
use Plugins\Donations\Policies\DonationCreditPolicy;
use Plugins\Donations\Policies\DonationPolicy;
use Plugins\Donations\Policies\FundPolicy;
use Plugins\Donations\Widgets\DonationForm\DonationFormDefinition;
use Plugins\Donations\Widgets\RecentDonations\RecentDonationsDefinition;

class DonationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Admin socket (docs/plugin-contract.md surfaces 3/4): the donation
        // and fund resources plus the Giving Summary and import pages are
        // discovered from the plugin. No permission names declared — the
        // donation/fund resource permissions and manage_donations are part
        // of core's PermissionSeeder role matrix (the extracted-vertical
        // nuance, surface 3).
        $this->app->make(PluginAdminRegistry::class)->register(new AdminContribution(
            plugin: 'donations',
            resourcesPath: __DIR__ . '/Filament/Resources',
            resourcesNamespace: 'Plugins\\Donations\\Filament\\Resources',
            pagesPath: __DIR__ . '/Filament/Pages',
            pagesNamespace: 'Plugins\\Donations\\Filament\\Pages',
        ));

        // Importer socket (surface 8): the donations importer pairs its
        // wizard + progress pages with the 'donations' slug; the CSV-template
        // shape and the demo-source field map are donations-importer domain
        // knowledge, so their composition lives here, not in the core
        // consumers. Header and map shapes are byte-identical to the
        // pre-carve core methods (CsvTemplateService::donationHeaders,
        // SeedFakeImportSourcesCommand::donationsFieldMap).
        $this->app->make(ImporterRegistry::class)->register(new ImporterContribution(
            slug: 'donations',
            label: 'Import Donations',
            icon: 'heroicon-o-gift',
            modelType: 'donation',
            pageClass: ImportDonationsPage::class,
            progressPageClass: ImportDonationsProgressPage::class,
            templateHeaders: function (): array {
                $headers = [];
                foreach (DonationFieldRegistry::options() as $label) {
                    $headers[] = $label;
                }

                foreach (TransactionFieldRegistry::options() as $key => $label) {
                    // Skip duplicated fields
                    if ($key === 'invoice_number') {
                        continue;
                    }
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
                foreach (DonationFieldRegistry::fields() as $key => $def) {
                    $map[strtolower($def['label'])] = "donation:{$key}";
                }
                foreach (TransactionFieldRegistry::fields() as $key => $def) {
                    if ($key === 'invoice_number') {
                        continue;
                    }
                    $map[strtolower($def['label'])] = "transaction:{$key}";
                }
                $map['email']   = 'contact:email';
                $map['user id'] = 'contact:external_id';
                $map['phone']   = 'contact:phone';

                return $map;
            },
        ));

        // Email-template handles (surface 7): the two donation handles are
        // the registry's first plugin contribution — defaults byte-identical
        // to the entries they replace in core's EmailTemplate::defaults()
        // array. forHandle() and the base seeder resolve through the
        // registry; with the plugin absent, neither handle exists.
        $templates = $this->app->make(EmailTemplateRegistry::class);
        $templates->contribute('donation_receipt', [
            'subject'       => 'Your {{tax_year}} donation receipt — {{org_name}}',
            'body'          => '<p>Dear {{contact_name}},</p><p>Thank you for your generous support of {{org_name}} in {{tax_year}}. This letter serves as your official donation receipt for the {{tax_year}} tax year.</p>{{donations}}<p><strong>Total donations: ${{total}}</strong></p><p>No goods or services were provided in exchange for these contributions. Please retain this letter for your tax records.</p><p>With gratitude,<br>{{org_name}}</p>',
            'footer_reason' => 'You received this email because you made a donation to {{org_name}} in {{tax_year}}.',
        ]);
        $templates->contribute('donation_acknowledgment', [
            'subject'       => 'Thank you for your donation — {{org_name}}',
            'body'          => '<p>Dear {{contact_name}},</p><p>Thank you for your generous gift of <strong>${{amount}}</strong> to {{org_name}}, received on {{date}}. This letter is your official receipt for this contribution.</p><p><strong>Gift details</strong><br>Amount: ${{amount}}<br>Date: {{date}}<br>Fund: {{fund}}<br>Reference: {{reference}}</p><p>No goods or services were provided in exchange for this contribution. Please retain this letter for your tax records.</p><p>With gratitude,<br>{{org_name}}</p>',
            'footer_reason' => 'You received this email because you made a donation to {{org_name}}.',
        ]);
    }

    public function boot(): void
    {
        // Plugin-owned schema (contract surface 5): the four donation tables
        // left core's dump at the D3 squash-boundary redraw. Install order is
        // core dump → enabled plugins' migrations → seeders.
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        View::addNamespace('plugin-donations', __DIR__ . '/resources/views');
        View::addNamespace('plugin-donations-widgets', __DIR__ . '/Widgets');

        $widgets = $this->app->make(WidgetRegistry::class);
        $widgets->register(new DonationFormDefinition());
        $widgets->register(new RecentDonationsDefinition());

        Donation::observe(DonationObserver::class);
        DonationCredit::observe(DonationCreditObserver::class);

        Gate::policy(Donation::class, DonationPolicy::class);
        Gate::policy(DonationCredit::class, DonationCreditPolicy::class);
        Gate::policy(Fund::class, FundPolicy::class);

        // The inbound payments inversion (contract surface 10, donations
        // slice — the first cross-repo inversion, Payments v0.2.0): the
        // Payments webhook dispatches the three core events; these listeners
        // own the fulfillment. Each no-ops cheaply on dispatches routed to
        // another vertical.
        \Illuminate\Support\Facades\Event::listen(
            \App\Payments\Events\CheckoutSettled::class,
            \Plugins\Donations\Listeners\PromotePaidDonations::class,
        );
        \Illuminate\Support\Facades\Event::listen(
            \App\Payments\Events\InvoiceSettled::class,
            \Plugins\Donations\Listeners\RecordRecurringDonationPayment::class,
        );
        \Illuminate\Support\Facades\Event::listen(
            \App\Payments\Events\InvoicePaymentFailed::class,
            \Plugins\Donations\Listeners\MarkRecurringDonationPastDue::class,
        );

        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
    }
}
