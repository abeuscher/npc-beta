<?php

use App\Models\Page;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

function makeTextBlockPage(string $slug, array $config): \App\Models\PageWidget
{
    $page = Page::factory()->create([
        'type'         => 'default',
        'slug'         => $slug,
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ]);

    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    return $page->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => array_merge(['content' => '<p>Body</p>'], $config),
        'sort_order'     => 0,
        'is_active'      => true,
    ]);
}

it('exposes ctas and cta_alignment in the TextBlock schema', function () {
    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    $keys = collect($wt->config_schema)->pluck('key')->all();
    expect($keys)->toContain('ctas', 'cta_alignment');
});

it('renders CTA buttons below TextBlock content with the chosen style classes', function () {
    $pw = makeTextBlockPage('text-block-ctas-styles', [
        'content' => '<p>Body copy.</p>',
        'ctas'    => [
            ['text' => 'Get started', 'url' => '/start',  'style' => 'primary'],
            ['text' => 'Learn more',  'url' => '/learn',  'style' => 'secondary'],
            ['text' => 'On dark',     'url' => '/dark',   'style' => 'secondary-dark'],
            ['text' => 'Details',     'url' => '/info',   'style' => 'text'],
        ],
    ]);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)
        ->toContain('widget-text-block__ctas')
        ->toContain('btn--primary')
        ->toContain('btn--secondary')
        ->toContain('btn--secondary-dark')
        ->toContain('btn--text')
        ->toContain('href="/start"')
        ->toContain('Get started')
        ->toContain('Learn more')
        ->toContain('On dark')
        ->toContain('Details');
});

it('hides the CTA wrapper when ctas is empty', function () {
    $pw = makeTextBlockPage('text-block-no-ctas', ['ctas' => []]);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)
        ->not->toContain('widget-text-block__ctas')
        ->not->toContain('btn--primary');
});

it('respects an explicit cta_alignment value', function () {
    foreach (['left', 'center', 'right'] as $alignment) {
        $pw = makeTextBlockPage('text-block-align-' . $alignment, [
            'ctas'          => [['text' => 'Go', 'url' => '/x', 'style' => 'primary']],
            'cta_alignment' => $alignment,
        ]);

        $html = WidgetRenderer::render($pw)['html'];

        expect($html)->toContain('btn-group--' . $alignment);
    }
});

it('inherits CTA alignment from Quill alignment classes when cta_alignment is inherit', function () {
    $pw = makeTextBlockPage('text-block-inherit-center', [
        'content'       => '<p class="ql-align-center">Centered copy.</p>',
        'ctas'          => [['text' => 'Go', 'url' => '/x', 'style' => 'primary']],
        'cta_alignment' => 'inherit',
    ]);

    $html = WidgetRenderer::render($pw)['html'];
    expect($html)->toContain('btn-group--center');

    $pw2 = makeTextBlockPage('text-block-inherit-right', [
        'content'       => '<p class="ql-align-right">Right copy.</p>',
        'ctas'          => [['text' => 'Go', 'url' => '/x', 'style' => 'primary']],
        'cta_alignment' => 'inherit',
    ]);

    $html2 = WidgetRenderer::render($pw2)['html'];
    expect($html2)->toContain('btn-group--right');

    $pw3 = makeTextBlockPage('text-block-inherit-left', [
        'content'       => '<p>Plain copy.</p>',
        'ctas'          => [['text' => 'Go', 'url' => '/x', 'style' => 'primary']],
        'cta_alignment' => 'inherit',
    ]);

    $html3 = WidgetRenderer::render($pw3)['html'];
    expect($html3)->toContain('btn-group--left');
});

it('escapes CTA URLs to prevent XSS', function () {
    $pw = makeTextBlockPage('text-block-xss', [
        'ctas' => [
            ['text' => 'Click', 'url' => '"><script>alert(1)</script>', 'style' => 'primary'],
        ],
    ]);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)
        ->not->toContain('<script>alert(1)</script>')
        ->toContain('Click');
});
