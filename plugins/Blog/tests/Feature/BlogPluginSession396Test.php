<?php

use App\Plugins\CapabilityRegistry;
use App\Services\WidgetRegistry;
use App\Widgets\QuickActions\QuickActionsDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Session 396 (Plugin Architecture arc D6): the enabled twin for the carved
// Blog vertical. The full composition must be behavior-identical to the
// pre-carve state: same routes, same widgets, same admin surface. The plugin
// declares NO capability — nothing consumes blog presence; the presence
// signal, were one ever needed, is route presence (Route::has('posts.index')).

it('loads the Blog provider as the seventh plugin', function () {
    expect(config('plugins'))->toContain(\Plugins\Blog\BlogServiceProvider::class)
        ->and(app()->providerIsLoaded(\Plugins\Blog\BlogServiceProvider::class))->toBeTrue();
});

it('declares no blog capability — nothing consumes blog presence', function () {
    expect(app(CapabilityRegistry::class)->present('blog'))->toBeFalse();
});

it('registers both blog_prefix routes with their pre-carve names, ahead of the catch-all', function () {
    expect(Route::has('posts.index'))->toBeTrue()
        ->and(Route::has('posts.show'))->toBeTrue()
        ->and(Route::getRoutes()->getByName('posts.index')->uri())->toBe('news')
        ->and(Route::getRoutes()->getByName('posts.show')->uri())->toBe('news/{slug}')
        ->and(Route::getRoutes()->getByName('posts.index')->getActionName())
            ->toBe(\Plugins\Blog\Http\Controllers\PostController::class . '@index');
});

it('registers the four PostResource admin routes through the admin socket', function () {
    foreach ([
        'filament.admin.resources.posts.index',
        'filament.admin.resources.posts.create',
        'filament.admin.resources.posts.edit',
        'filament.admin.resources.posts.details',
    ] as $name) {
        expect(Route::has($name))->toBeTrue("route {$name} should exist");
    }
});

it('registers both blog widgets from the plugin provider', function () {
    $handles = collect(app(WidgetRegistry::class)->all())->map(fn ($d) => $d->handle());

    expect($handles)->toContain('blog_listing')
        ->and($handles)->toContain('blog_pager');
});

it('declares the core-owned shared pager stylesheet as a BlogListing build input (ADR 0007)', function () {
    $assets = (new \Plugins\Blog\Widgets\BlogListing\BlogListingDefinition())->assets();

    expect($assets['scss'])->toContain('resources/scss/widgets/_shared-pager.scss')
        ->and(file_exists(base_path('resources/scss/widgets/_shared-pager.scss')))->toBeTrue();
});

it('resolves the New Post quick action by route name', function () {
    $url = (QuickActionsDefinition::actionRegistry()['new_post']['url'])();

    expect($url)->toBe(route('filament.admin.resources.posts.create'));
});

it('registers the plugin view namespaces', function () {
    expect(view()->exists('plugin-blog::filament.edit-post'))->toBeTrue()
        ->and(view()->exists('plugin-blog-widgets::BlogListing.template'))->toBeTrue()
        ->and(view()->exists('plugin-blog-widgets::BlogPager.template'))->toBeTrue();
});
