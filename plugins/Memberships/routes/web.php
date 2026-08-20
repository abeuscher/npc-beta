<?php

use Illuminate\Support\Facades\Route;
use Plugins\Memberships\Http\Controllers\MembershipCheckoutController;

// Registered from the plugin provider's boot(), which runs before core's
// routes/web.php loads (at app booted) — so the checkout POST registers ahead
// of the page-slug catch-all. Name, URI, and middleware are identical to the
// pre-carve core registration (route-list parity is the bar); the core
// PortalSignup widget renders route('membership.checkout') by this name
// (the D4/D5 portal seam — preserved, not changed).
Route::middleware('web')->group(function (): void {
    // Membership checkout
    Route::post('/membership/checkout', [MembershipCheckoutController::class, 'store'])
        ->name('membership.checkout')
        ->middleware('throttle:10,1');
});
