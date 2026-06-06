<?php

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Regression guard for Flag 344-A (session 345): every SVG that lands in the
 * media library is sanitized at the single media-add seam (MediaSvgSanitizer on
 * MediaHasBeenAddedEvent), before its content hash is computed — so a stored SVG
 * can never carry executable content that would run on direct URL navigation.
 */
beforeEach(function () {
    Storage::fake('public');
    Queue::fake();

    $this->widget = makeSvgWidget();
});

function makeSvgWidget(): PageWidget
{
    $page = Page::factory()->create(['slug' => 'svg-' . uniqid()]);

    $widgetType = WidgetType::create([
        'handle'        => 'svg_widget_' . uniqid(),
        'label'         => 'SVG Widget',
        'render_mode'   => 'server',
        'collections'   => [],
        'config_schema' => [['key' => 'logo', 'type' => 'image']],
    ]);

    return $page->widgets()->create([
        'widget_type_id'    => $widgetType->id,
        'label'             => 'W',
        'config'            => [],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);
}

function attachSvg(PageWidget $owner, string $svg, string $name = 'logo.svg'): Media
{
    return $owner->addMediaFromString($svg)
        ->usingFileName($name)
        ->toMediaCollection('config_logo', 'public');
}

it('strips script, foreignObject and event handlers from a stored SVG, keeping benign shapes', function () {
    $malicious = <<<'SVG'
    <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" onload="steal()">
      <script>alert('xss')</script>
      <rect width="10" height="10" fill="red"/>
    </svg>
    SVG;

    $media = attachSvg($this->widget, $malicious);

    $stored = Storage::disk('public')->get($media->getPathRelativeToRoot());

    expect($stored)->not->toContain('<script')
        ->and($stored)->not->toContain('onload')
        ->and($stored)->not->toContain("alert('xss')")
        ->and($stored)->toContain('<rect');
});

it('strips javascript: URIs from a stored SVG', function () {
    $malicious = <<<'SVG'
    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
      <a xlink:href="javascript:steal()"><rect width="5" height="5"/></a>
    </svg>
    SVG;

    $media = attachSvg($this->widget, $malicious);

    $stored = Storage::disk('public')->get($media->getPathRelativeToRoot());

    expect($stored)->not->toContain('javascript:')
        ->and($stored)->toContain('<rect');
});

it('replaces an unparseable .svg upload with an inert empty SVG (no original payload survives)', function () {
    // Not valid XML (unclosed tag) → SvgSanitizer returns null → inert substitute.
    $broken = '<svg onload="steal()"><script>alert(1)</script>';

    $media = attachSvg($this->widget, $broken, 'broken.svg');

    $stored = Storage::disk('public')->get($media->getPathRelativeToRoot());

    expect($stored)->toBe('<svg xmlns="http://www.w3.org/2000/svg"></svg>')
        ->and($stored)->not->toContain('script')
        ->and($stored)->not->toContain('onload');
});

it('leaves a non-SVG upload byte-identical (the sanitizer does not over-reach)', function () {
    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
    );

    $media = $this->widget->addMediaFromString($png)
        ->usingFileName('pixel.png')
        ->toMediaCollection('config_logo', 'public');

    $stored = Storage::disk('public')->get($media->getPathRelativeToRoot());

    expect($stored)->toBe($png);
});
