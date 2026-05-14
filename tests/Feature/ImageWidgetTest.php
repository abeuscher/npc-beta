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

function makeImagePage(string $slug, array $config): \App\Models\PageWidget
{
    $page = Page::factory()->create([
        'type'         => 'default',
        'slug'         => $slug,
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ]);

    $wt = WidgetType::where('handle', 'image')->firstOrFail();

    return $page->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => array_merge(['image' => 'https://example.com/placeholder.jpg'], $config),
        'sort_order'     => 0,
        'is_active'      => true,
    ]);
}

it('registers aspect_ratio and max_width in the image widget schema', function () {
    $image = WidgetType::where('handle', 'image')->firstOrFail();

    $keys = collect($image->config_schema)->pluck('key')->all();
    expect($keys)->toContain('aspect_ratio', 'max_width');
});

it('renders no aspect-ratio class when aspect_ratio is auto', function () {
    $pw = makeImagePage('image-auto', ['aspect_ratio' => 'auto']);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)
        ->toContain('widget-image')
        ->not->toContain('widget-image--ratio-');
});

it('renders the matching aspect-ratio class for each ratio option', function () {
    $cases = [
        '1:1'  => 'widget-image--ratio-1-1',
        '4:3'  => 'widget-image--ratio-4-3',
        '3:2'  => 'widget-image--ratio-3-2',
        '16:9' => 'widget-image--ratio-16-9',
        '4:5'  => 'widget-image--ratio-4-5',
        '3:4'  => 'widget-image--ratio-3-4',
    ];

    foreach ($cases as $ratio => $expectedClass) {
        $pw = makeImagePage('image-ratio-' . str_replace(':', '-', $ratio), ['aspect_ratio' => $ratio]);
        $html = WidgetRenderer::render($pw)['html'];

        expect($html)->toContain($expectedClass);
    }
});

it('ignores unknown aspect_ratio values', function () {
    $pw = makeImagePage('image-bad-ratio', ['aspect_ratio' => '7:13']);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)->not->toContain('widget-image--ratio-');
});

it('emits max-width inline style for a valid CSS length', function () {
    foreach (['400px', '60%', '20rem', '15em', '50vw'] as $value) {
        $pw = makeImagePage('image-mw-' . md5($value), ['max_width' => $value]);
        $html = WidgetRenderer::render($pw)['html'];

        expect($html)->toContain('max-width: ' . $value);
    }
});

it('assumes pixels when max_width is a bare number', function () {
    $pw = makeImagePage('image-mw-bare', ['max_width' => '400']);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)->toContain('max-width: 400px');
});

it('drops invalid max_width values silently', function () {
    foreach (['javascript:alert(1)', 'red;', '100 px', '40 % '] as $bad) {
        $pw = makeImagePage('image-mw-bad-' . md5($bad), ['max_width' => $bad]);
        $html = WidgetRenderer::render($pw)['html'];

        expect($html)->not->toContain('max-width:');
        expect($html)->not->toContain('javascript');
    }
});

it('omits the style attribute when max_width is empty', function () {
    $pw = makeImagePage('image-mw-empty', ['max_width' => '']);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)->not->toContain('style="max-width');
});
