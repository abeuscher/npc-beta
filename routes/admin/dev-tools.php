<?php

use App\Http\Controllers\Admin\RandomDataGeneratorController;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin routes — dev tools (Random Data Generator)
|--------------------------------------------------------------------------
*/

Route::middleware(Authenticate::class)
    ->prefix('dev-tools')
    ->name('dev-tools.')
    ->group(function () {
        Route::post('/random-data', [RandomDataGeneratorController::class, 'store'])
            ->name('random-data.store');
        Route::post('/random-data/wipe', [RandomDataGeneratorController::class, 'wipe'])
            ->name('random-data.wipe');
        Route::post('/random-data/seed-collections', [RandomDataGeneratorController::class, 'seedCollections'])
            ->name('random-data.seed-collections');
    });
