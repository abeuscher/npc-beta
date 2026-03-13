<?php

use App\Http\Controllers\PageController;
use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home']);

// Blog routes — prefix is config-driven, defaults to 'news'
$blogPrefix = config('site.blog_prefix', 'news');
Route::get("/{$blogPrefix}", [PostController::class, 'index'])->name('posts.index');
Route::get("/{$blogPrefix}/{slug}", [PostController::class, 'show'])->name('posts.show');

// Slug route is registered last so Filament and other named routes take priority.
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '[a-z0-9\-]+');
