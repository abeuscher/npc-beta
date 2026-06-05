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

it('seeds a columns footer navigation as the default footer', function () {
    User::factory()->create();
    $this->seed(\Database\Seeders\SystemPageSeeder::class);

    $footer = Page::where('slug', '_footer')->where('type', 'system')->first();

    $navWidget = $footer->widgets()->whereNull('layout_id')
        ->whereHas('widgetType', fn ($q) => $q->where('handle', 'nav'))
        ->first();

    expect($navWidget)->not->toBeNull();
    expect($navWidget->config['orientation'])->toBe('columns');

    $footerMenu = NavigationMenu::where('handle', 'footer')->first();
    expect($footerMenu)->not->toBeNull();
    expect((string) $navWidget->config['navigation_menu_id'])->toBe((string) $footerMenu->id);

    // Heading columns each carry child links (the kitchen-sink footer shape).
    $headings = $footerMenu->items()->whereNull('parent_id')->get();
    expect($headings->count())->toBeGreaterThan(0);
    expect($footerMenu->items()->whereNotNull('parent_id')->count())->toBeGreaterThan(0);
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

it('hides Header and Footer sub-nav entries from users without edit_site_chrome permission', function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\WidgetTypeSeeder::class);
    $this->seed(\Database\Seeders\RecordDetailViewSeeder::class);
    $pt = Template::factory()->create(['type' => 'page', 'is_default' => true]);

    $user = User::factory()->create();
    $user->givePermissionTo('view_any_page');
    $this->actingAs($user);

    $page = new \App\Filament\Resources\TemplateResource\Pages\EditPageTemplate();
    $page->record = $pt;

    $labels = array_map(
        fn (\Filament\Navigation\NavigationItem $i) => $i->getLabel(),
        $page->getSubNavigation(),
    );

    expect($labels)->not->toContain('Header')
        ->and($labels)->not->toContain('Footer');
});

it('shows Header and Footer sub-nav entries to users with edit_site_chrome permission', function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\WidgetTypeSeeder::class);
    $this->seed(\Database\Seeders\RecordDetailViewSeeder::class);
    $pt = Template::factory()->create(['type' => 'page', 'is_default' => true]);

    $user = User::factory()->create();
    $user->givePermissionTo(['view_any_page', 'edit_site_chrome']);
    $this->actingAs($user);

    $page = new \App\Filament\Resources\TemplateResource\Pages\EditPageTemplate();
    $page->record = $pt;

    $labels = array_map(
        fn (\Filament\Navigation\NavigationItem $i) => $i->getLabel(),
        $page->getSubNavigation(),
    );

    expect($labels)->toContain('Header')
        ->and($labels)->toContain('Footer');
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
    // Parity with the page-body renderer: the column track is the --layout-cols
    // custom property (not a literal grid-template-columns), so the stylesheet's
    // container-query collapse is not defeated by an inline declaration.
    expect($result['html'])->toContain('--layout-cols:auto 1fr');
    expect($result['html'])->not->toContain('grid-template-columns:');
    expect($result['html'])->toContain('data-collapse-mobile="true"');
    expect($result['html'])->toContain('layout-column');
    expect($result['html'])->toContain('Layout child content');
});

it('contains chrome layout content in a site-container by default (parity full-width default)', function () {
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

    // Parity default (resolveFullWidthForLayout): a layout with no explicit
    // full-width keys is full-bleed background with contained content — the grid
    // sits inside an inner .site-container and the .page-layout itself is not
    // outer-wrapped. Mirrors how a page body renders the same bare layout.
    expect($result)->not->toBeNull();
    expect($result['html'])->toContain('site-container');
    expect($result['html'])->toMatch('/<div class="page-layout"[^>]*><div class="site-container"><div class="layout-grid"/');
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
            'background_full_width' => true,
            'content_full_width'    => true,
        ],
        'sort_order'    => 0,
    ]);

    $result = ChromeRenderer::render('_header');

    expect($result)->not->toBeNull();
    expect($result['html'])->not->toContain('site-container');
    expect($result['html'])->toContain('page-layout');
});

it('composes per-layout appearance from nested appearance_config in chrome (parity with page body)', function () {
    // The page builder writes layout appearance to appearance_config.layout
    // (nested), which composeForLayout reads — NOT the long-removed flat
    // layout_config keys (background_color / padding_top) the chrome renderer
    // used to read. This is the s340 chrome-parity fix: chrome routes through
    // AppearanceStyleComposer::composeForLayout exactly like the page body, so
    // per-layout background + padding reach the rendered chrome instead of
    // silently vanishing.
    $page = Page::factory()->create([
        'slug'         => '_header',
        'type'         => 'system',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $page->layouts()->create([
        'display'           => 'grid',
        'columns'           => 1,
        'layout_config'     => ['grid_template_columns' => '1fr'],
        'appearance_config' => [
            'background' => ['color' => '#123456'],
            'layout'     => [
                'padding' => ['top' => '15', 'right' => '24', 'bottom' => '0', 'left' => '0'],
                'margin'  => ['top' => '0', 'right' => '0', 'bottom' => '7', 'left' => '0'],
            ],
        ],
        'sort_order'        => 0,
    ]);

    $result = ChromeRenderer::render('_header');

    expect($result)->not->toBeNull();
    expect($result['html'])->toContain('background-color:#123456');
    // Horizontal padding stays a literal declaration ...
    expect($result['html'])->toContain('padding-right:24px');
    // ... vertical (top/bottom) is emitted as --np-* custom properties so the
    // host rule scales it at narrow widths, in lockstep with the page body.
    expect($result['html'])->toContain('--np-pad-top:15px');
    expect($result['html'])->toContain('--np-mar-bottom:7px');
    // The flat legacy keys are no longer the source of truth.
    expect($result['html'])->not->toContain('grid-template-columns:');
});

it('reaches HTML with both data-collapse-mobile and nested appearance padding (s340 drift guard)', function () {
    // Standing guard against the chrome renderer drifting back into a stale
    // duplicate of the page-body path. A single chrome column layout carrying a
    // collapse_mobile setting AND nested appearance_config.layout.padding must
    // render with the collapse attribute, the --layout-cols track, and the
    // padding all present — the exact silent-drift class the s340 parity fix
    // closed.
    $page = Page::factory()->create([
        'slug'         => '_footer',
        'type'         => 'system',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $page->layouts()->create([
        'display'           => 'grid',
        'columns'           => 2,
        'layout_config'     => [
            'grid_template_columns' => '1fr 1fr',
            'collapse_mobile'       => true,
        ],
        'appearance_config' => [
            'layout' => ['padding' => ['top' => '0', 'right' => '40', 'bottom' => '0', 'left' => '40']],
        ],
        'sort_order'        => 0,
    ]);

    $result = ChromeRenderer::render('_footer');

    expect($result)->not->toBeNull();
    expect($result['html'])
        ->toContain('data-collapse-mobile="true"')
        ->toContain('--layout-cols:1fr 1fr')
        ->toContain('padding-left:40px')
        ->toContain('padding-right:40px');
});

it('emits data-collapse-mobile="false" on a chrome layout that opts out', function () {
    $page = Page::factory()->create([
        'slug'         => '_footer',
        'type'         => 'system',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $page->layouts()->create([
        'display'       => 'grid',
        'columns'       => 2,
        'layout_config' => [
            'grid_template_columns' => '1fr 1fr',
            'collapse_mobile'       => false,
        ],
        'sort_order'    => 0,
    ]);

    $result = ChromeRenderer::render('_footer');

    expect($result)->not->toBeNull();
    expect($result['html'])->toContain('data-collapse-mobile="false"');
});

it('emits the universal border on a chrome widget in parity with the composer (session 323)', function () {
    $page = Page::factory()->create([
        'slug'         => '_header',
        'type'         => 'system',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $widgetType = WidgetType::create([
        'handle'        => 'test_chrome_border',
        'label'         => 'Test Chrome Border',
        'render_mode'   => 'server',
        'collections'   => [],
        'config_schema' => [],
        'template'      => '<div class="test-chrome">Bordered</div>',
    ]);

    $page->widgets()->create([
        'widget_type_id'    => $widgetType->id,
        'label'             => 'Test',
        'config'            => [],
        'appearance_config' => ['layout' => ['border' => [
            'top' => true, 'right' => false, 'bottom' => true, 'left' => false,
            'width' => 2, 'color' => '#abcdef', 'radius' => 5,
        ]]],
        'sort_order'        => 1,
        'is_active'         => true,
    ]);

    $result = ChromeRenderer::render('_header');

    // Parity: chrome delegates to the same emitter the composer uses, so the
    // chrome HTML carries exactly what composeBorderProps emits for this config.
    $expected = \App\Services\AppearanceStyleComposer::composeBorderProps([
        'top' => true, 'right' => false, 'bottom' => true, 'left' => false,
        'width' => 2, 'color' => '#abcdef', 'radius' => 5,
    ]);

    expect($result)->not->toBeNull();
    foreach ($expected as $decl) {
        expect($result['html'])->toContain($decl);
    }
    expect($result['html'])->not->toContain('border-right');
    expect($result['html'])->not->toContain('border-left');
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
    expect($result['html'])->not->toContain('default-logo.svg');
});

it('logo widget renders the placeholder image when no logo and no text are configured', function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    $page = Page::factory()->create(['status' => 'published']);
    $logoType = WidgetType::where('handle', 'logo')->firstOrFail();

    $pw = $page->widgets()->create([
        'widget_type_id' => $logoType->id,
        'label'          => 'Logo',
        'config'         => [],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $result = WidgetRenderer::render($pw->fresh('widgetType'));

    expect($result['html'])
        ->toContain('default-logo.svg')
        ->toContain('widget-logo__img--placeholder');
});

it('logo widget suppresses the placeholder when only text is configured', function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    $page = Page::factory()->create(['status' => 'published']);
    $logoType = WidgetType::where('handle', 'logo')->firstOrFail();

    $pw = $page->widgets()->create([
        'widget_type_id' => $logoType->id,
        'label'          => 'Logo',
        'config'         => ['text' => 'Wordmark Only'],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $result = WidgetRenderer::render($pw->fresh('widgetType'));

    expect($result['html'])
        ->toContain('Wordmark Only')
        ->not->toContain('default-logo.svg');
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
