<?php

use Illuminate\Support\Facades\Route;
use Plugins\Payments\Http\Controllers\StripeWebhookController;

// Stripe webhook — no auth, no CSRF (covered by the /webhooks/* exemption in
// bootstrap/app.php, which is core path-policy, not plugin behavior).
// Signature is verified inside the controller using the webhook secret.
Route::middleware('web')->group(function (): void {
    Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
        ->name('webhooks.stripe');
});
