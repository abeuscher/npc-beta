<?php

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use App\Services\MapEmbedParser;
use App\Services\WidgetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── MapEmbedParser — extractEmbedUrl ────────────────────────────────────────

it('extracts embed URL from Google Maps share/place URL', function () {
    $url = 'https://www.google.com/maps/place/Central+Park/@40.7828647,-73.9653551,15z';
    $result = MapEmbedParser::extractEmbedUrl($url);

    expect($result)->not->toBeNull()
        ->toContain('google.com/maps?q=')
        ->toContain('output=embed')
        ->toContain('Central+Park');
});

it('extracts embed URL from iframe snippet', function () {
    $iframe = '<iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy"></iframe>';
    $result = MapEmbedParser::extractEmbedUrl($iframe);

    expect($result)->toBe('https://www.google.com/maps/embed?pb=!1m18!1m12');
});

it('returns existing embed URL unchanged', function () {
    $url = 'https://www.google.com/maps/embed/v1/place?q=Central+Park';
    $result = MapEmbedParser::extractEmbedUrl($url);

    expect($result)->toBe($url);
});

it('returns null for non-Google URLs', function () {
    expect(MapEmbedParser::extractEmbedUrl('https://www.openstreetmap.org/search?query=paris'))
        ->toBeNull();

    expect(MapEmbedParser::extractEmbedUrl('https://malicious-site.com/maps/embed'))
        ->toBeNull();
});

it('returns null for empty or garbage input', function () {
    expect(MapEmbedParser::extractEmbedUrl(''))->toBeNull();
    expect(MapEmbedParser::extractEmbedUrl('not a url at all'))->toBeNull();
    expect(MapEmbedParser::extractEmbedUrl('   '))->toBeNull();
});

it('rejects iframe with non-Google src', function () {
    $iframe = '<iframe src="https://evil.com/maps/embed?pb=!1m18" width="600"></iframe>';
    $result = MapEmbedParser::extractEmbedUrl($iframe);

    expect($result)->toBeNull();
});

it('handles Google Maps @ URL format', function () {
    $url = 'https://www.google.com/maps/@40.7128,-74.0060,14z';
    $result = MapEmbedParser::extractEmbedUrl($url);

    expect($result)->not->toBeNull()
        ->toContain('google.com/maps?q=')
        ->toContain('output=embed');
});

it('handles Google Maps short link format', function () {
    $url = 'https://maps.app.goo.gl/abc123xyz';
    $result = MapEmbedParser::extractEmbedUrl($url);

    expect($result)->not->toBeNull()
        ->toContain('google.com/maps?q=')
        ->toContain('output=embed');
});

// ── Seeder ──────────────────────────────────────────────────────────────────

it('seeder creates map_embed widget with correct config schema', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $wt = WidgetType::where('handle', 'map_embed')->first();

    expect($wt)->not->toBeNull()
        ->and($wt->label)->toBe('Map Embed')
        ->and($wt->category)->toBe(['content'])
        ->and($wt->collections)->toBe([]);

    $keys = collect($wt->config_schema)->pluck('key')->filter()->values()->all();
    expect($keys)->toContain('heading')
        ->toContain('map_input')
        ->toContain('aspect_ratio')
        ->toContain('min_height')
        ->toContain('max_height');

    // Notice field has no key
    $notice = collect($wt->config_schema)->first(fn ($f) => ($f['type'] ?? '') === 'notice');
    expect($notice)->not->toBeNull()
        ->and($notice['variant'])->toBe('warning');
});

// ── Blade template rendering ────────────────────────────────────────────────

it('map embed blade renders iframe when valid URL provided', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $wt = WidgetType::where('handle', 'map_embed')->first();
    $page = Page::factory()->create();

    $pw = PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $wt->id,
        'config'         => [
            'heading'      => 'Our Location',
            'map_input'    => 'https://www.google.com/maps/embed/v1/place?q=Central+Park',
            'aspect_ratio' => '16/9',
            'min_height'   => 300,
            'max_height'   => 600,
        ],
        'sort_order' => 0,
    ]);

    $result = WidgetRenderer::render($pw);

    expect($result['html'])
        ->toContain('Our Location')
        ->toContain('widget-map-embed')
        ->toContain('map-embed__iframe')
        ->toContain('google.com/maps/embed')
        ->toContain('referrerpolicy="no-referrer-when-downgrade"')
        ->toContain('loading="lazy"')
        ->toContain('map-embed__overlay')
        ->toContain('Click to interact with map');
});

it('map embed blade renders nothing when invalid input provided', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $wt = WidgetType::where('handle', 'map_embed')->first();
    $page = Page::factory()->create();

    $pw = PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $wt->id,
        'config'         => [
            'map_input' => 'not a valid url',
        ],
        'sort_order' => 0,
    ]);

    $result = WidgetRenderer::render($pw);

    expect($result['html'])->not->toContain('iframe')
        ->not->toContain('widget-map-embed');
});
