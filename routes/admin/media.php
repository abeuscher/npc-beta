<?php

use App\Http\Controllers\Admin\HeroiconController;
use App\Http\Controllers\Admin\InlineImageUploadController;
use App\Http\Controllers\Admin\MediaDedupController;
use App\Http\Middleware\BlockDemoUploads;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin routes — media upload, dedup, and icon lookup
|--------------------------------------------------------------------------
*/

// New-file upload — gated against the demo role (session 329).
Route::post('/inline-image-upload', [InlineImageUploadController::class, 'store'])
    ->name('inline-image-upload')
    ->middleware([Authenticate::class, BlockDemoUploads::class]);

Route::post('/media-dedup-check', [MediaDedupController::class, 'check'])
    ->name('media-dedup-check')
    ->middleware(Authenticate::class);

Route::get('/heroicons', [HeroiconController::class, 'index'])
    ->name('heroicons.index')
    ->middleware(Authenticate::class);
