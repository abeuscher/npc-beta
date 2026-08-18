<?php

use App\Http\Controllers\Admin\SetupChecklistController;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin routes — setup checklist widget actions
|--------------------------------------------------------------------------
*/

Route::middleware(Authenticate::class)
    ->prefix('setup-checklist')
    ->name('setup-checklist.')
    ->group(function () {
        Route::post('/mark-complete', [SetupChecklistController::class, 'markComplete'])
            ->name('mark-complete');
        Route::post('/reset', [SetupChecklistController::class, 'reset'])
            ->name('reset');
    });
