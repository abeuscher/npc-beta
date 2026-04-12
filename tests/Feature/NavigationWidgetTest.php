<?php

use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function seedNavWidget(): WidgetType
{
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    return WidgetType::where('handle', 'nav')->firstOrFail();
}

function createNavMenu(string $label = 'Primary', string $handle = 'primary'): NavigationMenu
{
    return NavigationMenu::create(['label' => $label, 'handle' => $handle]);
}

function createNavItem(NavigationMenu $menu, array $attrs = []): NavigationItem
{
    return NavigationItem::create(array_merge([
        'navigation_menu_id' => $menu->id,
        'label'              => 'Link',
        'url'                => '/test',
        'sort_order'         => 0,
        'target'             => '_self',
        'is_visible'         => true,
    ], $attrs));
}

function renderNavWidget(WidgetType $navType, array $config): string
{
    $page = Page::factory()->create(['title' => 'Nav Test', 'slug' => 'nav-test-' . uniqid(), 'status' => 'published']);

    $pw = PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $navType->id,
        'config'         => $config,
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $result = WidgetRenderer::render($pw);

    return $result['html'] ?? '';
}

it('renders menu items from a selected NavigationMenu', function () {
    $navType = seedNavWidget();
    $menu    = createNavMenu();
    createNavItem($menu, ['label' => 'Home', 'url' => '/']);
    createNavItem($menu, ['label' => 'About', 'url' => '/about', 'sort_order' => 1]);

    $html = renderNavWidget($navType, ['navigation_menu_id' => $menu->id]);

    expect($html)
        ->toContain('Home')
        ->toContain('About')
        ->toContain('role="menubar"')
        ->toContain('role="menuitem"')
        ->toContain('<nav');
});

it('renders 3-level nesting correctly', function () {
    $navType = seedNavWidget();
    $menu    = createNavMenu();

    $parent = createNavItem($menu, ['label' => 'Services', 'url' => '/services']);
    $child  = createNavItem($menu, ['label' => 'Consulting', 'url' => '/consulting', 'parent_id' => $parent->id]);
    createNavItem($menu, ['label' => 'Strategy', 'url' => '/strategy', 'parent_id' => $child->id]);

    $html = renderNavWidget($navType, ['navigation_menu_id' => $menu->id]);

    expect($html)
        ->toContain('Services')
        ->toContain('Consulting')
        ->toContain('Strategy')
        ->toContain('widget-nav__dropdown')
        ->toContain('widget-nav__subdrop')
        ->toContain('aria-haspopup="true"');
});

it('applies active class to the current page nav item', function () {
    $navType = seedNavWidget();
    $menu    = createNavMenu();
    createNavItem($menu, ['label' => 'Home', 'url' => '/']);

    // Simulate request to '/'
    $this->get('/');

    $html = renderNavWidget($navType, ['navigation_menu_id' => $menu->id]);

    expect($html)->toContain('is-active');
});

it('renders branding slot with text', function () {
    $navType = seedNavWidget();
    $menu    = createNavMenu();
    createNavItem($menu, ['label' => 'Home', 'url' => '/']);

    $html = renderNavWidget($navType, [
        'navigation_menu_id' => $menu->id,
        'branding_type'      => 'text',
        'branding_text'      => 'My Org',
    ]);

    expect($html)
        ->toContain('My Org')
        ->toContain('widget-nav__brand-text');
});

it('hides branding slot when type is none', function () {
    $navType = seedNavWidget();
    $menu    = createNavMenu();
    createNavItem($menu, ['label' => 'Home', 'url' => '/']);

    $html = renderNavWidget($navType, [
        'navigation_menu_id' => $menu->id,
        'branding_type'      => 'none',
    ]);

    expect($html)->not->toContain('widget-nav__brand');
});

it('applies dropdown alignment class', function () {
    $navType = seedNavWidget();
    $menu    = createNavMenu();

    $parent = createNavItem($menu, ['label' => 'Services', 'url' => '/services']);
    createNavItem($menu, ['label' => 'Child', 'url' => '/child', 'parent_id' => $parent->id]);

    $html = renderNavWidget($navType, [
        'navigation_menu_id' => $menu->id,
        'drop_align'         => 'center',
    ]);

    expect($html)->toContain('widget-nav__dropdown--center');
});

it('includes mobile hamburger button with correct aria attributes', function () {
    $navType = seedNavWidget();
    $menu    = createNavMenu();
    createNavItem($menu, ['label' => 'Home', 'url' => '/']);

    $html = renderNavWidget($navType, ['navigation_menu_id' => $menu->id]);

    expect($html)
        ->toContain('widget-nav__hamburger')
        ->toContain('aria-label="Toggle navigation menu"')
        ->toContain('aria-controls=');
});

it('renders gracefully when no NavigationMenu is selected', function () {
    $navType = seedNavWidget();

    $html = renderNavWidget($navType, []);

    expect($html)->toBe('');
});

it('renders gracefully when selected menu has no items', function () {
    $navType = seedNavWidget();
    $menu    = createNavMenu();

    $html = renderNavWidget($navType, ['navigation_menu_id' => $menu->id]);

    expect($html)->toBe('');
});

it('escapes template tokens to prevent XSS', function () {
    $navType = seedNavWidget();
    $menu    = createNavMenu();
    createNavItem($menu, ['label' => '<script>alert(1)</script>', 'url' => '/"onmouseover="alert(1)"']);

    $html = renderNavWidget($navType, ['navigation_menu_id' => $menu->id]);

    expect($html)
        ->not->toContain('<script>alert(1)</script>')
        ->toContain('&lt;script&gt;')
        ->not->toContain('"onmouseover="alert(1)"');
});

it('excludes hidden nav items', function () {
    $navType = seedNavWidget();
    $menu    = createNavMenu();
    createNavItem($menu, ['label' => 'Visible', 'url' => '/visible', 'is_visible' => true]);
    createNavItem($menu, ['label' => 'Hidden', 'url' => '/hidden', 'is_visible' => false]);

    $html = renderNavWidget($navType, ['navigation_menu_id' => $menu->id]);

    expect($html)
        ->toContain('Visible')
        ->not->toContain('Hidden');
});

it('resolves page URLs from page_id', function () {
    $navType = seedNavWidget();
    $menu    = createNavMenu();
    $page    = Page::factory()->create(['title' => 'About Us', 'slug' => 'about-us', 'status' => 'published']);
    createNavItem($menu, ['label' => 'About', 'page_id' => $page->id, 'url' => null]);

    $html = renderNavWidget($navType, ['navigation_menu_id' => $menu->id]);

    expect($html)->toContain('/about-us');
});

it('applies dropdown animation class', function () {
    $navType = seedNavWidget();
    $menu    = createNavMenu();
    createNavItem($menu, ['label' => 'Home', 'url' => '/']);

    $html = renderNavWidget($navType, [
        'navigation_menu_id' => $menu->id,
        'drop_animation'     => 'slide',
    ]);

    expect($html)->toContain('widget-nav--drop-slide');
});

it('renders mobile menu with nested sub-menus', function () {
    $navType = seedNavWidget();
    $menu    = createNavMenu();

    $parent = createNavItem($menu, ['label' => 'Products', 'url' => '/products']);
    createNavItem($menu, ['label' => 'Widget A', 'url' => '/widget-a', 'parent_id' => $parent->id]);

    $html = renderNavWidget($navType, ['navigation_menu_id' => $menu->id]);

    expect($html)
        ->toContain('widget-nav__mobile')
        ->toContain('widget-nav__mobile-sub')
        ->toContain('Widget A');
});
