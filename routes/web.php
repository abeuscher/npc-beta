<?php

use App\Http\Controllers\SitemapController;
use App\Http\Controllers\EventCheckoutController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\MembershipCheckoutController;
use App\Http\Controllers\FormSubmissionController;
use App\Http\Controllers\DonationCheckoutController;
use App\Http\Controllers\ProductCheckoutController;
use App\Http\Controllers\ProductWaitlistController;
use App\Http\Controllers\MailChimpWebhookController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Portal\AccountController;
use App\Http\Controllers\Portal\EmailVerificationController;
use App\Http\Controllers\Portal\EventCheckoutController as PortalEventCheckoutController;
use App\Http\Controllers\Portal\EventRegistrationController as PortalEventRegistrationController;
use App\Http\Controllers\Portal\ForgotPasswordController;
use App\Http\Controllers\Portal\LoginController;
use App\Http\Controllers\Portal\ResetPasswordController;
use App\Http\Controllers\Portal\SignupController;
use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;

// MailChimp webhook — no auth, no CSRF. Path is configurable via MAILCHIMP_WEBHOOK_PATH.
$mailchimpPath = config('services.mailchimp.webhook_path', 'mailchimp');
Route::post("/webhooks/{$mailchimpPath}", [MailChimpWebhookController::class, 'handle'])
    ->name('webhooks.mailchimp');
Route::get("/webhooks/{$mailchimpPath}", fn () => response('OK', 200))
    ->name('webhooks.mailchimp.verify');

// Stripe webhook — no auth, no CSRF (covered by /webhooks/* exemption in bootstrap/app.php).
// Signature is verified inside the controller using the webhook secret.
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->name('webhooks.stripe');

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
Route::post("/{$eventsPrefix}/{slug}/checkout", [EventCheckoutController::class, 'store'])
    ->name('events.checkout')
    ->middleware('throttle:10,1');

// Donation checkout
$donationsPrefix = config('site.donations_prefix', 'donate');
Route::post("/{$donationsPrefix}/checkout", [DonationCheckoutController::class, 'store'])
    ->name('donations.checkout')
    ->middleware('throttle:20,1');

// Product checkout and waitlist
Route::post('/products/checkout', [ProductCheckoutController::class, 'store'])
    ->name('products.checkout')
    ->middleware('throttle:20,1');
Route::post('/products/waitlist', [ProductWaitlistController::class, 'store'])
    ->name('products.waitlist')
    ->middleware('throttle:10,1');

// Web form submissions
Route::post('/forms/{handle}', [FormSubmissionController::class, 'store'])
    ->name('forms.submit')
    ->middleware('throttle:10,1');

// ── Portal auth routes ────────────────────────────────────────────────────────
// GET routes use the system_prefix so all system pages live under /system/*.
// POST routes stay at root paths so form actions don't depend on the prefix value.
$systemPrefix = config('site.system_prefix', 'system');
$systemBase   = $systemPrefix ? '/' . $systemPrefix : '';

Route::get("{$systemBase}/signup",  [SignupController::class, 'show'])->name('portal.signup');
Route::post('/signup', [SignupController::class, 'store'])->name('portal.signup.post')->middleware('throttle:10,1');
Route::post('/membership/checkout', [MembershipCheckoutController::class, 'store'])->name('membership.checkout')->middleware('throttle:10,1');

Route::get("{$systemBase}/login",   [LoginController::class, 'show'])->name('portal.login');
Route::post('/login',  [LoginController::class, 'store'])->name('portal.login.post')->middleware('throttle:10,1');
Route::post('/logout', [LoginController::class, 'destroy'])->name('portal.logout');

Route::get("{$systemBase}/forgot-password",        [ForgotPasswordController::class, 'show'])->name('portal.password.request');
Route::get("{$systemBase}/forgot-password/sent",   [ForgotPasswordController::class, 'sent'])->name('portal.password.sent');
Route::post('/forgot-password',       [ForgotPasswordController::class, 'store'])->name('portal.password.email')->middleware('throttle:5,1');
Route::get("{$systemBase}/reset-password/{token}", [ResetPasswordController::class, 'show'])->name('portal.password.reset');
Route::post('/reset-password',        [ResetPasswordController::class, 'update'])->name('portal.password.update');

Route::get("{$systemBase}/email/verify",             [EmailVerificationController::class, 'notice'])->name('portal.verification.notice')->middleware('portal.auth');
Route::get("{$systemBase}/email/verify/{id}/{hash}", [EmailVerificationController::class, 'verify'])->name('portal.verification.verify')->middleware(['portal.auth', 'signed']);

$portalAuth = ['portal.auth', 'verified:portal.verification.notice'];

Route::get("{$systemBase}/account", function () {
    $prefix = \App\Models\SiteSetting::get('system_prefix', 'system');
    $slug   = $prefix ? $prefix . '/account' : 'account';

    return app(\App\Http\Controllers\PageController::class)->show($slug);
})->name('portal.account')->middleware($portalAuth);

Route::post('/account/events/{slug}/register', [PortalEventRegistrationController::class, 'store'])->name('portal.events.register')->middleware($portalAuth);
Route::post('/account/events/{slug}/checkout', [PortalEventCheckoutController::class, 'store'])->name('portal.events.checkout')->middleware($portalAuth);

Route::patch('/account/address', [AccountController::class, 'updateAddress'])->name('portal.account.update-address')->middleware($portalAuth);
Route::patch('/account/password', [AccountController::class, 'updatePassword'])->name('portal.account.update-password')->middleware($portalAuth);
Route::post('/account/email',        [AccountController::class, 'requestEmailChange'])->name('portal.account.request-email-change')->middleware(array_merge($portalAuth, ['throttle:5,1']));
Route::get('/account/email/confirm', [AccountController::class, 'confirmEmailChange'])->name('portal.account.confirm-email')->middleware($portalAuth);
// ─────────────────────────────────────────────────────────────────────────────

// Sitemap and robots.txt
Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

// Dev-only routes — widget demo surface for thumbnail capture, etc.
if (! App::environment('production')) {
    require base_path('routes/dev.php');
}

// Slug route is registered last so Filament and other named routes take priority.
// The .* pattern allows forward-slash segments (e.g. events/board-meeting).
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '.*');
