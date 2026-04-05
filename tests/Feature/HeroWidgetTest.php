<?php

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function seedHeroWidget(): WidgetType
{
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    return WidgetType::where('handle', 'hero')->firstOrFail();
}

it('creates hero widget type with correct category and config schema after seeding', function () {
    $hero = seedHeroWidget();

    expect($hero->label)->toBe('Hero')
        ->and($hero->category)->toBe(['content'])
        ->and($hero->render_mode)->toBe('server')
        ->and($hero->allowed_page_types)->toBeNull();

    $keys = collect($hero->config_schema)->pluck('key')->all();
    expect($keys)->toBe(['content', 'background_color', 'text_color', 'background_image', 'background_video', 'text_position', 'ctas', 'fullscreen', 'scroll_indicator', 'full_width', 'overlap_nav', 'overlay_opacity', 'nav_link_color', 'nav_hover_color', 'min_height']);
});

it('renders content from config in the hero template', function () {
    $hero = seedHeroWidget();
    $page = Page::factory()->create(['title' => 'Hero Test', 'slug' => 'hero-test', 'status' => 'published']);

    $pw = PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $hero->id,
        'config'         => [
            'content'         => '<h1>Welcome</h1><p>Subheading here</p>',
            'overlay_opacity' => 50,
            'min_height'      => '24rem',
            'text_position'   => 'center-center',
            'ctas'            => [],
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $result = WidgetRenderer::render($pw);

    expect($result['html'])
        ->toContain('<h1>Welcome</h1>')
        ->toContain('<p>Subheading here</p>')
        ->toContain('widget--hero');
});

it('hides CTA section when ctas array is empty', function () {
    $hero = seedHeroWidget();
    $page = Page::factory()->create(['title' => 'Hero No CTA', 'slug' => 'hero-no-cta', 'status' => 'published']);

    $pw = PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $hero->id,
        'config'         => [
            'content'         => '<h1>No buttons</h1>',
            'overlay_opacity' => 50,
            'min_height'      => '24rem',
            'text_position'   => 'center-center',
            'ctas'            => [],
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $result = WidgetRenderer::render($pw);

    expect($result['html'])
        ->not->toContain('hero-ctas')
        ->not->toContain('btn--primary');
});

it('renders CTA buttons with correct style classes', function () {
    $hero = seedHeroWidget();
    $page = Page::factory()->create(['title' => 'Hero CTAs', 'slug' => 'hero-ctas', 'status' => 'published']);

    $pw = PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $hero->id,
        'config'         => [
            'content'         => '<h1>Test</h1>',
            'overlay_opacity' => 50,
            'min_height'      => '24rem',
            'text_position'   => 'center-center',
            'ctas'            => [
                ['text' => 'Get Started', 'url' => '/start', 'style' => 'primary'],
                ['text' => 'Learn More',  'url' => '/about', 'style' => 'secondary'],
                ['text' => 'Details',     'url' => '/info',  'style' => 'text'],
            ],
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $result = WidgetRenderer::render($pw);

    expect($result['html'])
        ->toContain('Get Started')
        ->toContain('btn--primary')
        ->toContain('Learn More')
        ->toContain('btn--secondary')
        ->toContain('Details')
        ->toContain('btn--text')
        ->toContain('href="/start"')
        ->toContain('href="/about"');
});

it('escapes CTA URLs to prevent XSS', function () {
    $hero = seedHeroWidget();
    $page = Page::factory()->create(['title' => 'Hero XSS', 'slug' => 'hero-xss', 'status' => 'published']);

    $pw = PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $hero->id,
        'config'         => [
            'content'         => '<h1>Test</h1>',
            'overlay_opacity' => 50,
            'min_height'      => '24rem',
            'text_position'   => 'center-center',
            'ctas'            => [
                ['text' => 'Click', 'url' => '"><script>alert(1)</script>', 'style' => 'primary'],
            ],
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $result = WidgetRenderer::render($pw);

    expect($result['html'])
        ->not->toContain('<script>alert(1)</script>')
        ->toContain('Click');
});

it('adds fullscreen class when fullscreen is enabled', function () {
    $hero = seedHeroWidget();
    $page = Page::factory()->create(['title' => 'Fullscreen Hero', 'slug' => 'fullscreen-hero', 'status' => 'published']);

    $pw = PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $hero->id,
        'config'         => [
            'content'         => '<h1>Big Hero</h1>',
            'overlay_opacity' => 50,
            'text_position'   => 'center-center',
            'ctas'            => [],
            'fullscreen'      => true,
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $result = WidgetRenderer::render($pw);

    expect($result['html'])->toContain('hero--fullscreen');
});

it('uses height class when fullscreen is off', function () {
    $hero = seedHeroWidget();
    $page = Page::factory()->create(['title' => 'Standard Hero', 'slug' => 'standard-hero', 'status' => 'published']);

    $pw = PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $hero->id,
        'config'         => [
            'content'         => '<h1>Normal</h1>',
            'overlay_opacity' => 50,
            'min_height'      => '32rem',
            'text_position'   => 'center-center',
            'ctas'            => [],
            'fullscreen'      => false,
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $result = WidgetRenderer::render($pw);

    expect($result['html'])
        ->toContain('hero--height-32')
        ->not->toContain('hero--fullscreen');
});

it('shows scroll indicator when enabled', function () {
    $hero = seedHeroWidget();
    $page = Page::factory()->create(['title' => 'Scroll Hero', 'slug' => 'scroll-hero', 'status' => 'published']);

    $pw = PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $hero->id,
        'config'         => [
            'content'          => '<h1>Test</h1>',
            'overlay_opacity'  => 50,
            'text_position'    => 'center-center',
            'ctas'             => [],
            'scroll_indicator' => true,
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $result = WidgetRenderer::render($pw);

    expect($result['html'])->toContain('hero-scroll-indicator');
});

it('hides scroll indicator when disabled', function () {
    $hero = seedHeroWidget();
    $page = Page::factory()->create(['title' => 'No Scroll Hero', 'slug' => 'no-scroll-hero', 'status' => 'published']);

    $pw = PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $hero->id,
        'config'         => [
            'content'          => '<h1>Test</h1>',
            'overlay_opacity'  => 50,
            'text_position'    => 'center-center',
            'ctas'             => [],
            'scroll_indicator' => false,
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $result = WidgetRenderer::render($pw);

    expect($result['html'])->not->toContain('hero-scroll-indicator');
});

it('adds overlap-nav class when full bleed is enabled', function () {
    $hero = seedHeroWidget();
    $page = Page::factory()->create(['title' => 'Bleed Hero', 'slug' => 'bleed-hero', 'status' => 'published']);

    $pw = PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $hero->id,
        'config'         => [
            'content'         => '<h1>Test</h1>',
            'overlay_opacity' => 50,
            'text_position'   => 'center-center',
            'ctas'            => [],
            'overlap_nav'     => true,
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $result = WidgetRenderer::render($pw);

    expect($result['html'])->toContain('hero--overlap-nav');
});
