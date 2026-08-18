<?php

use App\Http\Controllers\QuickBooksCallbackController;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin routes — QuickBooks OAuth
|--------------------------------------------------------------------------
*/

Route::get('/quickbooks/connect', [QuickBooksCallbackController::class, 'connect'])
    ->name('quickbooks.connect')
    ->middleware(Authenticate::class);
Route::get('/quickbooks/callback', [QuickBooksCallbackController::class, 'callback'])
    ->name('quickbooks.callback')
    ->middleware(Authenticate::class);
Route::post('/quickbooks/disconnect', [QuickBooksCallbackController::class, 'disconnect'])
    ->name('quickbooks.disconnect')
    ->middleware(Authenticate::class);
