<?php

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\User;
use App\Models\WidgetType;
use App\Services\ChromeRenderer;
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

it('seeds site_header and site_footer widget types', function () {
    $this->seed(\Database\Seeders\WidgetTypeSeeder::class);

    expect(WidgetType::where('handle', 'site_header')->exists())->toBeTrue();
    expect(WidgetType::where('handle', 'site_footer')->exists())->toBeTrue();
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

    PageWidget::create([
        'page_id'        => $page->id,
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

    PageWidget::create([
        'page_id'        => $headerPage->id,
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

    PageWidget::create([
        'page_id'        => $footerPage->id,
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

    $user = User::factory()->create();
    $user->givePermissionTo('view_any_page');

    $response = $this->actingAs($user)
        ->get('/admin/site-theme-page');

    $response->assertDontSee("tab = 'header'", false);
    $response->assertDontSee("tab = 'footer'", false);
});

it('shows Header and Footer tabs to users with edit_site_chrome permission', function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $user = User::factory()->create();
    $user->givePermissionTo(['view_any_page', 'edit_site_chrome']);

    $response = $this->actingAs($user)
        ->get('/admin/site-theme-page')
        ->assertOk();

    $response->assertSee("tab = 'header'", false);
    $response->assertSee("tab = 'footer'", false);
});
