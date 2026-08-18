<?php

use App\Http\Controllers\Admin\ThemeTypographyController;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin routes — theme editor (Vue typography island + SCSS export)
|--------------------------------------------------------------------------
*/

Route::middleware(Authenticate::class)
    ->group(function () {
        Route::post('/api/theme/typography', [ThemeTypographyController::class, 'update'])
            ->name('theme.typography.update');
        Route::get('/design-system/typography/export.scss', [ThemeTypographyController::class, 'export'])
            ->name('theme.typography.export');
        Route::post('/api/theme/typography/rebuild', [ThemeTypographyController::class, 'rebuild'])
            ->name('theme.typography.rebuild');
    });
