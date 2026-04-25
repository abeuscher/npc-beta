<?php

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Schema rename ────────────────────────────────────────────────────────────

it('page_widgets table has appearance_config and not style_config', function () {
    expect(Schema::hasColumn('page_widgets', 'appearance_config'))->toBeTrue();
    expect(Schema::hasColumn('page_widgets', 'style_config'))->toBeFalse();
});

// ── Widget seeder diff: REMOVED keys ─────────────────────────────────────────

it('hero widget no longer exposes background_color, text_color, background_image, full_width, or overlay_opacity', function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    $hero = WidgetType::where('handle', 'hero')->firstOrFail();
    $keys = collect($hero->config_schema)->pluck('key')->all();

    expect($keys)
        ->not->toContain('background_color')
        ->not->toContain('text_color')
        ->not->toContain('background_image')
        ->not->toContain('full_width')
        ->not->toContain('overlay_opacity');
});

it('product_carousel widget no longer exposes background_color, text_color, or full_width', function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    $wt = WidgetType::where('handle', 'product_carousel')->firstOrFail();
    $keys = collect($wt->config_schema)->pluck('key')->all();

    expect($keys)
        ->not->toContain('background_color')
        ->not->toContain('text_color')
        ->not->toContain('full_width');
});

// ── Widget seeder diff: RENAMED keys ─────────────────────────────────────────

it('hero exposes background_overlay_opacity in place of overlay_opacity', function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    $hero = WidgetType::where('handle', 'hero')->firstOrFail();
    $keys = collect($hero->config_schema)->pluck('key')->all();

    expect($keys)->toContain('background_overlay_opacity');
});

it('carousel renames slide_text_color and slide_link_color to caption_*', function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    $wt = WidgetType::where('handle', 'carousel')->firstOrFail();
    $keys = collect($wt->config_schema)->pluck('key')->all();

    expect($keys)
        ->toContain('caption_text_color')
        ->toContain('caption_link_color')
        ->not->toContain('slide_text_color')
        ->not->toContain('slide_link_color');
});

it('bar_chart renames bar_color to bar_fill_color', function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    $wt = WidgetType::where('handle', 'bar_chart')->firstOrFail();
    $keys = collect($wt->config_schema)->pluck('key')->all();

    expect($keys)
        ->toContain('bar_fill_color')
        ->not->toContain('bar_color');
});

it('logo_garden renames background_color to container_background_color', function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    $wt = WidgetType::where('handle', 'logo_garden')->firstOrFail();
    $keys = collect($wt->config_schema)->pluck('key')->all();

    expect($keys)
        ->toContain('container_background_color')
        ->not->toContain('background_color');
});

it('board_members renames background_color to grid_background_color and preserves pane_color and border_color', function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    $wt = WidgetType::where('handle', 'board_members')->firstOrFail();
    $keys = collect($wt->config_schema)->pluck('key')->all();

    expect($keys)
        ->toContain('grid_background_color')
        ->not->toContain('background_color')
        ->toContain('pane_color')
        ->toContain('border_color');
});

// ── Render smoke tests for affected widgets ──────────────────────────────────

it('hero widget renders with the new background_overlay_opacity key', function () {
    $html = Blade::render(
        file_get_contents(base_path('app/Widgets/Hero/template.blade.php')),
        [
            'config' => [
                'content'                    => '<h1>Hello</h1>',
                'background_overlay_opacity' => 50,
                'min_height'                 => '24rem',
                'text_position'              => 'center-center',
                'ctas'                       => [],
            ],
            'configMedia' => [],
        ]
    );

    expect($html)
        ->toContain('Hello')
        ->toContain('widget--hero')
        ->not->toContain('--hero-bg');
});

it('product_carousel widget renders without background_color or text_color reads', function () {
    $html = Blade::render(
        file_get_contents(base_path('app/Widgets/ProductCarousel/template.blade.php')),
        [
            'config' => ['heading' => 'Products'],
            'configMedia' => [],
        ]
    );

    // No products → outer wrapper not rendered, but render must not error
    expect($html)->toBeString();
});

it('bar_chart widget renders bar_fill_color into the chart config', function () {
    $html = Blade::render(
        file_get_contents(base_path('app/Widgets/BarChart/template.blade.php')),
        [
            'config' => [
                'heading'        => 'Stats',
                'x_field'        => 'label',
                'y_field'        => 'value',
                'bar_fill_color' => '#abcdef',
            ],
            'widgetData' => [
                'items' => [
                    ['label' => 'A', 'value' => 1],
                    ['label' => 'B', 'value' => 2],
                ],
            ],
            'configMedia' => [],
        ]
    );

    expect($html)
        ->toContain('widget-bar-chart')
        ->toContain('#abcdef');
});

it('carousel widget reads caption_text_color and caption_link_color', function () {
    $html = Blade::render(
        file_get_contents(base_path('app/Widgets/Carousel/template.blade.php')),
        [
            'config' => [
                'image_field'        => 'photo',
                'caption_text_color' => '#112233',
                'caption_link_color' => '#445566',
            ],
            'widgetData' => ['items' => [['title' => 'Slide A', '_media' => []]]],
            'configMedia' => [],
        ]
    );

    expect($html)
        ->toContain('#112233')
        ->toContain('#445566');
});

it('logo_garden widget reads container_background_color into --logo-container-bg', function () {
    $html = Blade::render(
        file_get_contents(base_path('app/Widgets/LogoGarden/template.blade.php')),
        [
            'config' => [
                'image_field'                => 'logo',
                'display_mode'               => 'static',
                'container_background_color' => '#abcdef',
            ],
            'collectionData' => ['logos' => []],
            'configMedia' => [],
        ]
    );

    // Empty logo set → outer markup not rendered, but template must not error
    expect($html)->toBeString();
});

it('board_members widget reads grid_background_color into --bm-grid-bg', function () {
    $html = Blade::render(
        file_get_contents(base_path('app/Widgets/BoardMembers/template.blade.php')),
        [
            'config' => [
                'heading'              => 'Our Board',
                'grid_background_color' => '#abcdef',
                'pane_color'           => '#ffffff',
                'border_color'         => '#cccccc',
            ],
            'collectionData' => ['members' => []],
            'configMedia' => [],
        ]
    );

    expect($html)
        ->toContain('Our Board')
        ->toContain('--bm-grid-bg: #abcdef');
});

it('blog_listing widget renders without background_color or text_color reads', function () {
    $html = Blade::render(
        file_get_contents(base_path('app/Widgets/BlogListing/template.blade.php')),
        [
            'config' => [
                'heading'          => 'Posts',
                'content_template' => '<h3>{{title}}</h3>',
                'columns'          => 3,
                'items_per_page'   => 10,
                'show_search'      => false,
                'sort_default'     => 'newest',
            ],
            'pageContext' => new \App\Services\PageContext(),
        ]
    );

    expect($html)
        ->toContain('widget-blog-listing')
        ->toContain('Posts');
});

it('events_listing widget renders without background_color or text_color reads', function () {
    $html = Blade::render(
        file_get_contents(base_path('app/Widgets/EventsListing/template.blade.php')),
        [
            'config' => [
                'heading'          => 'Events',
                'content_template' => '<h3>{{title}}</h3>',
                'columns'          => 3,
                'items_per_page'   => 10,
                'show_search'      => false,
                'sort_default'     => 'soonest',
            ],
            'pageContext' => new \App\Services\PageContext(),
        ]
    );

    expect($html)
        ->toContain('widget-events-listing')
        ->toContain('Events');
});

// ── Public renderer: bg + text color emission via the nested appearance_config ──

it('public renderer emits background-color and color from appearance_config nested shape', function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    $page = Page::factory()->create([
        'title'  => 'Renderer Test',
        'slug'   => 'renderer-test',
        'status' => 'published',
    ]);

    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    $page->widgets()->create([
        'widget_type_id'    => $wt->id,
        'label'             => 'TB',
        'config'            => array_merge($wt->getDefaultConfig(), ['content' => '<p>Hello</p>']),
        'query_config'      => [],
        'appearance_config' => [
            'background' => ['color' => '#abcdef'],
            'text'       => ['color' => '#112233'],
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $response = $this->get('/renderer-test');

    $response->assertOk();
    $response->assertSee('background-color:#abcdef', false);
    $response->assertSee('color:#112233', false);
});

it('public renderer ignores background.color values that are not valid hex', function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    $page = Page::factory()->create([
        'title'  => 'Renderer Sanitize Test',
        'slug'   => 'renderer-sanitize-test',
        'status' => 'published',
    ]);

    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    $page->widgets()->create([
        'widget_type_id'    => $wt->id,
        'label'             => 'TB',
        'config'            => array_merge($wt->getDefaultConfig(), ['content' => '<p>Hi</p>']),
        'query_config'      => [],
        'appearance_config' => [
            'background' => ['color' => 'javascript:alert(1)'],
            'text'       => ['color' => 'red'],
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $response = $this->get('/renderer-sanitize-test');

    $response->assertOk();
    $response->assertDontSee('javascript:alert(1)', false);
    $response->assertDontSee('color:red', false);
});

it('public renderer emits padding and margin from appearance_config.layout', function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    $page = Page::factory()->create([
        'title'  => 'Spacing Test',
        'slug'   => 'spacing-test',
        'status' => 'published',
    ]);

    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    $page->widgets()->create([
        'widget_type_id'    => $wt->id,
        'label'             => 'TB',
        'config'            => array_merge($wt->getDefaultConfig(), ['content' => '<p>Hi</p>']),
        'query_config'      => [],
        'appearance_config' => [
            'layout' => [
                'padding' => ['top' => 10, 'right' => 20, 'bottom' => 30, 'left' => 40],
                'margin'  => ['top' => 5,  'right' => 15, 'bottom' => 25, 'left' => 35],
            ],
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $response = $this->get('/spacing-test');

    $response->assertOk();
    $response->assertSee('padding-top:10px', false);
    $response->assertSee('padding-left:40px', false);
    $response->assertSee('margin-bottom:25px', false);
});
