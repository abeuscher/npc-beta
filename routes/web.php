<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\MailChimpWebhookController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

// MailChimp webhook — no auth, no CSRF. Path is configurable via MAILCHIMP_WEBHOOK_PATH.
$mailchimpPath = config('services.mailchimp.webhook_path', 'mailchimp');
Route::post("/webhooks/{$mailchimpPath}", [MailChimpWebhookController::class, 'handle'])
    ->name('webhooks.mailchimp');
Route::get("/webhooks/{$mailchimpPath}", fn () => response('OK', 200))
    ->name('webhooks.mailchimp.verify');

Route::get('/', [PageController::class, 'home']);

// Blog routes — prefix is config-driven
$blogPrefix = config('site.blog_prefix', 'news');
Route::get("/{$blogPrefix}", [PostController::class, 'index'])->name('posts.index');
Route::get("/{$blogPrefix}/{slug}", [PostController::class, 'show'])->name('posts.show');

// Event registration — GET routes removed (served by PageController via page slugs)
$eventsPrefix = config('site.events_prefix', 'events');
Route::post("/{$eventsPrefix}/{slug}/register", [EventController::class, 'register'])
    ->name('events.register')
    ->middleware('throttle:10,1');

// Slug route is registered last so Filament and other named routes take priority.
// The .* pattern allows forward-slash segments (e.g. events/board-meeting).
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '.*');
