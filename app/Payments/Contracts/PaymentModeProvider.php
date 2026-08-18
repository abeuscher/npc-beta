<?php

namespace App\Payments\Contracts;

/**
 * Core-owned live-payments question (session 380, arc P4). The payments
 * plugin binds an implementation whose isLive() detects a live-mode payment
 * credential; core consumers ask through App\Payments\PaymentMode, never by
 * referencing the plugin. With no binding (payments plugin absent), core
 * treats the install as not live.
 */
interface PaymentModeProvider
{
    public function isLive(): bool;
}
