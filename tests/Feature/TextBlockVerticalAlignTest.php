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

it('renders the TextBlock wrapper with the middle vertical-align class by default', function () {
    $host = Page::factory()->create([
        'type'         => 'default',
        'slug'         => 'text-block-default',
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ]);

    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => ['content' => '<p>Hello</p>'],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)
        ->toContain('class="widget-text-block widget-text-block--vertical-middle"')
        ->toContain('<p>Hello</p>');
});

it('renders the TextBlock wrapper with the configured vertical-align class', function () {
    $host = Page::factory()->create([
        'type'         => 'default',
        'slug'         => 'text-block-aligned',
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ]);

    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    foreach (['top', 'middle', 'bottom'] as $alignment) {
        $pw = $host->widgets()->create([
            'widget_type_id' => $wt->id,
            'config'         => ['content' => '<p>X</p>', 'vertical_align' => $alignment],
            'sort_order'     => 0,
            'is_active'      => true,
        ]);

        $html = WidgetRenderer::render($pw)['html'];

        expect($html)->toContain('widget-text-block--vertical-' . $alignment);
    }
});

it('falls back to middle vertical-align when the configured value is unknown', function () {
    $host = Page::factory()->create([
        'type'         => 'default',
        'slug'         => 'text-block-bad-alignment',
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ]);

    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => ['content' => '<p>X</p>', 'vertical_align' => 'centered-please'],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)->toContain('widget-text-block--vertical-middle');
});
