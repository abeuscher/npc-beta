<?php

use App\Http\Controllers\Admin\BundleExportDownloadController;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin routes — export downloads
|--------------------------------------------------------------------------
*/

// Gated download of a queued export artifact (session 303).
Route::get('/exports/bundles/{token}', BundleExportDownloadController::class)
    ->name('exports.bundle.download')
    ->middleware(Authenticate::class);
