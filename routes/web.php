<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\FormSubmissionController;
use App\Http\Controllers\MailChimpWebhookController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Portal\AccountController;
use App\Http\Controllers\Portal\EmailVerificationController;
use App\Http\Controllers\Portal\ForgotPasswordController;
use App\Http\Controllers\Portal\LoginController;
use App\Http\Controllers\Portal\ResetPasswordController;
use App\Http\Controllers\Portal\SignupController;
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

// Web form submissions
Route::post('/forms/{handle}', [FormSubmissionController::class, 'store'])
    ->name('forms.submit')
    ->middleware('throttle:10,1');

// ── Portal auth routes ────────────────────────────────────────────────────────
Route::get('/signup',  [SignupController::class, 'show'])->name('portal.signup');
Route::post('/signup', [SignupController::class, 'store'])->name('portal.signup.post')->middleware('throttle:10,1');

Route::get('/login',   [LoginController::class, 'show'])->name('portal.login');
Route::post('/login',  [LoginController::class, 'store'])->name('portal.login.post')->middleware('throttle:10,1');
Route::post('/logout', [LoginController::class, 'destroy'])->name('portal.logout');

Route::get('/forgot-password',        [ForgotPasswordController::class, 'show'])->name('portal.password.request');
Route::get('/forgot-password/sent',   [ForgotPasswordController::class, 'sent'])->name('portal.password.sent');
Route::post('/forgot-password',       [ForgotPasswordController::class, 'store'])->name('portal.password.email')->middleware('throttle:5,1');
Route::get('/reset-password/{token}', [ResetPasswordController::class, 'show'])->name('portal.password.reset');
Route::post('/reset-password',        [ResetPasswordController::class, 'update'])->name('portal.password.update');

Route::get('/email/verify',            [EmailVerificationController::class, 'notice'])->name('portal.verification.notice')->middleware('portal.auth');
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->name('portal.verification.verify')->middleware(['portal.auth', 'signed']);

$portalAuth = ['portal.auth', 'verified:portal.verification.notice'];

Route::get('/account', function () {
    return redirect(url('/' . \App\Models\SiteSetting::get('portal_prefix', 'members')));
})->name('portal.account')->middleware($portalAuth);

Route::patch('/account/address', [AccountController::class, 'updateAddress'])->name('portal.account.update-address')->middleware($portalAuth);
Route::patch('/account/password', [AccountController::class, 'updatePassword'])->name('portal.account.update-password')->middleware($portalAuth);
Route::post('/account/email',        [AccountController::class, 'requestEmailChange'])->name('portal.account.request-email-change')->middleware(array_merge($portalAuth, ['throttle:5,1']));
Route::get('/account/email/confirm', [AccountController::class, 'confirmEmailChange'])->name('portal.account.confirm-email')->middleware($portalAuth);
// ─────────────────────────────────────────────────────────────────────────────

// Slug route is registered last so Filament and other named routes take priority.
// The .* pattern allows forward-slash segments (e.g. events/board-meeting).
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '.*');
