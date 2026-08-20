<?php

use Illuminate\Support\Facades\Route;
use Plugins\Blog\Http\Controllers\PostController;

// Registered from the plugin provider's boot(), which runs before core's
// routes/web.php loads (at app booted) — so the two GET routes stay ahead of
// the page-slug catch-all. Names, URIs, and middleware are identical to the
// pre-carve core registrations (route-list parity is the bar).
Route::middleware('web')->group(function (): void {
    // Blog routes — prefix is config-driven
    $blogPrefix = config('site.blog_prefix', 'news');

    Route::get("/{$blogPrefix}", [PostController::class, 'index'])->name('posts.index');
    Route::get("/{$blogPrefix}/{slug}", [PostController::class, 'show'])->name('posts.show');
});
