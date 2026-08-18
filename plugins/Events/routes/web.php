<?php

use Illuminate\Support\Facades\Route;
use Plugins\Events\Http\Controllers\EventController;
use Plugins\Events\Http\Controllers\Portal\EventRegistrationController as PortalEventRegistrationController;

// Registered from the plugin provider's boot(), which runs before core's
// routes/web.php loads (at app booted) — so the JSON route stays ahead of the
// page-slug catch-all. Names, URIs, and middleware are identical to the
// pre-carve core registrations (route-list parity is the bar).
Route::middleware('web')->group(function (): void {
    // Public event JSON endpoint — feeds the calendar widget
    Route::get('/api/events.json', function () {
        return \App\Models\Event::query()
            ->published()
            ->where('starts_at', '>=', now()->subMonths(6))
            ->orderBy('starts_at')
            ->get()
            ->map(fn ($e) => [
                'id'          => $e->id,
                'title'       => $e->title,
                'from'        => $e->starts_at?->toIso8601String(),
                'to'          => $e->ends_at?->toIso8601String(),
                'description' => $e->description ? \Illuminate\Support\Str::limit(strip_tags($e->description), 200) : null,
                'url'         => $e->landingPage ? url($e->landingPage->slug) : null,
            ]);
    })->name('api.events.json');

    // Event registration — GET routes are served by PageController via page slugs
    $eventsPrefix = config('site.events_prefix', 'events');
    Route::post("/{$eventsPrefix}/{slug}/register", [EventController::class, 'register'])
        ->name('events.register')
        ->middleware('throttle:10,1');

    Route::post('/account/events/{slug}/register', [PortalEventRegistrationController::class, 'store'])
        ->name('portal.events.register')
        ->middleware(['portal.auth', 'verified:portal.verification.notice']);
});
