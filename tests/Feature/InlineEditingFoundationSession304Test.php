<?php

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use App\Services\WidgetRegistry;
use App\Services\WidgetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Session 304 Phase 2 — in-page inline editing. Covers the code-declared
// capability gate, the widget-level exclusion that keeps dormant-annotation
// / data-driven widgets inert, recursive nested-richtext sanitization of
// every inline path, the builder-only empty-wrapper rendering, and the
// durable Tier-B exempt-set guard.

function s304Registry(): WidgetRegistry
{
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    return app(WidgetRegistry::class);
}

// ── Capability gate ─────────────────────────────────────────────────────────

it('fails closed: inlineEditable() defaults to false on the base definition', function () {
    $reg = s304Registry();

    // Session 304 safe-set + the session 307 widening pass: every widget
    // whose template has at least one Tier-A-clear display-prose node
    // (heading + the existing TextBlock/Hero/ThreeBuckets/PricingChart
    // bodies + nested PricingChart prose). The widget-level gate is opt-in
    // by code declaration; widening only adds widgets that have a Tier-A
    // annotation and never breaches the Tier-B exempt set.
    foreach ([
        'text_block', 'hero', 'three_buckets', 'pricing_chart',
        'bar_chart', 'blog_listing', 'board_members', 'donation_form',
        'event_calendar', 'events_listing', 'map_embed', 'product_carousel',
        'social_sharing',
    ] as $h) {
        expect($reg->find($h)->inlineEditable())->toBeTrue("$h should be inline-editable");
    }

    // Excluded by design — Image (only text fields are alt_text/max_width,
    // both Tier-B) and Nav (branding_text is Tier-B; parent/child_template
    // are data-driven {{item.*}}). The widget-level gate keeps the inline
    // editor from attaching to anything in these widgets regardless of any
    // template annotation that might be added.
    foreach (['image', 'nav'] as $h) {
        $def = $reg->find($h);
        if ($def === null) {
            continue;
        }
        expect($def->inlineEditable())->toBeFalse("$h must remain non-inline-editable");
    }
});

it('keeps the gate out of the database — toRow() carries no inline flag (no migration)', function () {
    $reg = s304Registry();
    $row = $reg->find('pricing_chart')->toRow();

    expect($row)->not->toHaveKey('inline_editable');

    $columns = \Illuminate\Support\Facades\Schema::getColumnListing('widget_types');
    expect($columns)->not->toContain('inline_editable');
});

it('exposes widget_type_inline_editable per widget without a schema column', function () {
    $reg  = s304Registry();
    $page = Page::factory()->create(['slug' => 's304-res', 'status' => 'published']);

    $tb = $page->widgets()->create([
        'widget_type_id' => WidgetType::where('handle', 'text_block')->firstOrFail()->id,
        'config' => ['content' => '<p>hi</p>'], 'sort_order' => 0, 'is_active' => true,
    ]);
    $img = $page->widgets()->create([
        'widget_type_id' => WidgetType::where('handle', 'image')->firstOrFail()->id,
        'config' => [], 'sort_order' => 1, 'is_active' => true,
    ]);

    $tbArr  = (new \App\Http\Resources\WidgetResource($tb->fresh('widgetType')))->toArray(request());
    $imgArr = (new \App\Http\Resources\WidgetResource($img->fresh('widgetType')))->toArray(request());

    expect($tbArr['widget_type_inline_editable'])->toBeTrue()
        ->and($imgArr['widget_type_inline_editable'])->toBeFalse();
});

// ── Builder-only empty wrappers + public parity ─────────────────────────────

it('renders path-addressed annotations + empty wrappers on canvas only', function () {
    $reg  = s304Registry();
    $page = Page::factory()->create(['slug' => 's304-pc', 'status' => 'published']);

    $pw = $page->widgets()->create([
        'widget_type_id' => WidgetType::where('handle', 'pricing_chart')->firstOrFail()->id,
        'config' => [
            'heading' => 'Plans',
            'columns' => [
                ['title' => 'Basic', 'price' => '', 'attribute_rows' => [['label' => 'Seats', 'value' => '<p>5</p>']]],
            ],
        ],
        'sort_order' => 0, 'is_active' => true,
    ]);

    // Session 305: inline-editing is its own opt-in flag — only the
    // builder preview renderer passes true. The canvas slot alone no
    // longer implies editing scaffolding (which was leaking onto the
    // public site when PageBlockRenderer hit the default slot).
    $canvas = WidgetRenderer::render($pw, slotHandle: 'page_builder_canvas', inlineEditing: true)['html'];
    $public = WidgetRenderer::render($pw, slotHandle: 'page_main')['html'];

    // Path addressing reaches nested repeater leaves.
    expect($canvas)
        ->toContain('data-config-key="heading"')
        ->toContain('data-config-key="columns.0.title"')
        ->toContain('data-config-key="columns.0.price"')
        ->toContain('data-config-key="columns.0.attribute_rows.0.value"');

    // Builder shows the empty price slot as an editable wrapper with a
    // schema-label ghost; public never carries the placeholder and does not
    // gain empty optional wrappers.
    expect($canvas)->toContain('data-config-placeholder="Price"');
    expect($public)->not->toContain('data-config-placeholder');
});

// ── Recursive nested-richtext sanitization of every inline path ─────────────

it('sanitizes richtext at every inline path including nested repeater leaves', function () {
    $reg  = s304Registry();
    $page = Page::factory()->create(['slug' => 's304-san', 'status' => 'published']);

    $evil = '<p>ok</p><script>alert(1)</script>';

    $pw = $page->widgets()->create([
        'widget_type_id' => WidgetType::where('handle', 'pricing_chart')->firstOrFail()->id,
        'config' => [
            'subheading' => $evil,
            'footnote'   => $evil,
            'columns'    => [[
                'title'          => 'Keep <script>me()</script> raw?',
                'price'          => $evil,
                'lead_content'   => $evil,
                'attribute_rows' => [['label' => 'Plain', 'value' => $evil]],
            ]],
        ],
        'sort_order' => 0, 'is_active' => true,
    ]);

    $cfg = $pw->fresh()->config;

    // Every richtext leaf — top-level and nested — is sanitized.
    expect($cfg['subheading'])->not->toContain('<script>')
        ->and($cfg['footnote'])->not->toContain('<script>')
        ->and($cfg['columns'][0]['price'])->not->toContain('<script>')
        ->and($cfg['columns'][0]['lead_content'])->not->toContain('<script>')
        ->and($cfg['columns'][0]['attribute_rows'][0]['value'])->not->toContain('<script>')
        ->and($cfg['columns'][0]['price'])->toContain('<p>ok</p>');

    // Plain-text fields are not HTML-sanitized (escaped at render, not store)
    // and the nested structure round-trips intact.
    expect($cfg['columns'][0]['title'])->toContain('<script>me()</script>')
        ->and($cfg['columns'][0]['attribute_rows'][0]['label'])->toBe('Plain');
});

it('still sanitizes top-level richtext (TextBlock content regression)', function () {
    $reg  = s304Registry();
    $page = Page::factory()->create(['slug' => 's304-tb', 'status' => 'published']);

    $pw = $page->widgets()->create([
        'widget_type_id' => WidgetType::where('handle', 'text_block')->firstOrFail()->id,
        'config' => ['content' => '<p>hi</p><script>x()</script>'],
        'sort_order' => 0, 'is_active' => true,
    ]);

    expect($pw->fresh()->config['content'])
        ->toContain('<p>hi</p>')
        ->not->toContain('<script>');
});

// ── Tier-B exempt-set guard (durable regression artifact) ───────────────────

it('never annotates a Tier-B field as inline-editable in any widget template', function () {
    // Canonical exempt set (session 304 prompt, 40-widget safety pass):
    // declared text/richtext, token-free, rendered, but corruptive if edited
    // in place because they flow into CSS / attributes / chart config.
    $tierB = [
        'pricing_chart' => ['gap'],
        'three_buckets' => ['gap'],
        'image'         => ['max_width', 'alt_text'],
        'board_members' => ['image_aspect_ratio'],
        'nav'           => ['branding_text'],
        'bar_chart'     => ['x_label', 'y_label'],
    ];

    foreach (glob(base_path('app/Widgets/*/template.blade.php')) as $file) {
        $src = file_get_contents($file);
        foreach ($tierB as $widget => $keys) {
            foreach ($keys as $key) {
                expect($src)->not->toContain('data-config-key="' . $key . '"',
                    "$file annotates Tier-B key '$key' via literal data-config-key");
                foreach (["'key' => '$key'", "\"key\" => \"$key\""] as $needle) {
                    expect(str_contains($src, $needle))->toBeFalse(
                        "$file annotates Tier-B key '$key' via the inline-prose partial"
                    );
                }
            }
        }
    }
});

it('annotates the expected safe-set inline paths (guard is meaningful)', function () {
    $tb = file_get_contents(base_path('app/Widgets/TextBlock/template.blade.php'));
    $pc = file_get_contents(base_path('app/Widgets/PricingChart/template.blade.php'));

    expect($tb)->toContain('inline-prose')->toContain("'key' => 'content'");
    expect($pc)
        ->toContain("'key' => 'heading'")
        ->toContain("columns.{\$ci}.attribute_rows.{\$r}.value");
});
