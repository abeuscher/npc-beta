<?php

use App\Http\Controllers\LlmsTxtController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\DemoLoginController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\MailChimpWebhookController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\WidgetThumbnailController;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;

// MailChimp webhook — no auth, no CSRF. Path is configurable via MAILCHIMP_WEBHOOK_PATH.
$mailchimpPath = config('services.mailchimp.webhook_path', 'mailchimp');
Route::post("/webhooks/{$mailchimpPath}", [MailChimpWebhookController::class, 'handle'])
    ->name('webhooks.mailchimp');
Route::get("/webhooks/{$mailchimpPath}", fn () => response('OK', 200))
    ->name('webhooks.mailchimp.verify');

// The Stripe webhook route (POST /webhooks/stripe) is registered by the
// Payments plugin (plugins/Payments/routes/web.php); the /webhooks/* CSRF
// exemption in bootstrap/app.php stays core path-policy.

// The events routes (api.events.json, events.register, portal.events.register)
// are registered by the Events plugin (vendor/nonprofitcrm/events/routes/web.php).

Route::get('/', [PageController::class, 'home']);

// Canonical homepage: /home 301s to / so a single URL exists. Without this the
// catch-all /{slug} route below resolves the 'home' page at /home as a 200,
// giving the homepage two crawlable URLs.
Route::get('/home', fn () => redirect('/', 301))->name('home.redirect');

// The blog routes (posts.index, posts.show) are registered by the Blog
// plugin (vendor/nonprofitcrm/blog/routes/web.php) ahead of the catch-all below.

// The donation checkout route (donations.checkout) is registered by the
// Donations plugin (vendor/nonprofitcrm/donations/routes/web.php).

// The product checkout and waitlist routes (products.checkout,
// products.waitlist) are registered by the Products plugin
// (plugins/Products/routes/web.php).

// The web form submission route (forms.submit) is registered by the
// Forms plugin (vendor/nonprofitcrm/forms/routes/web.php).

// Form-less demo auto-login — demo server only (guarded inside the controller
// on the same signal isDemoMode() keys on; inert/404 elsewhere). Per-IP
// throttled; reuses one shared, tightly-scoped "Demo User" account.
Route::get('/demo/enter', DemoLoginController::class)
    ->name('demo.enter')
    ->middleware('throttle:10,1');

// ── Portal auth routes ────────────────────────────────────────────────────────
// Registered by the MemberPortal plugin (vendor/nonprofitcrm/member-portal/
// routes/web.php, session 394; extracted 395) — names, URIs, and middleware
// unchanged. Plugin route files
// load at provider boot(), before this file, so the portal GET routes stay
// ahead of the page-slug catch-all below.
// ─────────────────────────────────────────────────────────────────────────────

// Sitemap and robots.txt
Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

// LLMs.txt — AEO file describing the site to LLM crawlers
Route::get('/llms.txt', [LlmsTxtController::class, 'index'])->name('llms-txt');

// Public docs library — marketing instance only (every action 404s unless
// isPublicWebsite()). /docs is a hardcoded reserved prefix, registered before
// the page-slug catchall so a Page can never shadow it. The .md sibling route
// registers first and its slug pattern excludes dots, so /docs/{slug}.md and
// /docs/{slug} match unambiguously.
Route::get('/docs', [DocsController::class, 'index'])->name('docs.index');
Route::get('/docs/{slug}.md', [DocsController::class, 'raw'])
    ->where('slug', '[^/.]+')
    ->name('docs.raw');
Route::get('/docs/{slug}', [DocsController::class, 'show'])
    ->where('slug', '[^/.]+')
    ->name('docs.show');

// llms-full.txt — whole docs corpus in one fetch, marketing instance only
Route::get('/llms-full.txt', [LlmsTxtController::class, 'full'])->name('llms-full-txt');

// Widget thumbnail images (public, static assets served from app/Widgets/*/thumbnails/).
Route::get('/widget-thumbnails/{handle}/{file}', [WidgetThumbnailController::class, 'show'])
    ->name('widget-thumbnails.show');

// Dev-only routes — widget demo surface for thumbnail capture, etc.
if (! App::environment('production')) {
    require base_path('routes/dev.php');
}

// Slug route is registered last so Filament and other named routes take priority.
// The .* pattern allows forward-slash segments (e.g. events/board-meeting).
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '.*');
