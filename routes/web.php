<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home']);

// Blog routes — prefix is config-driven
$blogPrefix = config('site.blog_prefix', 'news');
Route::get("/{$blogPrefix}", [PostController::class, 'index'])->name('posts.index');
Route::get("/{$blogPrefix}/{slug}", [PostController::class, 'show'])->name('posts.show');

// Event routes — prefix is config-driven
$eventsPrefix = config('site.events_prefix', 'events');
Route::get("/{$eventsPrefix}", [EventController::class, 'index'])->name('events.index');
Route::get("/{$eventsPrefix}/{slug}", [EventController::class, 'show'])->name('events.show');
Route::post("/{$eventsPrefix}/{slug}/register", [EventController::class, 'register'])
    ->name('events.register')
    ->middleware('throttle:10,1');

// Slug route is registered last so Filament and other named routes take priority.
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '[a-z0-9\-]+');
