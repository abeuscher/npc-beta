<?php

namespace Plugins\Products;

use App\Models\Product;
use App\Models\Purchase;
use App\Plugins\AdminContribution;
use App\Plugins\PluginAdminRegistry;
use App\Services\WidgetRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Plugins\Products\Observers\ProductObserver;
use Plugins\Products\Observers\PurchaseObserver;
use Plugins\Products\Policies\ProductPolicy;
use Plugins\Products\Widgets\ProductCarousel\ProductCarouselDefinition;
use Plugins\Products\Widgets\ProductDisplay\ProductDisplayDefinition;

class ProductsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Admin socket (docs/plugin-contract.md surfaces 3/4): the Product
        // resource, its three pages, and both relation managers are discovered
        // from the plugin. No permission names declared — the product
        // permissions are part of core's PermissionSeeder role matrix (the
        // extracted-vertical nuance, surface 3). No capability declared —
        // nothing consumes products presence; the presence signal is route
        // presence (Route::has('products.checkout')).
        $this->app->make(PluginAdminRegistry::class)->register(new AdminContribution(
            plugin: 'products',
            resourcesPath: __DIR__ . '/Filament/Resources',
            resourcesNamespace: 'Plugins\\Products\\Filament\\Resources',
        ));
    }

    public function boot(): void
    {
        // Plugin-owned schema (contract surface 5): the four product tables
        // left core's dump at the D8 squash-boundary redraw. Install order is
        // core dump → enabled plugins' migrations → seeders.
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        View::addNamespace('plugin-products-widgets', __DIR__ . '/Widgets');

        $widgets = $this->app->make(WidgetRegistry::class);
        $widgets->register(new ProductDisplayDefinition());
        $widgets->register(new ProductCarouselDefinition());

        // The double observer inversion (front-loaded decision 5): both
        // #[ObservedBy] attributes came off the core Product and Purchase
        // models — a core-model reference to a plugin class would be a banned
        // core→plugin reach — and the plugin registers the observers here
        // instead (the Forms FormSubmission::observe() precedent, twice).
        Product::observe(ProductObserver::class);
        Purchase::observe(PurchaseObserver::class);

        Gate::policy(Product::class, ProductPolicy::class);

        // The inbound payments inversion (contract surface 10, products slice
        // — the third cross-repo inversion, Payments v0.4.0): the Payments
        // webhook's product branch dispatches CheckoutSettled; this listener
        // owns the fulfillment and no-ops cheaply on dispatches routed to
        // another vertical (self-filter on product_price_id before any read).
        \Illuminate\Support\Facades\Event::listen(
            \App\Payments\Events\CheckoutSettled::class,
            \Plugins\Products\Listeners\RecordProductPurchase::class,
        );

        // Both product routes register ahead of core's page-slug catch-all —
        // provider boot() runs before routes/web.php loads (contract
        // surface 4, front-of-house half).
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
    }
}
