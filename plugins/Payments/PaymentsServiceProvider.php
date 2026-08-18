<?php

namespace Plugins\Payments;

use App\Models\ProductPrice;
use App\Models\SiteSetting;
use App\Payments\Contracts\CheckoutProvider;
use App\Payments\Contracts\PaymentModeProvider;
use App\Plugins\CapabilityRegistry;
use Illuminate\Support\ServiceProvider;
use Plugins\Payments\Observers\ProductPriceObserver;
use Plugins\Payments\Services\StripeCheckoutService;
use Plugins\Payments\Support\StripeMode;

class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The capability resolver carries the same truthiness the five
        // pre-carve controller guards tested (empty config secret = not
        // configured), evaluated lazily so runtime config changes are seen.
        $this->app->make(CapabilityRegistry::class)->provide(
            'payments',
            fn (): bool => filled(config('services.stripe.secret')),
        );

        $this->app->bind(CheckoutProvider::class, StripeCheckoutService::class);
        $this->app->bind(PaymentModeProvider::class, StripeMode::class);
    }

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
