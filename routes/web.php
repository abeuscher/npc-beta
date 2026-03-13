<?php

use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home']);

// Slug route is registered last so Filament and other named routes take priority.
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '[a-z0-9\-]+');
