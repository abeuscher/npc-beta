<?php

use Illuminate\Support\Facades\Route;
use Plugins\Donations\Http\Controllers\DonationCheckoutController;

// Registered from the plugin provider's boot(), which runs before core's
// routes/web.php loads (at app booted) — so the checkout POST registers ahead
// of the page-slug catch-all. Name, URI, and middleware are identical to the
// pre-carve core registration (route-list parity is the bar).
Route::middleware('web')->group(function (): void {
    // Donation checkout
    $donationsPrefix = config('site.donations_prefix', 'donate');
    Route::post("/{$donationsPrefix}/checkout", [DonationCheckoutController::class, 'store'])
        ->name('donations.checkout')
        ->middleware('throttle:20,1');
});
