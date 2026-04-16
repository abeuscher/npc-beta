<?php

use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\PageLayout;
use App\Models\PageWidget;
use App\Models\Template;
use App\Models\User;
use App\Models\WidgetType;
use App\Services\Media\ChromeRenderer;
use App\Services\WidgetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Seeder tests ────────────────────────────────────────────────────────────

it('creates _header and _footer system pages via seeder', function () {
    User::factory()->create();
    $this->seed(\Database\Seeders\SystemPageSeeder::class);

    expect(Page::where('slug', '_header')->where('type', 'system')->exists())->toBeTrue();
    expect(Page::where('slug', '_footer')->where('type', 'system')->exists())->toBeTrue();
});

it('seeds logo and nav widget types', function () {
    $this->seed(\Database\Seeders\WidgetTypeSeeder::class);

    expect(WidgetType::where('handle', 'logo')->exists())->toBeTrue();
    expect(WidgetType::where('handle', 'nav')->exists())->toBeTrue();
});

it('does not seed legacy site_header or site_footer widget types', function () {
    $this->seed(\Database\Seeders\WidgetTypeSeeder::class);

    expect(WidgetType::where('handle', 'site_header')->exists())->toBeFalse();
    expect(WidgetType::where('handle', 'site_footer')->exists())->toBeFalse();
});

it('registers edit_site_chrome permission via seeder', function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    expect(\Spatie\Permission\Models\Permission::where('name', 'edit_site_chrome')->exists())->toBeTrue();
});

// ── Routing guard ───────────────────────────────────────────────────────────

it('returns 404 for _header slug', function () {
    Page::factory()->create([
        'slug'         => '_header',
        'type'         => 'system',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/_header')->assertNotFound();
});

it('returns 404 for _footer slug', function () {
    Page::factory()->create([
        'slug'         => '_footer',
        'type'         => 'system',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/_footer')->assertNotFound();
});

it('returns 404 for any underscore-prefixed slug', function () {
    Page::factory()->create([
        'slug'         => '_secret',
        'type'         => 'system',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/_secret')->assertNotFound();
});

// ── ChromeRenderer ──────────────────────────────────────────────────────────

it('returns null when system page has no widgets', function () {
    Page::factory()->create([
        'slug'         => '_header',
        'type'         => 'system',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    expect(ChromeRenderer::render('_header'))->toBeNull();
});

it('returns null when system page does not exist', function () {
    expect(ChromeRenderer::render('_header'))->toBeNull();
});

it('renders widget HTML when system page has active widgets', function () {
    $page = Page::factory()->create([
        'slug'         => '_header',
        'type'         => 'system',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $widgetType = WidgetType::create([
        'handle'        => 'test_chrome_widget',
        'label'         => 'Test Chrome',
        'render_mode'   => 'server',
        'collections'   => [],
        'config_schema' => [],
        'template'      => '<div class="test-chrome">Hello Chrome</div>',
    ]);

    $page->widgets()->create([
        'widget_type_id' => $widgetType->id,
        'label'          => 'Test',
        'config'         => [],
        'sort_order'     => 1,
        'is_active'      => true,
    ]);

    $result = ChromeRenderer::render('_header');

    expect($result)->not->toBeNull();
    expect($result['html'])->toContain('Hello Chrome');
});

// ── Public layout fallback vs widget-driven ─────────────────────────────────

it('renders fallback header when no widgets on header system page', function () {
    // Create a home page to serve
    Page::factory()->create([
        'slug'         => 'home',
        'title'        => 'Home',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    // Create the _header system page with no widgets
    Page::factory()->create([
        'slug'         => '_header',
        'type'         => 'system',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $response = $this->get('/');
    $response->assertOk();
    // The fallback header partial contains the hamburger button with "Toggle navigation"
    $response->assertSee('Toggle navigation');
});

it('renders widget-driven header when widgets exist', function () {
    Page::factory()->create([
        'slug'         => 'home',
        'title'        => 'Home',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $headerPage = Page::factory()->create([
        'slug'         => '_header',
        'type'         => 'system',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $widgetType = WidgetType::create([
        'handle'        => 'test_header_wt',
        'label'         => 'Test Header WT',
        'render_mode'   => 'server',
        'collections'   => [],
        'config_schema' => [],
        'template'      => '<header class="widget-header">Widget Header Active</header>',
    ]);

    $headerPage->widgets()->create([
        'widget_type_id' => $widgetType->id,
        'label'          => 'Test Header',
        'config'         => [],
        'sort_order'     => 1,
        'is_active'      => true,
    ]);

    $response = $this->get('/');
    $response->assertOk();
    $response->assertSee('Widget Header Active');
});

it('renders widget-driven footer when widgets exist', function () {
    Page::factory()->create([
        'slug'         => 'home',
        'title'        => 'Home',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $footerPage = Page::factory()->create([
        'slug'         => '_footer',
        'type'         => 'system',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $widgetType = WidgetType::create([
        'handle'        => 'test_footer_wt',
        'label'         => 'Test Footer WT',
        'render_mode'   => 'server',
        'collections'   => [],
        'config_schema' => [],
        'template'      => '<footer class="widget-footer">Widget Footer Active</footer>',
    ]);

    $footerPage->widgets()->create([
        'widget_type_id' => $widgetType->id,
        'label'          => 'Test Footer',
        'config'         => [],
        'sort_order'     => 1,
        'is_active'      => true,
    ]);

    $response = $this->get('/');
    $response->assertOk();
    $response->assertSee('Widget Footer Active');
});

// ── Permission gating ───────────────────────────────────────────────────────

it('hides Header and Footer tabs from users without edit_site_chrome permission', function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $pt = Template::factory()->create(['type' => 'page', 'is_default' => true]);

    $user = User::factory()->create();
    $user->givePermissionTo('view_any_page');

    $response = $this->actingAs($user)
        ->get(\App\Filament\Resources\TemplateResource::getUrl('edit-page', ['record' => $pt]));

    $response->assertDontSee("tab = 'header'", false);
    $response->assertDontSee("tab = 'footer'", false);
});

it('shows Header and Footer tabs to users with edit_site_chrome permission', function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $pt = Template::factory()->create(['type' => 'page', 'is_default' => true]);

    $user = User::factory()->create();
    $user->givePermissionTo(['view_any_page', 'edit_site_chrome']);

    $response = $this->actingAs($user)
        ->get(\App\Filament\Resources\TemplateResource::getUrl('edit-page', ['record' => $pt]))
        ->assertOk();

    $response->assertSee("tab = 'header'", false);
    $response->assertSee("tab = 'footer'", false);
});

// ── ChromeRenderer renders layouts ──────────────────────────────────────────

it('renders a column layout on a system page', function () {
    $page = Page::factory()->create([
        'slug'         => '_header',
        'type'         => 'system',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $widgetType = WidgetType::create([
        'handle'        => 'test_chrome_layout_widget',
        'label'         => 'Test Chrome Layout',
        'render_mode'   => 'server',
        'collections'   => [],
        'config_schema' => [],
        'template'      => '<div class="test-layout-widget">Layout child content</div>',
    ]);

    $layout = $page->layouts()->create([
        'label'         => 'Two Col',
        'display'       => 'grid',
        'columns'       => 2,
        'layout_config' => [
            'grid_template_columns' => 'auto 1fr',
            'gap'                   => '1rem',
        ],
        'sort_order'    => 0,
    ]);

    $page->widgets()->create([
        'layout_id'      => $layout->id,
        'column_index'   => 0,
        'widget_type_id' => $widgetType->id,
        'label'          => 'Child',
        'config'         => [],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $result = ChromeRenderer::render('_header');

    expect($result)->not->toBeNull();
    expect($result['html'])->toContain('page-layout');
    expect($result['html'])->toContain('display:grid');
    expect($result['html'])->toContain('grid-template-columns:auto 1fr');
    expect($result['html'])->toContain('layout-column');
    expect($result['html'])->toContain('Layout child content');
});

it('wraps chrome layout in site-container by default', function () {
    $page = Page::factory()->create([
        'slug'         => '_header',
        'type'         => 'system',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $page->layouts()->create([
        'display'       => 'grid',
        'columns'       => 1,
        'layout_config' => ['grid_template_columns' => '1fr'],
        'sort_order'    => 0,
    ]);

    $result = ChromeRenderer::render('_header');

    expect($result)->not->toBeNull();
    expect($result['html'])->toContain('site-container');
    expect($result['html'])->toMatch('/<div class="site-container"><div class="page-layout"/');
});

it('renders chrome layout edge-to-edge when full_width is true', function () {
    $page = Page::factory()->create([
        'slug'         => '_header',
        'type'         => 'system',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $page->layouts()->create([
        'display'       => 'grid',
        'columns'       => 1,
        'layout_config' => [
            'grid_template_columns' => '1fr',
            'full_width'            => true,
        ],
        'sort_order'    => 0,
    ]);

    $result = ChromeRenderer::render('_header');

    expect($result)->not->toBeNull();
    expect($result['html'])->not->toContain('site-container');
    expect($result['html'])->toContain('page-layout');
});

it('emits new layout style fields in chrome', function () {
    $page = Page::factory()->create([
        'slug'         => '_header',
        'type'         => 'system',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $page->layouts()->create([
        'display'       => 'grid',
        'columns'       => 1,
        'layout_config' => [
            'grid_template_columns' => '1fr',
            'background_color'      => '#123456',
            'padding_top'           => '15',
            'margin_bottom'         => '7',
        ],
        'sort_order'    => 0,
    ]);

    $result = ChromeRenderer::render('_header');

    expect($result)->not->toBeNull();
    expect($result['html'])->toContain('background-color:#123456');
    expect($result['html'])->toContain('padding-top:15px');
    expect($result['html'])->toContain('margin-bottom:7px');
});

// ── Logo widget ─────────────────────────────────────────────────────────────

it('logo widget renders text and link', function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    $page = Page::factory()->create(['status' => 'published']);
    $logoType = WidgetType::where('handle', 'logo')->firstOrFail();

    $pw = $page->widgets()->create([
        'widget_type_id' => $logoType->id,
        'label'          => 'Logo',
        'config'         => [
            'text'     => 'Acme Org',
            'link_url' => 'https://example.test/home',
        ],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $result = WidgetRenderer::render($pw->fresh('widgetType'));

    expect($result['html'])->toContain('<a href="https://example.test/home"');
    expect($result['html'])->toContain('widget-logo__text');
    expect($result['html'])->toContain('Acme Org');
});

// ── Nav widget ──────────────────────────────────────────────────────────────

it('nav widget renders links from a NavigationMenu by handle', function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    $menu = NavigationMenu::create(['label' => 'Primary', 'handle' => 'primary']);

    NavigationItem::create([
        'navigation_menu_id' => $menu->id,
        'label'              => 'About',
        'url'                => '/about',
        'sort_order'         => 1,
        'is_visible'         => true,
    ]);

    NavigationItem::create([
        'navigation_menu_id' => $menu->id,
        'label'              => 'Contact',
        'url'                => '/contact',
        'sort_order'         => 2,
        'is_visible'         => true,
    ]);

    NavigationItem::create([
        'navigation_menu_id' => $menu->id,
        'label'              => 'Hidden',
        'url'                => '/hidden',
        'sort_order'         => 3,
        'is_visible'         => false,
    ]);

    $page = Page::factory()->create(['status' => 'published']);
    $navType = WidgetType::where('handle', 'nav')->firstOrFail();

    $pw = $page->widgets()->create([
        'widget_type_id' => $navType->id,
        'label'          => 'Nav',
        'config'         => ['navigation_menu_id' => $menu->id],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $result = WidgetRenderer::render($pw->fresh('widgetType'));

    expect($result['html'])->toContain('href="/about"');
    expect($result['html'])->toContain('About');
    expect($result['html'])->toContain('href="/contact"');
    expect($result['html'])->toContain('Contact');
    expect($result['html'])->not->toContain('Hidden');
});
