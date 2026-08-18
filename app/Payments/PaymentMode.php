<?php

namespace App\Payments;

use App\Payments\Contracts\PaymentModeProvider;

/**
 * Core entry point for "is this install configured against LIVE payments?"
 * (session 370's Random Data Generator guard, re-seamed at session 380 when
 * the Stripe key sniff moved into the Payments plugin). Static so widget
 * templates may call it under the template-purity rules; the plugin's
 * PaymentModeProvider binding answers when present, and an install with no
 * payments plugin is never live.
 */
class PaymentMode
{
    public static function isLive(): bool
    {
        return app()->bound(PaymentModeProvider::class)
            && app(PaymentModeProvider::class)->isLive();
    }
}
