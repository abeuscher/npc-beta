<?php

// Session 307 — standing builder↔public render-parity guard.
//
// Asserts that for each inline-eligible widget, the HTML produced by
// WidgetRenderer::render($pw, ..., inlineEditing: true) (the page-builder
// canvas branch) is appearance-equivalent to the same call with
// inlineEditing: false (the public render path), modulo the documented
// builder-only emissions from widget-shared/inline-prose.blade.php:
//
//   - the data-config-placeholder="..." attribute (builder only); and
//   - empty inline-prose wrappers around blank non-`always` fields
//     (builder only — public skips them).
//
// Session 305's public-vs-builder render conflation split (WidgetRenderer
// grew an explicit `bool $inlineEditing` parameter; only the page-builder
// preview renderer passes true) made these the ONLY expected diffs — so a
// drift in any other attribute, class, structural element, or text node is
// a real divergence between the two render branches and must fail this test.
//
// Complementary to tests/Feature/AssetBundleDriftGuardTest.php (session 296),
// which is the served-bundle-layer drift guard. Both stay in the `design`
// Pest group; they cover different composition layers (this = render-time
// HTML equivalence; 296 = served stylesheet freshness) and do not duplicate.

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->group('design');

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

/**
 * Normalise either render branch so the documented builder-only diffs are
 * stripped from both. Anything left after this MUST be byte-identical
 * between builder and public.
 */
function s307NormaliseRender(string $html): string
{
    // Strip the data-config-placeholder attribute (builder-only emission).
    $html = preg_replace('/\s+data-config-placeholder="[^"]*"/', '', $html);

    // EventCalendar uses `Str::random(8)` to mint a per-render DOM id so
    // multiple calendars on one page don't collide. The same call is made
    // independently by each render branch, so the two ids never match.
    // Normalise to a stable token — this is a deliberate divergence within
    // a single widget render, not a public-vs-builder semantic difference.
    $html = preg_replace('/id="cal-[A-Za-z0-9]{8}"/', 'id="cal-STABLE"', $html);

    // Strip empty inline-prose wrappers — a tag carrying data-config-key +
    // data-config-type with an empty body. Builder renders these for blank
    // non-`always` fields; public omits them entirely. After stripping the
    // placeholder attr above, an `always`-empty wrapper renders identically
    // on both sides, so this strip applies symmetrically.
    $html = preg_replace(
        '/<(\w+)\s+class="[^"]*"\s+data-config-key="[^"]*"\s+data-config-type="(?:text|richtext)"><\/\1>/',
        '',
        $html,
    );

    // Collapse whitespace runs so trailing newlines from removed @if blocks
    // don't trip the comparison. The HTML's semantic shape is what matters.
    $html = preg_replace('/\s+/', ' ', $html);

    return trim($html);
}

function s307RenderPair(PageWidget $pw): array
{
    // Both branches use the page_builder_canvas slot — production's public
    // render path (PageBlockRenderer::WidgetRenderer::render($pw)) calls with
    // the default slot too. The 305 conflation split moved the editing-only
    // behaviour to the new $inlineEditing flag; the slot still drives shared
    // page-context token resolution on both branches.
    return [
        'builder' => WidgetRenderer::render($pw, slotHandle: 'page_builder_canvas', inlineEditing: true)['html'],
        'public'  => WidgetRenderer::render($pw, slotHandle: 'page_builder_canvas', inlineEditing: false)['html'],
    ];
}

function s307MakeWidget(Page $page, string $handle, array $config, int $sort = 0): PageWidget
{
    return $page->widgets()->create([
        'widget_type_id' => WidgetType::where('handle', $handle)->firstOrFail()->id,
        'config'         => $config,
        'sort_order'     => $sort,
        'is_active'      => true,
    ]);
}

it('TextBlock — builder and public renders match after normalisation (filled)', function () {
    $page = Page::factory()->create(['slug' => 's307-tb-filled', 'status' => 'published']);
    $pw = s307MakeWidget($page, 'text_block', [
        'content' => '<h2>Headline</h2><p>Body paragraph.</p>',
    ]);

    ['builder' => $builder, 'public' => $public] = s307RenderPair($pw);

    expect(s307NormaliseRender($builder))->toBe(s307NormaliseRender($public));
});

it('TextBlock — blank content still parity-equivalent (always-wrapper case)', function () {
    $page = Page::factory()->create(['slug' => 's307-tb-blank', 'status' => 'published']);
    $pw = s307MakeWidget($page, 'text_block', [
        'content' => '',
    ]);

    ['builder' => $builder, 'public' => $public] = s307RenderPair($pw);

    // The TextBlock content wrapper is `always` → renders on both sides
    // (empty on each). Normalisation strips both the placeholder attr and
    // the empty wrapper; the surviving shell must match.
    expect(s307NormaliseRender($builder))->toBe(s307NormaliseRender($public));
});

it('Hero — builder and public renders match after normalisation', function () {
    $page = Page::factory()->create(['slug' => 's307-hero', 'status' => 'published']);
    $pw = s307MakeWidget($page, 'hero', [
        'content'                    => '<h1>Welcome</h1><p>Lead copy.</p>',
        'background_overlay_opacity' => 50,
        'text_position'              => 'center-center',
        'min_height'                 => '24rem',
    ]);

    ['builder' => $builder, 'public' => $public] = s307RenderPair($pw);

    expect(s307NormaliseRender($builder))->toBe(s307NormaliseRender($public));
});

it('ThreeBuckets — one bucket filled, two empty (non-`always` wrappers)', function () {
    $page = Page::factory()->create(['slug' => 's307-tb3', 'status' => 'published']);
    $pw = s307MakeWidget($page, 'three_buckets', [
        'heading_1' => 'Bucket One',
        'body_1'    => '<p>First body.</p>',
        'heading_2' => '',
        'body_2'    => '',
        'heading_3' => '',
        'body_3'    => '',
    ]);

    ['builder' => $builder, 'public' => $public] = s307RenderPair($pw);

    // Bucket 2/3 wrappers are non-`always`: builder emits empty editable
    // wrappers with placeholders; public emits nothing. Normalisation strips
    // both and the bucket-1 filled prose must round-trip identically.
    expect(s307NormaliseRender($builder))->toBe(s307NormaliseRender($public));
});

it('PricingChart — populated chart (the canonical 304 stress test)', function () {
    $page = Page::factory()->create(['slug' => 's307-pc-full', 'status' => 'published']);
    $pw = s307MakeWidget($page, 'pricing_chart', [
        'eyebrow_label'     => 'Pricing',
        'heading'           => 'Choose your plan',
        'subheading'        => '<p>Find the right fit.</p>',
        'footnote'          => '<p>All prices in USD.</p>',
        'heading_alignment' => 'center',
        'column_count'      => 'auto',
        'attribute_row_count' => 'auto',
        'columns' => [
            [
                'eyebrow'        => 'Starter',
                'title'          => 'Basic',
                'price'          => '<p>$10/mo</p>',
                'lead_content'   => '<p>For individuals.</p>',
                'attribute_rows' => [
                    ['label' => 'Seats', 'value' => '<p>1</p>'],
                    ['label' => 'Support', 'value' => '<p>Email</p>'],
                ],
            ],
            [
                'eyebrow'        => 'Team',
                'title'          => 'Pro',
                'price'          => '<p>$50/mo</p>',
                'lead_content'   => '<p>For small teams.</p>',
                'attribute_rows' => [
                    ['label' => 'Seats', 'value' => '<p>10</p>'],
                    ['label' => 'Support', 'value' => '<p>Priority</p>'],
                ],
            ],
        ],
    ]);

    ['builder' => $builder, 'public' => $public] = s307RenderPair($pw);

    expect(s307NormaliseRender($builder))->toBe(s307NormaliseRender($public));
});

it('PricingChart — partially-empty chart exercises `always` subgrid tracks', function () {
    $page = Page::factory()->create(['slug' => 's307-pc-empty', 'status' => 'published']);
    $pw = s307MakeWidget($page, 'pricing_chart', [
        'heading'           => 'Plans',
        'heading_alignment' => 'center',
        'column_count'      => 'auto',
        'attribute_row_count' => 'auto',
        'columns' => [
            [
                'eyebrow'        => '',
                'title'          => 'Basic',
                'price'          => '',
                'lead_content'   => '',
                'attribute_rows' => [
                    ['label' => 'Seats', 'value' => '<p>5</p>'],
                ],
            ],
        ],
    ]);

    ['builder' => $builder, 'public' => $public] = s307RenderPair($pw);

    // Column-level fields (`eyebrow`, `price`, `lead_content`) are
    // `always` so their wrappers render on both sides — empty here. The
    // top-level subheading + footnote are non-`always`; their wrappers
    // appear only on builder. The shipped 304/305 contract is that public
    // is "clean public output, builder is public + the inline scaffolding";
    // normalisation collapses to that shared base.
    expect(s307NormaliseRender($builder))->toBe(s307NormaliseRender($public));
});

// ── Session 307 widening pass ───────────────────────────────────────────────
// Each widget added by Phase 2 (heading-only annotation + widget-level
// `inlineEditable()=true`). Configs set a non-empty heading so the inline-
// prose wrapper renders on both branches and the parity assertion exercises
// a real surface. Data-gated widgets (BarChart, ProductCarousel) early-
// return when their data contract resolves to an empty set; for those the
// parity assertion is trivially true (both branches return empty HTML) —
// the widget-level eligibility flip is covered by
// InlineEditingFoundationSession304Test, and the heading-render round-trip
// is covered by Playwright once the e2e suite is un-parked.

it('DonationForm — heading parity', function () {
    $page = Page::factory()->create(['slug' => 's307-df', 'status' => 'published']);
    $pw = s307MakeWidget($page, 'donation_form', ['heading' => 'Support our work']);

    ['builder' => $builder, 'public' => $public] = s307RenderPair($pw);
    expect(s307NormaliseRender($builder))->toBe(s307NormaliseRender($public));
});

it('EventsListing — heading parity', function () {
    $page = Page::factory()->create(['slug' => 's307-el', 'status' => 'published']);
    $pw = s307MakeWidget($page, 'events_listing', ['heading' => 'Upcoming Events']);

    ['builder' => $builder, 'public' => $public] = s307RenderPair($pw);
    expect(s307NormaliseRender($builder))->toBe(s307NormaliseRender($public));
});

it('EventCalendar — heading parity', function () {
    $page = Page::factory()->create(['slug' => 's307-ec', 'status' => 'published']);
    $pw = s307MakeWidget($page, 'event_calendar', ['heading' => 'Calendar']);

    ['builder' => $builder, 'public' => $public] = s307RenderPair($pw);
    expect(s307NormaliseRender($builder))->toBe(s307NormaliseRender($public));
});

it('BlogListing — heading parity', function () {
    $page = Page::factory()->create(['slug' => 's307-bl', 'status' => 'published']);
    $pw = s307MakeWidget($page, 'blog_listing', ['heading' => 'From the Blog']);

    ['builder' => $builder, 'public' => $public] = s307RenderPair($pw);
    expect(s307NormaliseRender($builder))->toBe(s307NormaliseRender($public));
});

it('BoardMembers — heading parity (non-empty heading satisfies outer gate)', function () {
    $page = Page::factory()->create(['slug' => 's307-bm', 'status' => 'published']);
    $pw = s307MakeWidget($page, 'board_members', ['heading' => 'Our Board']);

    ['builder' => $builder, 'public' => $public] = s307RenderPair($pw);
    expect(s307NormaliseRender($builder))->toBe(s307NormaliseRender($public));
});

it('MapEmbed — heading parity', function () {
    $page = Page::factory()->create(['slug' => 's307-me', 'status' => 'published']);
    $pw = s307MakeWidget($page, 'map_embed', [
        'heading'   => 'Find us',
        'map_input' => 'https://www.google.com/maps/embed?pb=!1m18!parity-test',
    ]);

    ['builder' => $builder, 'public' => $public] = s307RenderPair($pw);
    expect(s307NormaliseRender($builder))->toBe(s307NormaliseRender($public));
});

it('SocialSharing — heading parity (heading also seeds share-link text; documented dual-purpose coupling)', function () {
    $page = Page::factory()->create(['slug' => 's307-ss', 'status' => 'published']);
    $pw = s307MakeWidget($page, 'social_sharing', [
        'heading'   => 'Share this',
        'platforms' => ['bluesky', 'email', 'copy_link'],
    ]);

    ['builder' => $builder, 'public' => $public] = s307RenderPair($pw);
    expect(s307NormaliseRender($builder))->toBe(s307NormaliseRender($public));
});

// Session 308: BarChart + ProductCarousel had their `heading` field
// removed (no other inline-editable surface, so they also dropped out of
// the inline-eligible set). Their parity at the HTML layer remains
// trivially covered by the shared WidgetRenderer::render path — both
// branches early-return when their data set is empty, producing
// byte-identical empty HTML; the prior "heading parity" cases here
// asserted exactly that emptiness, so retiring them is a no-op in test
// coverage terms.

it('builder render emits data-config-placeholder; public render does not', function () {
    // Sanity assertion that the documented expected-diff actually exists —
    // proving the test above is doing real work, not vacuously passing
    // because both branches are accidentally identical.
    $page = Page::factory()->create(['slug' => 's307-sanity', 'status' => 'published']);
    $pw = s307MakeWidget($page, 'text_block', ['content' => '<p>Body.</p>']);

    ['builder' => $builder, 'public' => $public] = s307RenderPair($pw);

    expect($builder)->toContain('data-config-placeholder')
        ->and($public)->not->toContain('data-config-placeholder');
});
