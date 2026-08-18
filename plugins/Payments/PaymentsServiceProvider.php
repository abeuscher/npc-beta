<?php

namespace Plugins\Payments;

use App\Models\ProductPrice;
use App\Models\SiteSetting;
use Illuminate\Support\ServiceProvider;
use Plugins\Payments\Observers\ProductPriceObserver;

class PaymentsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->injectStripeConfig();

        ProductPrice::observe(ProductPriceObserver::class);

        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
    }

    /**
     * Copy the three Stripe SiteSetting rows into config('services.stripe.*')
     * — the read path every payment surface uses. Lived in AppServiceProvider
     * until session 380; owned here so that when this plugin is absent the
     * services.stripe.* keys are simply never populated and every capability
     * check degrades to "not configured".
     */
    private function injectStripeConfig(): void
    {
        try {
            config([
                'services.stripe.publishable_key' => SiteSetting::get('stripe_publishable_key', ''),
                'services.stripe.secret'          => SiteSetting::get('stripe_secret_key', ''),
                'services.stripe.webhook_secret'  => SiteSetting::get('stripe_webhook_secret', ''),
            ]);
        } catch (\Throwable $e) {
            // DB not ready (fresh install before migrations) — config stays unpopulated
        }
    }
}
