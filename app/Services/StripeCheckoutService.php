<?php

namespace App\Services;

use App\Filament\Pages\Settings\FinanceSettingsPage;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;
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
        ?string $submitType = null,
        array $extra = [],
    ): Session {
        $params = $this->buildParams(
            lineItems: $lineItems,
            metadata: $metadata,
            successUrl: $successUrl,
            cancelUrl: $cancelUrl,
            mode: $mode,
            submitType: $submitType,
            extra: $extra,
        );

        $secret = config('services.stripe.secret');
        $stripe = new StripeClient($secret);

        return $stripe->checkout->sessions->create($params);
    }

    public function buildParams(
        array $lineItems,
        array $metadata,
        string $successUrl,
        string $cancelUrl,
        string $mode = 'payment',
        ?string $submitType = null,
        array $extra = [],
    ): array {
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

        $customText = $this->buildCustomText();
        if ($customText !== []) {
            $params['custom_text'] = $customText;
        }

        if ($mode === 'payment' && $submitType !== null) {
            $params['submit_type'] = $submitType;
        }

        if ($mode === 'payment') {
            $piData     = $params['payment_intent_data'] ?? [];
            $descriptor = self::statementDescriptor();
            $suffix     = self::statementDescriptorSuffix();

            if ($descriptor !== '') {
                $piData['statement_descriptor'] = $descriptor;
            }
            if ($suffix !== '') {
                $piData['statement_descriptor_suffix'] = $suffix;
            }
            if ($piData !== []) {
                $params['payment_intent_data'] = $piData;
            }
        }

        if (self::tosUrlConfigured()) {
            $consent                     = $params['consent_collection'] ?? [];
            $consent['terms_of_service'] = 'required';
            $params['consent_collection'] = $consent;
        }

        return $params;
    }

    public static function defaultImageUrl(string $flow): ?string
    {
        $key = match ($flow) {
            'donation'   => 'stripe_default_donation_image',
            'event'      => 'stripe_default_event_image',
            'product'    => 'stripe_default_product_image',
            'membership' => 'stripe_default_membership_image',
            default      => null,
        };

        if ($key === null) {
            return null;
        }

        $path = trim((string) SiteSetting::get($key, ''));
        if ($path === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    private function buildCustomText(): array
    {
        $custom = [];

        $submit = trim((string) SiteSetting::get('stripe_checkout_submit_text', ''));
        if ($submit !== '') {
            $custom['submit'] = ['message' => $submit];
        }

        $afterSubmit = trim((string) SiteSetting::get('stripe_checkout_after_submit_text', ''));
        if ($afterSubmit !== '') {
            $custom['after_submit'] = ['message' => $afterSubmit];
        }

        if (self::tosUrlConfigured()) {
            $tos = trim((string) SiteSetting::get('stripe_checkout_terms_acceptance_text', ''));
            if ($tos !== '') {
                $custom['terms_of_service_acceptance'] = ['message' => $tos];
            }
        }

        return $custom;
    }

    private static function statementDescriptor(): string
    {
        return trim((string) SiteSetting::get('stripe_statement_descriptor', ''));
    }

    private static function statementDescriptorSuffix(): string
    {
        return trim((string) SiteSetting::get('stripe_statement_descriptor_suffix', ''));
    }

    private static function tosUrlConfigured(): bool
    {
        return SiteSetting::get('stripe_tos_url_configured', 'false') === 'true';
    }
}
