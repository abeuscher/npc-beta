<?php

use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Navigation Menus ────────────────────────────────────────────────────────

it('creates a navigation menu with valid data', function () {
    $menu = NavigationMenu::create([
        'label'  => 'Primary',
        'handle' => 'primary',
    ]);

    expect($menu->exists)->toBeTrue()
        ->and($menu->label)->toBe('Primary')
        ->and($menu->handle)->toBe('primary');
});

it('enforces unique handle on navigation menus', function () {
    NavigationMenu::create(['label' => 'Primary', 'handle' => 'primary']);

    expect(fn () => NavigationMenu::create(['label' => 'Also Primary', 'handle' => 'primary']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('menu has many items', function () {
    $menu = NavigationMenu::create(['label' => 'Footer', 'handle' => 'footer']);

    NavigationItem::create(['navigation_menu_id' => $menu->id, 'label' => 'Home', 'url' => '/']);
    NavigationItem::create(['navigation_menu_id' => $menu->id, 'label' => 'About', 'url' => '/about']);

    expect($menu->items)->toHaveCount(2);
});

// ── Navigation Items ────────────────────────────────────────────────────────

it('creates a navigation item with a URL', function () {
    $menu = NavigationMenu::create(['label' => 'Main', 'handle' => 'main']);
    $item = NavigationItem::create([
        'navigation_menu_id' => $menu->id,
        'label'              => 'Home',
        'url'                => '/',
    ]);

    expect($item->exists)->toBeTrue()
        ->and($item->label)->toBe('Home')
        ->and($item->url)->toBe('/');
});

it('links a navigation item to a page', function () {
    $menu = NavigationMenu::create(['label' => 'Main', 'handle' => 'main']);
    $page = Page::factory()->create();
    $item = NavigationItem::create([
        'navigation_menu_id' => $menu->id,
        'label'              => 'About',
        'page_id'            => $page->id,
    ]);

    expect($item->page)->toBeInstanceOf(Page::class)
        ->and($item->page->id)->toBe($page->id);
});

it('supports nested items via parent relationship', function () {
    $menu = NavigationMenu::create(['label' => 'Main', 'handle' => 'main']);
    $parent = NavigationItem::create([
        'navigation_menu_id' => $menu->id,
        'label'              => 'Services',
        'url'                => '/services',
    ]);
    $child = NavigationItem::create([
        'navigation_menu_id' => $menu->id,
        'label'              => 'Consulting',
        'url'                => '/services/consulting',
        'parent_id'          => $parent->id,
    ]);

    expect($child->parent->id)->toBe($parent->id)
        ->and($parent->children)->toHaveCount(1)
        ->and($parent->children->first()->id)->toBe($child->id);
});

it('casts is_visible as boolean', function () {
    $menu = NavigationMenu::create(['label' => 'Main', 'handle' => 'main']);
    $item = NavigationItem::create([
        'navigation_menu_id' => $menu->id,
        'label'              => 'Hidden',
        'url'                => '/hidden',
        'is_visible'         => false,
    ]);

    expect($item->is_visible)->toBeFalse()->toBeBool();
});

it('defaults target to _self', function () {
    $menu = NavigationMenu::create(['label' => 'Main', 'handle' => 'main']);
    $item = NavigationItem::create([
        'navigation_menu_id' => $menu->id,
        'label'              => 'Link',
        'url'                => '/link',
    ]);

    expect($item->fresh()->target)->toBe('_self');
});
