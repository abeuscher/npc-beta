<?php

use Illuminate\Support\Facades\Route;
use Plugins\MemberPortal\Http\Controllers\AccountController;
use Plugins\MemberPortal\Http\Controllers\EmailVerificationController;
use Plugins\MemberPortal\Http\Controllers\ForgotPasswordController;
use Plugins\MemberPortal\Http\Controllers\LoginController;
use Plugins\MemberPortal\Http\Controllers\ResetPasswordController;
use Plugins\MemberPortal\Http\Controllers\SignupController;

// Registered from the plugin provider's boot(), which runs before core's
// routes/web.php loads (at app booted) — so the GET routes stay ahead of the
// page-slug catch-all. Names, URIs, and middleware are identical to the
// pre-carve core registrations (route-list parity is the bar).
Route::middleware('web')->group(function (): void {
    // GET routes use the system_prefix so all system pages live under /system/*.
    // POST routes stay at root paths so form actions don't depend on the prefix value.
    $systemPrefix = config('site.system_prefix', 'system');
    $systemBase   = $systemPrefix ? '/' . $systemPrefix : '';

    Route::get("{$systemBase}/signup",  [SignupController::class, 'show'])->name('portal.signup');
    Route::post('/signup', [SignupController::class, 'store'])->name('portal.signup.post')->middleware('throttle:10,1');

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

    // Legacy alias. The member home moved to the dashboard at the portal prefix
    // (/members, a type=member page; session 337). Kept as a named redirect so the
    // existing route('portal.account') callers (post-login, email-verify,
    // forgot-password, account updates) land on the dashboard without churn.
    Route::get("{$systemBase}/account", function () {
        $prefix = \App\Models\SiteSetting::get('portal_prefix', 'members');

        return redirect('/' . $prefix);
    })->name('portal.account')->middleware($portalAuth);

    Route::patch('/account/address', [AccountController::class, 'updateAddress'])->name('portal.account.update-address')->middleware($portalAuth);
    Route::patch('/account/password', [AccountController::class, 'updatePassword'])->name('portal.account.update-password')->middleware($portalAuth);
    Route::post('/account/email',        [AccountController::class, 'requestEmailChange'])->name('portal.account.request-email-change')->middleware(array_merge($portalAuth, ['throttle:5,1']));
    Route::get('/account/email/confirm', [AccountController::class, 'confirmEmailChange'])->name('portal.account.confirm-email')->middleware($portalAuth);
});
