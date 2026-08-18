<?php

use App\Filament\Pages\TwoFactorChallenge;
use App\Filament\Pages\TwoFactorSetup;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin routes — two-factor authentication
|--------------------------------------------------------------------------
|
| Two-factor authentication flow (session 359, A5). Registered in the panel's
| ->routes() group rather than via page auto-discovery because SimplePage
| isn't routable on its own — this gives the enrollment and challenge screens
| the simple, sidebar-free auth layout and the canonical
| `filament.admin.pages.*` route names. They sit behind Authenticate (must be
| logged in) but deliberately OUTSIDE the panel's authMiddleware, so the 2FA
| enforcement gate that lives there can never trap these pages in a redirect
| loop.
|
*/

Route::get('/two-factor-setup', TwoFactorSetup::class)
    ->name('pages.two-factor-setup')
    ->middleware(Authenticate::class);
Route::get('/two-factor-challenge', TwoFactorChallenge::class)
    ->name('pages.two-factor-challenge')
    ->middleware(Authenticate::class);
