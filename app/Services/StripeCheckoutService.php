<?php

namespace App\Services;

use App\Filament\Pages\Settings\FinanceSettingsPage;
use App\Models\SiteSetting;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

class StripeCheckoutService
{
    public function createSession(
        array $lineItems,
        array $metadata,
        string $successUrl,
        string $cancelUrl,
        string $mode = 'payment',
        array $extra = [],
    ): Session {
        $secret = config('services.stripe.secret');
        $stripe = new StripeClient($secret);

        $configuredMethods = SiteSetting::get('stripe_payment_method_types') ?? ['card'];
        if (empty($configuredMethods)) {
            $configuredMethods = ['card'];
        }

        if ($mode === 'subscription') {
            $paymentMethods = array_values(array_intersect(
                $configuredMethods,
                FinanceSettingsPage::SUBSCRIPTION_COMPATIBLE_METHODS,
            ));
            if (empty($paymentMethods)) {
                $paymentMethods = ['card'];
            }
        } else {
            $paymentMethods = array_values($configuredMethods);
        }

        $params = array_merge([
            'mode'                 => $mode,
            'payment_method_types' => $paymentMethods,
            'line_items'           => $lineItems,
            'metadata'             => $metadata,
            'success_url'          => $successUrl,
            'cancel_url'           => $cancelUrl,
        ], $extra);

        return $stripe->checkout->sessions->create($params);
    }
}
