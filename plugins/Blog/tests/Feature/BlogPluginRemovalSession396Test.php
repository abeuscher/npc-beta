<?php

use App\Models\Page;
use App\Widgets\QuickActions\QuickActionsDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Plugins\BlogRemovedTestCase;

uses(BlogRemovedTestCase::class, RefreshDatabase::class);

// Session 396 (Plugin Architecture arc D6): the remove-the-line mirror for
// the Blog vertical. The application boots with the Blog line stripped from
// config('plugins'); the named routes, both widgets, and the admin surface
// must vanish — while post CONTENT stays reachable: posts are core Page rows
// (the plugin owns no schema), so the catch-all serves them as plain pages.
// The assertion is listing-absent, not page-absent.

it('does not load the Blog provider', function () {
    expect(config('plugins'))->not->toContain(\Plugins\Blog\BlogServiceProvider::class)
        ->and(app()->providerIsLoaded(\Plugins\Blog\BlogServiceProvider::class))->toBeFalse();
});

it('drops both named blog routes', function () {
    expect(Route::has('posts.index'))->toBeFalse()
        ->and(Route::has('posts.show'))->toBeFalse();
});

it('drops the four PostResource admin routes', function () {
    foreach ([
        'filament.admin.resources.posts.index',
        'filament.admin.resources.posts.create',
        'filament.admin.resources.posts.edit',
        'filament.admin.resources.posts.details',
    ] as $name) {
        expect(Route::has($name))->toBeFalse("route {$name} should be gone");
    }
});

it('serves the blog index page 200 through the catch-all — listing-absent, not page-absent', function () {
    Page::factory()->create([
        'slug'         => 'news',
        'title'        => 'News',
        'type'         => 'default',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/news')->assertOk();
});

it('keeps post rows reachable through the catch-all — posts are core Page content', function () {
    Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Still Reachable',
        'slug'         => 'news/still-reachable',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/news/still-reachable')->assertOk();

    expect(Page::where('type', 'post')->count())->toBe(1);
});

it('drops both blog widget rows on the next widgets:sync', function () {
    $this->artisan('widgets:sync')->assertSuccessful();

    $handles = \App\Models\WidgetType::pluck('handle');

    expect($handles)->not->toContain('blog_listing')
        ->and($handles)->not->toContain('blog_pager');
});

it('yields a null New Post quick-action URL instead of a missing class', function () {
    expect((QuickActionsDefinition::actionRegistry()['new_post']['url'])())->toBeNull();
});
