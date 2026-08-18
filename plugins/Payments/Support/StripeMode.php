<?php

namespace Plugins\Payments\Support;

use App\Models\SiteSetting;
use App\Payments\Contracts\PaymentModeProvider;

/**
 * Detects whether the install is configured against a LIVE Stripe key
 * (session 370, Security S1). Stripe secret keys self-identify by prefix:
 * `sk_live_` / `rk_live_` are live, `sk_test_` / `rk_test_` are test. An
 * unconfigured install (empty key) is not live.
 *
 * Used to gate the Random Data Generator: synthetic scrub data — including fake
 * donations and transactions — must never be generated on a real-payments
 * install, where it could mingle with genuine donor records. Core consumers
 * reach it through App\Payments\PaymentMode (session 380 — this class is the
 * plugin's PaymentModeProvider binding; it reads the SiteSetting directly, not
 * config, so the sniff answers even before config injection runs).
 */
class StripeMode implements PaymentModeProvider
{
    public function isLive(): bool
    {
        $secret = (string) SiteSetting::get('stripe_secret_key', '');

        return str_starts_with($secret, 'sk_live_') || str_starts_with($secret, 'rk_live_');
    }
}
