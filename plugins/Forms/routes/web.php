<?php

use Illuminate\Support\Facades\Route;
use Plugins\Forms\Http\Controllers\FormSubmissionController;

// Registered from the plugin provider's boot(), which runs before core's
// routes/web.php loads (at app booted) — so the POST route stays ahead of
// the page-slug catch-all. Name, URI, and middleware are identical to the
// pre-carve core registration (route-list parity is the bar).
Route::middleware('web')->group(function (): void {
    // Web form submissions
    Route::post('/forms/{handle}', [FormSubmissionController::class, 'store'])
        ->name('forms.submit')
        ->middleware('throttle:10,1');
});
