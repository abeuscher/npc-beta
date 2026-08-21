<?php

use Illuminate\Support\Facades\Route;
use Plugins\Products\Http\Controllers\ProductCheckoutController;
use Plugins\Products\Http\Controllers\ProductWaitlistController;

// Registered from the plugin provider's boot(), which runs before core's
// routes/web.php loads (at app booted) — so both POST routes stay ahead of
// the page-slug catch-all. Names, URIs, and middleware are identical to the
// pre-carve core registrations (route-list parity is the bar).
Route::middleware('web')->group(function (): void {
    // Product checkout and waitlist
    Route::post('/products/checkout', [ProductCheckoutController::class, 'store'])
        ->name('products.checkout')
        ->middleware('throttle:20,1');
    Route::post('/products/waitlist', [ProductWaitlistController::class, 'store'])
        ->name('products.waitlist')
        ->middleware('throttle:10,1');
});
