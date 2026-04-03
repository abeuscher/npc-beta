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
    expect($keys)->toBe(['content', 'background_image', 'text_position', 'ctas', 'overlap_nav', 'overlay_opacity', 'min_height']);
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
        ->not->toContain('btn-primary')
        ->not->toContain('btn-secondary')
        ->not->toContain('btn-text');
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
        ->toContain('btn-primary')
        ->toContain('Learn More')
        ->toContain('btn-secondary')
        ->toContain('Details')
        ->toContain('btn-text')
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
