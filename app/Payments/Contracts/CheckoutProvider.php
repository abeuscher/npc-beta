<?php

namespace App\Payments\Contracts;

/**
 * Core-owned checkout contract (session 380, arc P4). Verticals resolve this
 * interface — never a concrete class from the Payments plugin — after guarding
 * on CapabilityRegistry::enabled('payments'); the plugin binds its
 * implementation in its provider's register(), so the binding exists exactly
 * when the capability is present.
 *
 * createSession() returns the provider's session object; callers rely only on
 * its ->id and ->url properties (the redirect handle and the persisted
 * session identifier). The parameter shapes are the pre-carve
 * StripeCheckoutService signatures, unchanged.
 */
interface CheckoutProvider
{
    public function createSession(
        array $lineItems,
        array $metadata,
        string $successUrl,
        string $cancelUrl,
        string $mode = 'payment',
        ?string $submitType = null,
        array $extra = [],
    ): object;

    public function defaultImageUrl(string $flow): ?string;
}
