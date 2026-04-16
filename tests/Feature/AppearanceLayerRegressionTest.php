<?php

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

/**
 * Helper: create a page, widget, and GET the page. Returns the response.
 */
function renderOnPage(string $handle, array $config, array $appearanceConfig = []): \Illuminate\Testing\TestResponse
{
    $slug = 'appearance-test-' . $handle . '-' . uniqid();
    $page = Page::factory()->create([
        'title'  => 'Test ' . $handle,
        'slug'   => $slug,
        'status' => 'published',
    ]);

    $wt = WidgetType::where('handle', $handle)->firstOrFail();

    $page->widgets()->create([
        'widget_type_id'    => $wt->id,
        'label'             => 'Test',
        'config'            => $config,
        'query_config'      => [],
        'appearance_config' => $appearanceConfig,
        'sort_order'        => 0,
        'is_active'         => true,
    ]);

    return test()->get('/' . $slug);
}

// ── Hero ──────────────────────────────���──────────────────────��──────────────

it('hero renders with surviving keys only and no PHP notices', function () {
    $response = renderOnPage('hero', [
        'content'                    => '<h1>Hello</h1>',
        'text_position'              => 'center-center',
        'min_height'                 => '24rem',
        'ctas'                       => [],
        'background_overlay_opacity' => 50,
        'nav_link_color'             => '#ffffff',
        'nav_hover_color'            => '#cccccc',
        'background_video'           => '',
    ]);

    $response->assertOk();
    $response->assertSee('widget--hero', false);
});

it('hero renders appearance_config background and text colors in wrapper', function () {
    $response = renderOnPage('hero', [
        'content'                    => '<h1>Styled</h1>',
        'text_position'              => 'center-center',
        'min_height'                 => '24rem',
        'ctas'                       => [],
        'background_overlay_opacity' => 50,
    ], [
        'background' => ['color' => '#aa1122'],
        'text'       => ['color' => '#33cc55'],
    ]);

    $response->assertOk();
    $response->assertSee('background-color:#aa1122', false);
    $response->assertSee('color:#33cc55', false);
});

// ── Product Carousel ────────────────────────────────────────────────────────

it('product_carousel renders with surviving keys only and no PHP notices', function () {
    $response = renderOnPage('product_carousel', [
        'heading' => 'Products',
    ]);

    $response->assertOk();
});

it('product_carousel renders appearance_config colors in wrapper', function () {
    $response = renderOnPage('product_carousel', [
        'heading' => 'Products',
    ], [
        'background' => ['color' => '#bb2233'],
        'text'       => ['color' => '#44dd66'],
    ]);

    $response->assertOk();
    $response->assertSee('background-color:#bb2233', false);
    $response->assertSee('color:#44dd66', false);
});

// ── Carousel ──────────────────────────────────────────────────────���─────────

it('carousel renders with surviving keys only and no PHP notices', function () {
    $response = renderOnPage('carousel', [
        'image_field'        => 'photo',
        'caption_text_color' => '#112233',
        'caption_link_color' => '#445566',
    ]);

    $response->assertOk();
});

it('carousel renders appearance_config colors in wrapper', function () {
    $response = renderOnPage('carousel', [
        'image_field'        => 'photo',
        'caption_text_color' => '#112233',
        'caption_link_color' => '#445566',
    ], [
        'background' => ['color' => '#cc3344'],
        'text'       => ['color' => '#55ee77'],
    ]);

    $response->assertOk();
    $response->assertSee('background-color:#cc3344', false);
    $response->assertSee('color:#55ee77', false);
});

// ── Bar Chart ───────────────────────────────────────────────────────────────

it('bar_chart renders with surviving keys only and no PHP notices', function () {
    $response = renderOnPage('bar_chart', [
        'heading'        => 'Stats',
        'x_field'        => 'label',
        'y_field'        => 'value',
        'bar_fill_color' => '#abcdef',
    ]);

    $response->assertOk();
});

it('bar_chart renders appearance_config colors in wrapper', function () {
    $response = renderOnPage('bar_chart', [
        'heading'        => 'Stats',
        'x_field'        => 'label',
        'y_field'        => 'value',
        'bar_fill_color' => '#abcdef',
    ], [
        'background' => ['color' => '#dd4455'],
        'text'       => ['color' => '#66ff88'],
    ]);

    $response->assertOk();
    $response->assertSee('background-color:#dd4455', false);
    $response->assertSee('color:#66ff88', false);
});

// ── Logo Garden ────────────────��────────────────────────────────────────────

it('logo_garden renders with surviving keys only and no PHP notices', function () {
    $response = renderOnPage('logo_garden', [
        'collection_handle'          => 'nonexistent',
        'image_field'                => 'logo',
        'display_mode'               => 'static',
        'container_background_color' => '#abcdef',
    ]);

    $response->assertOk();
});

it('logo_garden renders appearance_config colors in wrapper', function () {
    $response = renderOnPage('logo_garden', [
        'collection_handle'          => 'nonexistent',
        'image_field'                => 'logo',
        'display_mode'               => 'static',
        'container_background_color' => '#abcdef',
    ], [
        'background' => ['color' => '#ee5566'],
        'text'       => ['color' => '#77ff99'],
    ]);

    $response->assertOk();
    $response->assertSee('background-color:#ee5566', false);
    $response->assertSee('color:#77ff99', false);
});

// ── Board Members ───────────────────────────────��───────────────────────────

it('board_members renders with surviving keys only and no PHP notices', function () {
    $response = renderOnPage('board_members', [
        'heading'              => 'Our Board',
        'grid_background_color' => '#abcdef',
        'pane_color'           => '#ffffff',
        'border_color'         => '#cccccc',
    ]);

    $response->assertOk();
});

it('board_members renders appearance_config colors in wrapper', function () {
    $response = renderOnPage('board_members', [
        'heading'              => 'Our Board',
        'grid_background_color' => '#abcdef',
        'pane_color'           => '#ffffff',
        'border_color'         => '#cccccc',
    ], [
        'background' => ['color' => '#ff6677'],
        'text'       => ['color' => '#88ffaa'],
    ]);

    $response->assertOk();
    $response->assertSee('background-color:#ff6677', false);
    $response->assertSee('color:#88ffaa', false);
});

// ── Blog Listing ────────────────────────────────────────────────────────────

it('blog_listing renders with surviving keys only and no PHP notices', function () {
    $response = renderOnPage('blog_listing', [
        'heading'          => 'Posts',
        'content_template' => '<h3>{{title}}</h3>',
        'columns'          => 3,
        'items_per_page'   => 10,
        'show_search'      => false,
        'sort_default'     => 'newest',
    ]);

    $response->assertOk();
});

it('blog_listing renders appearance_config colors in wrapper', function () {
    $response = renderOnPage('blog_listing', [
        'heading'          => 'Posts',
        'content_template' => '<h3>{{title}}</h3>',
        'columns'          => 3,
        'items_per_page'   => 10,
        'show_search'      => false,
        'sort_default'     => 'newest',
    ], [
        'background' => ['color' => '#aa7788'],
        'text'       => ['color' => '#99ffbb'],
    ]);

    $response->assertOk();
    $response->assertSee('background-color:#aa7788', false);
    $response->assertSee('color:#99ffbb', false);
});

// ── Events Listing ──────────────────────────────���───────────────────────────

it('events_listing renders with surviving keys only and no PHP notices', function () {
    $response = renderOnPage('events_listing', [
        'heading'          => 'Events',
        'content_template' => '<h3>{{title}}</h3>',
        'columns'          => 3,
        'items_per_page'   => 10,
        'show_search'      => false,
        'sort_default'     => 'soonest',
    ]);

    $response->assertOk();
});

it('events_listing renders appearance_config colors in wrapper', function () {
    $response = renderOnPage('events_listing', [
        'heading'          => 'Events',
        'content_template' => '<h3>{{title}}</h3>',
        'columns'          => 3,
        'items_per_page'   => 10,
        'show_search'      => false,
        'sort_default'     => 'soonest',
    ], [
        'background' => ['color' => '#bb8899'],
        'text'       => ['color' => '#aaffcc'],
    ]);

    $response->assertOk();
    $response->assertSee('background-color:#bb8899', false);
    $response->assertSee('color:#aaffcc', false);
});
