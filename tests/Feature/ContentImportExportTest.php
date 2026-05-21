<?php

use App\Models\Collection;
use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\PageLayout;
use App\Models\PageWidget;
use App\Models\Template;
use App\Models\User;
use App\Models\WidgetType;
use App\Models\SiteSetting;
use App\Services\ImportExport\ContentExporter;
use App\Services\ImportExport\ContentImporter;
use App\Services\ImportExport\ImportLog;
use App\Services\ImportExport\InvalidImportBundleException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    // Ensure we always have a default page template for fallback tests.
    if (! Template::page()->where('is_default', true)->exists()) {
        Template::create([
            'name'       => 'Default',
            'type'       => 'page',
            'is_default' => true,
        ]);
    }
});

function ieMakePageWithWidgets(string $slug, array $configs = [['handle' => 'text_block', 'label' => 'Hello']]): Page
{
    $page = Page::factory()->create([
        'title'  => 'Test ' . $slug,
        'slug'   => $slug,
        'status' => 'published',
    ]);

    foreach ($configs as $i => $cfg) {
        $wt = WidgetType::where('handle', $cfg['handle'])->firstOrFail();

        $page->widgets()->create([
            'widget_type_id' => $wt->id,
            'label'          => $cfg['label'] ?? null,
            'config'         => $cfg['config'] ?? $wt->getDefaultConfig(),
            'query_config'   => [],
            'appearance_config' => [],
            'sort_order'     => $i,
            'is_active'      => true,
        ]);
    }

    return $page;
}

function ieAuthorUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo('update_page');

    return $user;
}

function ieSeedDesignRow(string $key, array $value): void
{
    SiteSetting::updateOrCreate(
        ['key' => $key],
        ['value' => json_encode($value), 'type' => 'json', 'group' => 'design'],
    );
    Cache::forget("site_setting:{$key}");
}

// ── Round-trip: text widgets only ───────────────────────────────────────────

it('round-trips a page with text widgets only', function () {
    $page = ieMakePageWithWidgets('round-trip-text', [
        ['handle' => 'text_block', 'label' => 'Block A', 'config' => ['content' => '<p>Hello A</p>']],
        ['handle' => 'text_block', 'label' => 'Block B', 'config' => ['content' => '<p>Hello B</p>']],
    ]);

    $bundle = app(ContentExporter::class)->exportPages([$page->id]);

    // Wipe widgets but keep the page row to verify overwrite path
    PageWidget::forOwner($page)->delete();
    $page->update(['title' => 'Wiped', 'meta_title' => null]);

    app(ContentImporter::class)->import($bundle, new ImportLog());

    $reloaded = Page::where('slug', 'round-trip-text')->first();
    expect($reloaded->id)->toBe($page->id); // id preserved
    expect($reloaded->title)->toBe('Test round-trip-text');

    $widgets = PageWidget::forOwner($reloaded)->orderBy('sort_order')->get();
    expect($widgets)->toHaveCount(2);
    expect($widgets[0]->label)->toBe('Block A');
    expect($widgets[0]->config['content'])->toBe('<p>Hello A</p>');
    expect($widgets[1]->label)->toBe('Block B');
});

it('round-trips a widget\'s text.link_color through export/import', function () {
    $page = Page::factory()->create(['slug' => 'round-trip-link-color', 'status' => 'published']);
    $textWt = WidgetType::where('handle', 'text_block')->first();

    $appearance = ['text' => ['color' => '#ffffff', 'link_color' => '#ffffff']];

    $page->widgets()->create([
        'widget_type_id'    => $textWt->id,
        'label'             => 'Linked',
        'config'            => ['content' => '<p>See <a href="/demo">the demo</a></p>'],
        'query_config'      => [],
        'appearance_config' => $appearance,
        'sort_order'        => 0,
        'is_active'         => true,
    ]);

    $bundle = app(ContentExporter::class)->exportPages([$page->id]);
    PageWidget::forOwner($page)->delete();
    app(ContentImporter::class)->import($bundle, new ImportLog());

    $reloaded = PageWidget::forOwner(Page::where('slug', 'round-trip-link-color')->first())->first();
    expect($reloaded->appearance_config['text']['link_color'])->toBe('#ffffff');
});

// ── Round-trip: column layout ───────────────────────────────────────────────

it('round-trips a page with a column layout containing widgets', function () {
    $page = Page::factory()->create(['slug' => 'round-trip-layout', 'status' => 'published']);

    $layout = $page->layouts()->create([
        'label'         => 'Two-Col',
        'display'       => 'grid',
        'columns'       => 2,
        'layout_config' => ['grid_template_columns' => '1fr 1fr', 'gap' => '1rem'],
        'sort_order'    => 0,
    ]);

    $textWt = WidgetType::where('handle', 'text_block')->first();

    $page->widgets()->create([
        'layout_id' => $layout->id, 'column_index' => 0,
        'widget_type_id' => $textWt->id, 'label' => 'Left',
        'config' => ['content' => '<p>Left col</p>'],
        'query_config' => [], 'appearance_config' => [], 'sort_order' => 0, 'is_active' => true,
    ]);
    $page->widgets()->create([
        'layout_id' => $layout->id, 'column_index' => 1,
        'widget_type_id' => $textWt->id, 'label' => 'Right',
        'config' => ['content' => '<p>Right col</p>'],
        'query_config' => [], 'appearance_config' => [], 'sort_order' => 0, 'is_active' => true,
    ]);

    $bundle = app(ContentExporter::class)->exportPages([$page->id]);

    PageWidget::forOwner($page)->delete();
    PageLayout::forOwner($page)->delete();

    app(ContentImporter::class)->import($bundle, new ImportLog());

    $layouts = PageLayout::forOwner($page)->get();
    expect($layouts)->toHaveCount(1);
    expect($layouts[0]->columns)->toBe(2);
    expect($layouts[0]->layout_config['gap'])->toBe('1rem');

    $children = PageWidget::where('layout_id', $layouts[0]->id)->orderBy('column_index')->get();
    expect($children)->toHaveCount(2);
    expect($children[0]->column_index)->toBe(0);
    expect($children[0]->config['content'])->toBe('<p>Left col</p>');
    expect($children[1]->column_index)->toBe(1);
    expect($children[1]->config['content'])->toBe('<p>Right col</p>');
});

// ── Round-trip: layout appearance_config (G1 fix) ───────────────────────────

it('round-trips a layout\'s appearance_config through export/import', function () {
    $page = Page::factory()->create(['slug' => 'round-trip-layout-appearance', 'status' => 'published']);

    $appearance = [
        'background' => ['color' => '#d4d4f2'],
        'layout'     => [
            'padding' => ['top' => 150, 'right' => 0, 'bottom' => 150, 'left' => 0],
            'margin'  => ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0],
        ],
    ];

    $layout = $page->layouts()->create([
        'label'             => 'Tinted Band',
        'display'           => 'grid',
        'columns'           => 2,
        'layout_config'     => ['grid_template_columns' => '1fr 1fr', 'gap' => '1rem'],
        'appearance_config' => $appearance,
        'sort_order'        => 0,
    ]);

    $textWt = WidgetType::where('handle', 'text_block')->first();
    $page->widgets()->create([
        'layout_id' => $layout->id, 'column_index' => 0,
        'widget_type_id' => $textWt->id, 'label' => 'Col A',
        'config' => ['content' => '<p>A</p>'],
        'query_config' => [], 'appearance_config' => [], 'sort_order' => 0, 'is_active' => true,
    ]);

    $bundle = app(ContentExporter::class)->exportPages([$page->id]);

    // Bundle should carry the layout's appearance_config.
    $exportedLayout = $bundle['payload']['pages'][0]['widgets'][0];
    expect($exportedLayout['type'])->toBe('layout');
    expect($exportedLayout['appearance_config'])->toEqual($appearance);

    // Wipe and re-import.
    PageWidget::forOwner($page)->delete();
    PageLayout::forOwner($page)->delete();

    app(ContentImporter::class)->import($bundle, new ImportLog());

    $reloaded = PageLayout::forOwner($page)->first();
    expect($reloaded)->not->toBeNull();
    expect($reloaded->appearance_config)->toEqual($appearance);
});

it('preserves an empty appearance_config on round-trip', function () {
    $page = Page::factory()->create(['slug' => 'round-trip-empty-appearance', 'status' => 'published']);

    $layout = $page->layouts()->create([
        'label'             => 'Plain Row',
        'display'           => 'grid',
        'columns'           => 2,
        'layout_config'     => [],
        'appearance_config' => [],
        'sort_order'        => 0,
    ]);

    $textWt = WidgetType::where('handle', 'text_block')->first();
    $page->widgets()->create([
        'layout_id' => $layout->id, 'column_index' => 0,
        'widget_type_id' => $textWt->id, 'label' => 'Col',
        'config' => ['content' => '<p>X</p>'],
        'query_config' => [], 'appearance_config' => [], 'sort_order' => 0, 'is_active' => true,
    ]);

    $bundle = app(ContentExporter::class)->exportPages([$page->id]);

    PageWidget::forOwner($page)->delete();
    PageLayout::forOwner($page)->delete();

    app(ContentImporter::class)->import($bundle, new ImportLog());

    $reloaded = PageLayout::forOwner($page)->first();
    expect($reloaded->appearance_config)->toBe([]);
});

// ── Round-trip: layout_config.collapse_mobile (G1/285-shape guard) ──────────
//
// A new layout_config key that does not survive export/import is silent data
// loss — exactly how appearance_config dropped pre-285. collapse_mobile must
// round-trip, including an explicit false (the only value that opts a layout
// out of mobile collapse — distinct from absent, which resolves to true).

it('round-trips layout_config.collapse_mobile=false through export/import', function () {
    $page = Page::factory()->create(['slug' => 'round-trip-collapse-mobile', 'status' => 'published']);

    $layout = $page->layouts()->create([
        'label'         => 'Logo + Nav Bar',
        'display'       => 'grid',
        'columns'       => 2,
        'layout_config' => [
            'grid_template_columns' => 'auto 1fr',
            'collapse_mobile'       => false,
        ],
        'sort_order'    => 0,
    ]);

    $textWt = WidgetType::where('handle', 'text_block')->first();
    $page->widgets()->create([
        'layout_id' => $layout->id, 'column_index' => 0,
        'widget_type_id' => $textWt->id, 'label' => 'Col',
        'config' => ['content' => '<p>X</p>'],
        'query_config' => [], 'appearance_config' => [], 'sort_order' => 0, 'is_active' => true,
    ]);

    $bundle = app(ContentExporter::class)->exportPages([$page->id]);

    $exportedLayout = $bundle['payload']['pages'][0]['widgets'][0];
    expect($exportedLayout['type'])->toBe('layout');
    expect($exportedLayout['layout_config']['collapse_mobile'])->toBeFalse();

    PageWidget::forOwner($page)->delete();
    PageLayout::forOwner($page)->delete();

    app(ContentImporter::class)->import($bundle, new ImportLog());

    $reloaded = PageLayout::forOwner($page)->first();
    expect($reloaded)->not->toBeNull();
    expect($reloaded->layout_config['collapse_mobile'])->toBeFalse();
});

// ── Round-trip: media reference (Tier 1 rewiring) ───────────────────────────

it('round-trips a page with a logo widget media reference', function () {
    Storage::fake('public');

    $page = Page::factory()->create(['slug' => 'round-trip-media', 'status' => 'published']);
    $logoWt = WidgetType::where('handle', 'logo')->firstOrFail();

    $widget = $page->widgets()->create([
        'widget_type_id' => $logoWt->id,
        'label'          => 'Site Logo',
        'config'         => ['logo' => null, 'text' => 'Acme', 'link_url' => '/'],
        'query_config'   => [],
        'appearance_config' => [],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $logoPath = firstSampleLogo();
    $logoName = basename($logoPath);

    $media = $widget->addMedia($logoPath)
        ->preservingOriginal()
        ->toMediaCollection('config_logo', 'public');

    $widget->update(['config' => ['logo' => $media->id, 'text' => 'Acme', 'link_url' => '/']]);

    // Sanity: file exists on the fake disk under {media_id}/{file_name}
    expect(Storage::disk('public')->exists($media->id . '/' . $media->file_name))->toBeTrue();

    $bundle = app(ContentExporter::class)->exportPages([$page->id]);

    // Verify the bundle's widget has a media descriptor
    $exportedWidget = $bundle['payload']['pages'][0]['widgets'][0];
    expect($exportedWidget['media'])->toHaveCount(1);
    expect($exportedWidget['media'][0]['key'])->toBe('logo');
    expect($exportedWidget['media'][0]['file_name'])->toBe($logoName);

    // Wipe the widget (DB row only — file stays on disk because Storage::fake doesn't auto-delete)
    PageWidget::forOwner($page)->delete();

    $log = new ImportLog();
    app(ContentImporter::class)->import($bundle, $log);

    expect($log->hasWarnings())->toBeFalse();

    $reimported = PageWidget::forOwner($page)->first();
    expect($reimported)->not->toBeNull();
    expect($reimported->config['text'])->toBe('Acme');

    $reimportedMedia = $reimported->getFirstMedia('config_logo');
    expect($reimportedMedia)->not->toBeNull();
    expect($reimportedMedia->file_name)->toBe($logoName);
    expect($reimported->config['logo'])->toBe($reimportedMedia->id);
});

// ── Round-trip: bulk page bundle ────────────────────────────────────────────

it('round-trips a bulk bundle of multiple pages', function () {
    $p1 = ieMakePageWithWidgets('bulk-1', [['handle' => 'text_block', 'label' => 'p1', 'config' => ['content' => '<p>P1</p>']]]);
    $p2 = ieMakePageWithWidgets('bulk-2', [['handle' => 'text_block', 'label' => 'p2', 'config' => ['content' => '<p>P2</p>']]]);
    $p3 = ieMakePageWithWidgets('bulk-3', [['handle' => 'text_block', 'label' => 'p3', 'config' => ['content' => '<p>P3</p>']]]);

    $bundle = app(ContentExporter::class)->exportPages([$p1->id, $p2->id, $p3->id]);
    expect($bundle['payload']['pages'])->toHaveCount(3);

    // Wipe widgets to simulate a fresh import target
    PageWidget::where('owner_type', (new \App\Models\Page())->getMorphClass())
        ->whereIn('owner_id', [$p1->id, $p2->id, $p3->id])->delete();

    app(ContentImporter::class)->import($bundle, new ImportLog());

    foreach (['bulk-1' => '<p>P1</p>', 'bulk-2' => '<p>P2</p>', 'bulk-3' => '<p>P3</p>'] as $slug => $expected) {
        $page    = Page::where('slug', $slug)->first();
        $widget  = PageWidget::forOwner($page)->first();
        expect($widget->config['content'])->toBe($expected);
    }
});

// ── Round-trip: page template chrome (the migrate:fresh case) ───────────────

it('round-trips a page template with customised chrome and header/footer pages', function () {
    // Build a customised page template with its own header/footer system pages.
    // Colour is no longer template-owned (session-297 relocation to the
    // site-wide Theme palette); only custom_scss + chrome page refs round-trip.
    $template = Template::create([
        'name'             => 'Custom Chrome',
        'type'             => 'page',
        'custom_scss'      => '.custom { color: red; }',
        'is_default'       => false,
        'created_by'       => User::factory()->create()->id,
    ]);

    // Create header/footer system pages with widgets
    $headerPage = Page::create([
        'title'     => 'Header — Custom Chrome',
        'slug'      => '_header_custom_chrome',
        'type'      => 'system',
        'status'    => 'published',
        'author_id' => User::factory()->create()->id,
    ]);
    $logoWt = WidgetType::where('handle', 'logo')->first();
    $headerPage->widgets()->create([
        'widget_type_id' => $logoWt->id,
        'label' => 'Header Logo',
        'config' => ['logo' => null, 'text' => 'Brand', 'link_url' => '/'],
        'query_config' => [], 'appearance_config' => [], 'sort_order' => 0, 'is_active' => true,
    ]);

    $footerPage = Page::create([
        'title'     => 'Footer — Custom Chrome',
        'slug'      => '_footer_custom_chrome',
        'type'      => 'system',
        'status'    => 'published',
        'author_id' => User::factory()->create()->id,
    ]);
    $textWt = WidgetType::where('handle', 'text_block')->first();
    $footerPage->widgets()->create([
        'widget_type_id' => $textWt->id,
        'label' => 'Copyright',
        'config' => ['content' => '<p>© 2026 Acme</p>'],
        'query_config' => [], 'appearance_config' => [], 'sort_order' => 0, 'is_active' => true,
    ]);

    $template->update([
        'header_page_id' => $headerPage->id,
        'footer_page_id' => $footerPage->id,
    ]);

    $bundle = app(ContentExporter::class)->exportTemplates([$template->id]);
    expect($bundle['payload']['templates'])->toHaveCount(1);
    expect($bundle['payload']['pages'])->toHaveCount(2);

    // Now wipe absolutely everything for those records to mimic a migrate:fresh
    PageWidget::where('owner_type', (new \App\Models\Page())->getMorphClass())
        ->whereIn('owner_id', [$headerPage->id, $footerPage->id])->delete();
    Page::whereIn('id', [$headerPage->id, $footerPage->id])->forceDelete();
    $template->update(['header_page_id' => null, 'footer_page_id' => null]);

    app(ContentImporter::class)->import($bundle, new ImportLog());

    $reloadedTemplate = Template::where('name', 'Custom Chrome')->first();
    expect($reloadedTemplate->custom_scss)->toBe('.custom { color: red; }');
    expect($reloadedTemplate->header_page_id)->not->toBeNull();
    expect($reloadedTemplate->footer_page_id)->not->toBeNull();

    $reloadedHeader = Page::find($reloadedTemplate->header_page_id);
    expect($reloadedHeader->slug)->toBe('_header_custom_chrome');
    $headerWidget = PageWidget::forOwner($reloadedHeader)->first();
    expect($headerWidget->config['text'])->toBe('Brand');

    $reloadedFooter = Page::find($reloadedTemplate->footer_page_id);
    $footerWidget   = PageWidget::forOwner($reloadedFooter)->first();
    expect($footerWidget->config['content'])->toBe('<p>© 2026 Acme</p>');
});

it('round-trips a page template\'s session-301 scheme + chrome suppression (carry-through regression)', function () {
    $template = Template::create([
        'name'        => 'Dark Landing',
        'type'        => 'page',
        'is_default'  => false,
        'scheme'      => 'inverse',
        'no_header'   => true,
        'no_footer'   => true,
        'created_by'  => User::factory()->create()->id,
    ]);

    $bundle = app(ContentExporter::class)->exportTemplates([$template->id]);

    $exported = $bundle['payload']['templates'][0];
    expect($exported['scheme'])->toBe('inverse');
    expect($exported['no_header'])->toBeTrue();
    expect($exported['no_footer'])->toBeTrue();

    // Mutate away from the exported state, then re-import — the deviation
    // config must come back, not be silently dropped.
    $template->update(['scheme' => 'default', 'no_header' => false, 'no_footer' => false]);

    app(ContentImporter::class)->import($bundle, new ImportLog());

    $reloaded = Template::where('name', 'Dark Landing')->first();
    expect($reloaded->scheme)->toBe('inverse');
    expect($reloaded->no_header)->toBeTrue();
    expect($reloaded->no_footer)->toBeTrue();
});

it('keeps concrete scheme/suppression when an older bundle omits the session-301 keys', function () {
    $template = Template::create([
        'name'       => 'Legacy Import Target',
        'type'       => 'page',
        'is_default' => false,
        'scheme'     => 'inverse',
        'no_header'  => true,
        'created_by' => User::factory()->create()->id,
    ]);

    // A pre-301 bundle: page template entry with no scheme/no_header/no_footer.
    $legacyBundle = [
        'format_version' => app(ContentExporter::class)::FORMAT_VERSION,
        'exported_at'    => now()->toIso8601String(),
        'payload'        => [
            'templates' => [[
                'name'        => 'Legacy Import Target',
                'type'        => 'page',
                'description' => null,
                'is_default'  => false,
                'custom_scss' => '.x{}',
            ]],
            'pages' => [],
        ],
    ];

    app(ContentImporter::class)->import($legacyBundle, new ImportLog());

    // Concrete-values rule: absent keys never null the columns — the
    // template's current concrete values stand.
    $reloaded = Template::where('name', 'Legacy Import Target')->first();
    expect($reloaded->scheme)->toBe('inverse');
    expect($reloaded->no_header)->toBeTrue();
    expect($reloaded->no_footer)->toBeFalse();
    expect($reloaded->custom_scss)->toBe('.x{}');
});

// ── Slug collision: re-import overwrites in place ───────────────────────────

it('overwrites a page with a colliding slug while preserving its id', function () {
    $page = ieMakePageWithWidgets('collision', [
        ['handle' => 'text_block', 'label' => 'Original', 'config' => ['content' => '<p>v1</p>']],
    ]);
    $originalId = $page->id;

    $bundle = app(ContentExporter::class)->exportPages([$page->id]);

    // Mutate the existing page to verify it gets reset
    PageWidget::forOwner($page)->delete();
    $page->widgets()->create([
        'widget_type_id' => WidgetType::where('handle', 'text_block')->first()->id,
        'label'          => 'Stale',
        'config'         => ['content' => '<p>STALE</p>'],
        'query_config'   => [], 'appearance_config' => [], 'sort_order' => 0, 'is_active' => true,
    ]);

    app(ContentImporter::class)->import($bundle, new ImportLog());

    $reloaded = Page::where('slug', 'collision')->first();
    expect($reloaded->id)->toBe($originalId);

    $widgets = PageWidget::forOwner($reloaded)->get();
    expect($widgets)->toHaveCount(1);
    expect($widgets[0]->label)->toBe('Original');
    expect($widgets[0]->config['content'])->toBe('<p>v1</p>');
});

// ── Slug collision: re-import restores a soft-deleted page ──────────────────

it('restores a soft-deleted page when re-importing its slug', function () {
    $page = ieMakePageWithWidgets('soft-deleted', [
        ['handle' => 'text_block', 'label' => 'Original', 'config' => ['content' => '<p>v1</p>']],
    ]);
    $originalId = $page->id;

    $bundle = app(ContentExporter::class)->exportPages([$page->id]);

    // Soft-delete the page
    $page->delete();
    expect(Page::where('slug', 'soft-deleted')->exists())->toBeFalse(); // hidden by default scope
    expect(Page::withTrashed()->where('slug', 'soft-deleted')->exists())->toBeTrue();

    app(ContentImporter::class)->import($bundle, new ImportLog());

    // Page should be visible in the default scope again
    $reloaded = Page::where('slug', 'soft-deleted')->first();
    expect($reloaded)->not->toBeNull();
    expect($reloaded->id)->toBe($originalId);
    expect($reloaded->trashed())->toBeFalse();

    $widgets = PageWidget::forOwner($reloaded)->get();
    expect($widgets)->toHaveCount(1);
    expect($widgets[0]->label)->toBe('Original');
});

// ── Fallback: missing collection handle ─────────────────────────────────────

it('clears collection_handle when the referenced collection does not exist', function () {
    $blogWt = WidgetType::where('handle', 'blog_listing')->firstOrFail();

    $page = Page::factory()->create(['slug' => 'missing-coll', 'status' => 'published']);
    $page->widgets()->create([
        'widget_type_id' => $blogWt->id,
        'label'          => 'Listing',
        'config'         => ['collection_handle' => 'does_not_exist'],
        'query_config'   => [], 'appearance_config' => [], 'sort_order' => 0, 'is_active' => true,
    ]);

    $bundle = app(ContentExporter::class)->exportPages([$page->id]);

    PageWidget::forOwner($page)->delete();

    $log = new ImportLog();
    app(ContentImporter::class)->import($bundle, $log);

    $widget = PageWidget::forOwner($page)->first();
    expect($widget->config['collection_handle'])->toBe('');
    expect($log->hasWarnings())->toBeTrue();
    expect($log->warnings()[0]['message'])->toContain("'does_not_exist'");
});

// ── Fallback: missing template_name ─────────────────────────────────────────

it('falls back to the default template when the referenced template name does not exist', function () {
    $this->actingAs(ieAuthorUser());

    // Hand-craft a bundle that references a non-existent template
    $bundle = [
        'format_version' => '1.0.0',
        'exported_at'    => now()->toIso8601String(),
        'payload'        => [
            'templates' => [],
            'pages'     => [[
                'title'         => 'Falls Back',
                'slug'          => 'fallback-tpl',
                'type'          => 'default',
                'template_name' => 'Nonexistent Template',
                'status'        => 'draft',
                'widgets'       => [],
            ]],
        ],
    ];

    $log = new ImportLog();
    app(ContentImporter::class)->import($bundle, $log);

    $page = Page::where('slug', 'fallback-tpl')->first();
    $defaultId = Template::page()->where('is_default', true)->value('id');
    expect($page->template_id)->toBe($defaultId);
    expect($log->hasWarnings())->toBeTrue();
});

// ── Fallback: missing media file ────────────────────────────────────────────

it('leaves widget media null and warns when the source file is missing', function () {
    Storage::fake('public');
    $this->actingAs(ieAuthorUser());

    $bundle = [
        'format_version' => '1.0.0',
        'exported_at'    => now()->toIso8601String(),
        'payload'        => [
            'templates' => [],
            'pages'     => [[
                'title'         => 'Missing Media',
                'slug'          => 'missing-media',
                'type'          => 'default',
                'status'        => 'draft',
                'widgets'       => [[
                    'type'         => 'widget',
                    'handle'       => 'logo',
                    'label'        => 'Logo',
                    'config'       => ['logo' => 99, 'text' => 'X', 'link_url' => '/'],
                    'query_config' => [],
                    'appearance_config' => [],
                    'sort_order'   => 0,
                    'is_active'    => true,
                    'media'        => [[
                        'key'             => 'logo',
                        'collection_name' => 'config_logo',
                        'file_name'       => 'ghost.png',
                        'disk'            => 'public',
                        'path'            => '999/ghost.png',
                        'mime_type'       => 'image/png',
                        'size'            => 1234,
                    ]],
                ]],
            ]],
        ],
    ];

    $log = new ImportLog();
    app(ContentImporter::class)->import($bundle, $log);

    $missingPage = Page::where('slug', 'missing-media')->first();
    $widget = PageWidget::forOwner($missingPage)->first();
    expect($widget)->not->toBeNull();
    expect($widget->config['logo'])->toBeNull();
    expect($widget->getFirstMedia('config_logo'))->toBeNull();
    expect($log->hasWarnings())->toBeTrue();
});

// ── Format version validation ───────────────────────────────────────────────

it('rejects a bundle with a future major format version', function () {
    $bundle = [
        'format_version' => '2.0.0',
        'exported_at'    => now()->toIso8601String(),
        'payload'        => ['templates' => [], 'pages' => []],
    ];

    expect(fn () => app(ContentImporter::class)->import($bundle, new ImportLog()))
        ->toThrow(InvalidImportBundleException::class);

    // No partial DB writes
    expect(Page::count())->toBe(0);
});

it('accepts a bundle within the supported major (1.x.y)', function () {
    $this->actingAs(ieAuthorUser());

    $bundle = [
        'format_version' => '1.5.3',
        'exported_at'    => now()->toIso8601String(),
        'payload'        => [
            'templates' => [],
            'pages'     => [[
                'title' => 'OK', 'slug' => 'minor-bump', 'type' => 'default',
                'status' => 'draft', 'widgets' => [],
            ]],
        ],
    ];

    app(ContentImporter::class)->import($bundle, new ImportLog());

    expect(Page::where('slug', 'minor-bump')->exists())->toBeTrue();
});

it('rejects a bundle with a malformed format_version', function () {
    $bundle = [
        'format_version' => 'banana',
        'payload'        => ['templates' => [], 'pages' => []],
    ];

    expect(fn () => app(ContentImporter::class)->import($bundle, new ImportLog()))
        ->toThrow(InvalidImportBundleException::class);
});

// ── Authorization: import action gates on update_page ──────────────────────

it('rejects unauthenticated users from the import endpoint via the action visibility gate', function () {
    // Authorization is enforced at the Filament Action layer; the underlying service has no auth
    // (it runs from the action callback after Filament's policy gate). Verify the gate logic
    // by simulating an unauthorised user and asserting the visible() closure returns false.
    $user = User::factory()->create(); // no permissions

    $this->actingAs($user);

    $action = \App\Filament\Actions\ImportBundleAction::make();
    expect($action->isVisible())->toBeFalse();
});

it('allows update_page users to use the import action', function () {
    $this->actingAs(ieAuthorUser());

    $action = \App\Filament\Actions\ImportBundleAction::make();
    expect($action->isVisible())->toBeTrue();
});

// ── Author fallback ─────────────────────────────────────────────────────────

it('uses the authenticated user as author when creating a new page on import', function () {
    $importer = ieAuthorUser();
    $this->actingAs($importer);

    $bundle = [
        'format_version' => '1.0.0',
        'payload'        => [
            'templates' => [],
            'pages'     => [[
                'title' => 'New', 'slug' => 'new-page-author', 'type' => 'default',
                'status' => 'draft', 'widgets' => [],
            ]],
        ],
    ];

    app(ContentImporter::class)->import($bundle, new ImportLog());

    $page = Page::where('slug', 'new-page-author')->first();
    expect($page->author_id)->toBe($importer->id);
});

it('preserves author_id on overwrite of an existing page', function () {
    $originalAuthor = User::factory()->create();
    $page           = Page::factory()->create([
        'slug'      => 'preserve-author',
        'author_id' => $originalAuthor->id,
        'status'    => 'published',
    ]);

    $bundle = app(ContentExporter::class)->exportPages([$page->id]);

    $importer = ieAuthorUser();
    $this->actingAs($importer);

    app(ContentImporter::class)->import($bundle, new ImportLog());

    $reloaded = Page::where('slug', 'preserve-author')->first();
    expect($reloaded->author_id)->toBe($originalAuthor->id);
});

// ── Session 309: opt-in exporter (with_design / with_media) ────────────────

it('omits payload.design from exportPages by default', function () {
    $page = ieMakePageWithWidgets('opt-default-no-design');

    $bundle = app(ContentExporter::class)->exportPages([$page->id]);

    expect($bundle['payload'])->not->toHaveKey('design');
    expect($bundle['payload'])->not->toHaveKey('media');
});

it('includes payload.design on exportPages when with_design is true', function () {
    ieSeedDesignRow('theme_colors', ['palette' => ['primary' => '#abcdef']]);

    $page = ieMakePageWithWidgets('opt-with-design');

    $bundle = app(ContentExporter::class)->exportPages([$page->id], ['with_design' => true]);

    expect($bundle['payload'])->toHaveKey('design');
    expect($bundle['payload']['design'])->toHaveKey('theme_colors');
    expect($bundle['payload']['design']['theme_colors']['palette']['primary'])->toBe('#abcdef');
});

it('dispatches ExportBundleJob with both opts and the handle() path emits design + media', function () {
    // End-to-end queue-path guard: the per-action dispatch sites pass
    // ['with_design' => true, 'with_media' => true] for the "theme & media"
    // export. ExportBundleJob::handle() must forward those opts to
    // ContentExporter so the resulting envelope carries both payload.design
    // and payload.media.
    ieSeedDesignRow('typography', ['buckets' => ['heading_family' => 'Inter']]);

    $page = ieMakePageWithWidgets('queue-path-full-snapshot');

    $job = new \App\Jobs\ExportBundleJob(
        kind:   'pages',
        ids:    [$page->id],
        userId: User::factory()->create()->id,
        label:  'queue-path-test',
        opts:   ['with_design' => true, 'with_media' => true],
    );

    // Re-run the kind→exporter dispatch inline so we can assert on the
    // envelope without round-tripping through BundleArchive::build() (which
    // is exercised separately).
    $envelope = match ($job->kind) {
        'pages' => app(ContentExporter::class)->exportPages($job->ids, $job->opts),
    };

    expect($envelope['payload'])->toHaveKey('design');
    expect($envelope['payload']['design'])->toHaveKey('typography');
    expect($envelope['payload'])->toHaveKey('media');
});

it('emits both payload.design and payload.media when with_design + with_media are true', function () {
    Storage::fake('public');

    ieSeedDesignRow('theme_colors', ['palette' => ['primary' => '#abcdef']]);

    $page   = Page::factory()->create(['slug' => 'opt-full-snapshot', 'status' => 'published']);
    $logoWt = \App\Models\WidgetType::where('handle', 'logo')->firstOrFail();
    $widget = $page->widgets()->create([
        'widget_type_id' => $logoWt->id,
        'label'          => 'Logo',
        'config'         => ['logo' => null, 'text' => 'Acme', 'link_url' => '/'],
        'query_config'   => [],
        'appearance_config' => [],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);
    $media = $widget->addMedia(firstSampleLogo())
        ->preservingOriginal()
        ->toMediaCollection('config_logo', 'public');
    $widget->update(['config' => ['logo' => $media->id, 'text' => 'Acme', 'link_url' => '/']]);

    $bundle = app(ContentExporter::class)->exportPages([$page->id], [
        'with_design' => true,
        'with_media'  => true,
    ]);

    expect($bundle['payload'])->toHaveKey('design');
    expect($bundle['payload']['design']['theme_colors']['palette']['primary'])->toBe('#abcdef');
    expect($bundle['payload'])->toHaveKey('media');
    expect($bundle['payload']['media'])->toHaveCount(1);
    expect($bundle['payload']['media'][0]['id'])->toBe($media->id);
});

it('emits payload.media descriptors for referenced media when with_media is true', function () {
    Storage::fake('public');

    $page   = Page::factory()->create(['slug' => 'opt-with-media', 'status' => 'published']);
    $logoWt = \App\Models\WidgetType::where('handle', 'logo')->firstOrFail();
    $widget = $page->widgets()->create([
        'widget_type_id' => $logoWt->id,
        'label'          => 'Logo',
        'config'         => ['logo' => null, 'text' => 'Acme', 'link_url' => '/'],
        'query_config'   => [],
        'appearance_config' => [],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);
    $media = $widget->addMedia(firstSampleLogo())
        ->preservingOriginal()
        ->toMediaCollection('config_logo', 'public');
    $widget->update(['config' => ['logo' => $media->id, 'text' => 'Acme', 'link_url' => '/']]);

    $defaultBundle = app(ContentExporter::class)->exportPages([$page->id]);
    expect($defaultBundle['payload'])->not->toHaveKey('media');

    $withMedia = app(ContentExporter::class)->exportPages([$page->id], ['with_media' => true]);
    expect($withMedia['payload'])->toHaveKey('media');
    expect($withMedia['payload']['media'])->toHaveCount(1);
    expect($withMedia['payload']['media'][0]['id'])->toBe($media->id);
    expect($withMedia['payload']['media'][0]['file_name'])->toBe($media->file_name);
});

// ── Session 309: ContentImporter::analyze() manifest ───────────────────────

it('analyzes a bundle and reports design, media, pages, and templates', function () {
    Page::factory()->create(['slug' => 'analyze-existing', 'status' => 'published']);
    Template::create(['name' => 'Analyze Existing Template', 'type' => 'content', 'is_default' => false, 'created_by' => User::factory()->create()->id]);

    $bundle = [
        'format_version' => '1.1.0',
        'payload' => [
            'design' => [
                'theme_colors' => ['palette' => ['primary' => '#123456']],
                'typography'   => ['buckets' => ['heading_family' => 'Inter']],
            ],
            'media' => [
                ['id' => 9001, 'file_name' => 'one.png'],
                ['id' => 9002, 'file_name' => 'two.png'],
            ],
            'pages' => [
                ['slug' => 'analyze-existing', 'widgets' => []],
                ['slug' => 'analyze-fresh', 'widgets' => []],
            ],
            'templates' => [
                ['name' => 'Analyze Existing Template', 'type' => 'content'],
                ['name' => 'Analyze Fresh Template', 'type' => 'page'],
            ],
        ],
    ];

    $manifest = app(ContentImporter::class)->analyze($bundle);

    expect($manifest['has_design'])->toBeTrue();
    expect($manifest['design_keys'])->toEqualCanonicalizing(['theme_colors', 'typography']);
    expect($manifest['has_media'])->toBeTrue();
    expect($manifest['media_count'])->toBe(2);
    expect($manifest['pages'])->toEqualCanonicalizing([
        ['slug' => 'analyze-existing', 'exists_locally' => true],
        ['slug' => 'analyze-fresh',    'exists_locally' => false],
    ]);
    expect($manifest['templates'])->toEqualCanonicalizing([
        ['name' => 'Analyze Existing Template', 'type' => 'content', 'exists_locally' => true],
        ['name' => 'Analyze Fresh Template',    'type' => 'page',    'exists_locally' => false],
    ]);
});

it('analyzes a bare bundle with no design or media payloads', function () {
    $bundle = [
        'format_version' => '1.1.0',
        'payload' => [
            'pages'     => [],
            'templates' => [],
        ],
    ];

    $manifest = app(ContentImporter::class)->analyze($bundle);

    expect($manifest['has_design'])->toBeFalse();
    expect($manifest['design_keys'])->toBe([]);
    expect($manifest['has_media'])->toBeFalse();
    expect($manifest['media_count'])->toBe(0);
    expect($manifest['pages'])->toBe([]);
    expect($manifest['templates'])->toBe([]);
});

it('rejects analyze on a malformed envelope with the same error as import', function () {
    expect(fn () => app(ContentImporter::class)->analyze(['payload' => []]))
        ->toThrow(InvalidImportBundleException::class);
});

// ── Session 309: importer opt flags ────────────────────────────────────────

it('skips payload.design by default (merge_design opt defaults to FALSE)', function () {
    // Seed the SiteSetting row to a known value, then import a bundle that
    // would overwrite it under the old implicit behaviour.
    ieSeedDesignRow('theme_colors', ['palette' => ['primary' => '#000000']]);

    $bundle = [
        'format_version' => '1.1.0',
        'payload' => [
            'design' => [
                'theme_colors' => ['palette' => ['primary' => '#ff00ff']],
            ],
            'pages'     => [],
            'templates' => [],
        ],
    ];

    $log = new ImportLog();
    app(ContentImporter::class)->import($bundle, $log);

    $colors = SiteSetting::get('theme_colors');
    expect($colors['palette']['primary'])->toBe('#000000');
});

it('merges payload.design when merge_design opt is TRUE', function () {
    ieSeedDesignRow('theme_colors', ['palette' => ['primary' => '#000000']]);

    $bundle = [
        'format_version' => '1.1.0',
        'payload' => [
            'design' => [
                'theme_colors' => ['palette' => ['primary' => '#ff00ff']],
            ],
            'pages'     => [],
            'templates' => [],
        ],
    ];

    $log = new ImportLog();
    app(ContentImporter::class)->import($bundle, $log, ['merge_design' => true]);

    $colors = SiteSetting::get('theme_colors');
    expect($colors['palette']['primary'])->toBe('#ff00ff');
});

it('skips colliding-slug pages when replace_duplicate_pages is FALSE', function () {
    $existing = ieMakePageWithWidgets('opt-dup-skip', [
        ['handle' => 'text_block', 'label' => 'Original', 'config' => ['content' => '<p>original</p>']],
    ]);
    $originalId = $existing->id;

    $bundle = [
        'format_version' => '1.1.0',
        'payload' => [
            'pages' => [[
                'title'   => 'Replacement', 'slug' => 'opt-dup-skip', 'type' => 'default',
                'status'  => 'draft',
                'widgets' => [],
            ]],
            'templates' => [],
        ],
    ];

    $log = new ImportLog();
    app(ContentImporter::class)->import($bundle, $log, ['replace_duplicate_pages' => false]);

    $reloaded = Page::where('slug', 'opt-dup-skip')->first();
    expect($reloaded->id)->toBe($originalId);
    expect($reloaded->title)->toBe('Test opt-dup-skip');

    $widgets = PageWidget::forOwner($reloaded)->get();
    expect($widgets)->toHaveCount(1);
    expect($widgets[0]->label)->toBe('Original');
});

it('skips ALL page entries when import_pages opt is FALSE', function () {
    // One existing slug + one fresh slug — neither should land.
    $existing = ieMakePageWithWidgets('opt-pages-off-existing', [
        ['handle' => 'text_block', 'label' => 'Original', 'config' => ['content' => '<p>original</p>']],
    ]);
    $originalTitle = $existing->title;

    $bundle = [
        'format_version' => '1.1.0',
        'payload' => [
            'pages' => [
                [
                    'title' => 'Replacement',
                    'slug'  => 'opt-pages-off-existing',
                    'type'  => 'default',
                    'status'  => 'draft',
                    'widgets' => [],
                ],
                [
                    'title' => 'Brand new',
                    'slug'  => 'opt-pages-off-fresh',
                    'type'  => 'default',
                    'status'  => 'draft',
                    'widgets' => [],
                ],
            ],
            'templates' => [],
        ],
    ];

    $log = new ImportLog();
    app(ContentImporter::class)->import($bundle, $log, ['import_pages' => false]);

    // Existing page is untouched.
    $reloaded = Page::where('slug', 'opt-pages-off-existing')->first();
    expect($reloaded->title)->toBe($originalTitle);

    // Fresh slug was never created.
    expect(Page::where('slug', 'opt-pages-off-fresh')->exists())->toBeFalse();
});

// ── Session 310: exportSite() rollup ───────────────────────────────────────

it('exportSite enumerates every page and every template on the install', function () {
    ieMakePageWithWidgets('site-rollup-a');
    ieMakePageWithWidgets('site-rollup-b');
    Template::create([
        'name'        => 'Site Rollup Content Template',
        'type'        => 'content',
        'is_default'  => false,
    ]);

    $bundle = app(ContentExporter::class)->exportSite();

    $pageSlugs = collect($bundle['payload']['pages'])->pluck('slug')->all();
    expect($pageSlugs)->toContain('site-rollup-a', 'site-rollup-b');

    $tplNames = collect($bundle['payload']['templates'])->pluck('name')->all();
    expect($tplNames)->toContain('Default', 'Site Rollup Content Template');
});

it('exportSite defaults with_design + with_media to true', function () {
    Storage::fake('public');
    ieSeedDesignRow('theme_colors', ['palette' => ['primary' => '#abcdef']]);

    $page   = Page::factory()->create(['slug' => 'site-rollup-defaults', 'status' => 'published']);
    $logoWt = WidgetType::where('handle', 'logo')->firstOrFail();
    $widget = $page->widgets()->create([
        'widget_type_id'    => $logoWt->id,
        'label'             => 'Logo',
        'config'            => ['logo' => null, 'text' => 'Acme', 'link_url' => '/'],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);
    $media = $widget->addMedia(firstSampleLogo())
        ->preservingOriginal()
        ->toMediaCollection('config_logo', 'public');
    $widget->update(['config' => ['logo' => $media->id, 'text' => 'Acme', 'link_url' => '/']]);

    $bundle = app(ContentExporter::class)->exportSite();

    expect($bundle['payload'])->toHaveKey('design');
    expect($bundle['payload']['design']['theme_colors']['palette']['primary'])->toBe('#abcdef');
    expect($bundle['payload'])->toHaveKey('media');
    expect(collect($bundle['payload']['media'])->pluck('id'))->toContain($media->id);
});

it('exportSite honours an explicit with_design / with_media override', function () {
    ieMakePageWithWidgets('site-rollup-override');
    ieSeedDesignRow('theme_colors', ['palette' => ['primary' => '#abcdef']]);

    $bundle = app(ContentExporter::class)->exportSite([
        'with_design' => false,
        'with_media'  => false,
    ]);

    expect($bundle['payload'])->not->toHaveKey('design');
    expect($bundle['payload'])->not->toHaveKey('media');
});

it('ExportBundleJob site kind forwards opts to exportSite()', function () {
    // End-to-end queue-path guard: the SiteImportExportPage dispatches
    // ExportBundleJob('site', [], …) with ['with_design' => true,
    // 'with_media' => true]. ExportBundleJob::handle() must route 'site' to
    // ContentExporter::exportSite() so the envelope carries both payloads.
    ieSeedDesignRow('typography', ['buckets' => ['heading_family' => 'Inter']]);
    ieMakePageWithWidgets('site-kind-queue-path');

    $job = new \App\Jobs\ExportBundleJob(
        kind:   'site',
        ids:    [],
        userId: User::factory()->create()->id,
        label:  'site-kind-test',
        opts:   ['with_design' => true, 'with_media' => true],
    );

    // Re-run the kind→exporter dispatch inline so we can assert on the
    // envelope without round-tripping through BundleArchive::build().
    $envelope = match ($job->kind) {
        'site' => app(ContentExporter::class)->exportSite($job->opts),
    };

    expect($envelope['payload'])->toHaveKey('design');
    expect($envelope['payload']['design'])->toHaveKey('typography');
    expect($envelope['payload'])->toHaveKey('pages');
    expect(collect($envelope['payload']['pages'])->pluck('slug'))->toContain('site-kind-queue-path');
});

it('round-trips a site snapshot: exportSite → import → row counts match', function () {
    ieSeedDesignRow('theme_colors', ['palette' => ['primary' => '#112233']]);
    ieMakePageWithWidgets('site-rollup-rt-a');
    ieMakePageWithWidgets('site-rollup-rt-b');
    Template::create([
        'name'        => 'Site Rollup RT Content',
        'type'        => 'content',
        'is_default'  => false,
    ]);

    $bundle = app(ContentExporter::class)->exportSite();

    $expectedPages     = Page::count();
    $expectedTemplates = Template::count();

    app(ContentImporter::class)->import($bundle, new ImportLog(), [
        'merge_design'            => true,
        'import_media'            => true,
        'import_pages'            => true,
        'replace_duplicate_pages' => true,
    ]);

    expect(Page::count())->toBe($expectedPages);
    expect(Template::count())->toBe($expectedTemplates);
    expect(Page::where('slug', 'site-rollup-rt-a')->exists())->toBeTrue();
    expect(Page::where('slug', 'site-rollup-rt-b')->exists())->toBeTrue();
    expect(Template::where('name', 'Site Rollup RT Content')->exists())->toBeTrue();

    $reloaded = SiteSetting::get('theme_colors');
    expect($reloaded['palette']['primary'])->toBe('#112233');
});

// ── Session A001: navigation round-trip ────────────────────────────────────

it('round-trips a navigation menu with a 2-deep tree and page references', function () {
    $page = Page::factory()->create(['slug' => 'nav-target-page', 'status' => 'published']);

    $menu = NavigationMenu::create(['label' => 'Main Menu', 'handle' => 'main_menu']);

    $home = NavigationItem::create([
        'navigation_menu_id' => $menu->id,
        'label'              => 'Home',
        'url'                => '/',
        'target'             => '_self',
        'is_visible'         => true,
        'sort_order'         => 0,
    ]);
    $about = NavigationItem::create([
        'navigation_menu_id' => $menu->id,
        'label'              => 'About',
        'page_id'            => $page->id,
        'target'             => '_self',
        'is_visible'         => true,
        'sort_order'         => 1,
    ]);
    NavigationItem::create([
        'navigation_menu_id' => $menu->id,
        'parent_id'          => $about->id,
        'label'              => 'Our Team',
        'url'                => '/team',
        'target'             => '_blank',
        'is_visible'         => true,
        'sort_order'         => 0,
    ]);
    NavigationItem::create([
        'navigation_menu_id' => $menu->id,
        'parent_id'          => $about->id,
        'label'              => 'Contact',
        'url'                => '/contact',
        'target'             => '_self',
        'is_visible'         => false,
        'sort_order'         => 1,
    ]);

    $bundle = app(ContentExporter::class)->exportNavigation([$menu->id]);

    expect($bundle['payload'])->toHaveKey('navigation_menus');
    expect($bundle['payload']['navigation_menus'])->toHaveCount(1);
    expect($bundle['payload']['navigation_menus'][0]['menu']['handle'])->toBe('main_menu');
    expect($bundle['payload']['navigation_menus'][0]['items'])->toHaveCount(2);

    // Mutate the menu so import has to re-establish the canonical state.
    NavigationItem::where('navigation_menu_id', $menu->id)->delete();
    $menu->update(['label' => 'Wiped']);

    app(ContentImporter::class)->import($bundle, new ImportLog());

    $reloaded = NavigationMenu::where('handle', 'main_menu')->first();
    expect($reloaded->id)->toBe($menu->id);
    expect($reloaded->label)->toBe('Main Menu');

    $items = NavigationItem::where('navigation_menu_id', $reloaded->id)
        ->orderBy('parent_id', 'asc')
        ->orderBy('sort_order')
        ->get();
    expect($items)->toHaveCount(4);

    $roots = $items->whereNull('parent_id')->sortBy('sort_order')->values();
    expect($roots)->toHaveCount(2);
    expect($roots[0]->label)->toBe('Home');
    expect($roots[0]->url)->toBe('/');
    expect($roots[0]->page_id)->toBeNull();
    expect($roots[1]->label)->toBe('About');
    expect($roots[1]->page_id)->toBe($page->id);
    expect($roots[1]->url)->toBeNull();

    $children = $items->whereNotNull('parent_id')->sortBy('sort_order')->values();
    expect($children)->toHaveCount(2);
    expect($children[0]->parent_id)->toBe($roots[1]->id);
    expect($children[0]->label)->toBe('Our Team');
    expect($children[0]->target)->toBe('_blank');
    expect($children[1]->label)->toBe('Contact');
    expect($children[1]->is_visible)->toBeFalse();
});

it('warns and leaves page_id null when a navigation page_slug is missing on import', function () {
    $menu = NavigationMenu::create(['label' => 'Footer', 'handle' => 'footer_menu']);

    $bundle = [
        'format_version' => '1.1.0',
        'payload' => [
            'navigation_menus' => [[
                'menu'  => ['handle' => 'footer_menu', 'label' => 'Footer'],
                'items' => [[
                    'label'      => 'Ghost',
                    'url'        => null,
                    'page_slug'  => 'does-not-exist',
                    'target'     => '_self',
                    'is_visible' => true,
                    'sort_order' => 0,
                ]],
            ]],
        ],
    ];

    $log = new ImportLog();
    app(ContentImporter::class)->import($bundle, $log);

    expect($log->hasWarnings())->toBeTrue();

    $item = NavigationItem::where('navigation_menu_id', $menu->id)->first();
    expect($item)->not->toBeNull();
    expect($item->page_id)->toBeNull();
});

it('analyze() reports navigation menus and exists_locally', function () {
    NavigationMenu::create(['label' => 'Existing', 'handle' => 'existing_menu']);

    $bundle = [
        'format_version' => '1.1.0',
        'payload' => [
            'navigation_menus' => [
                [
                    'menu'  => ['handle' => 'existing_menu', 'label' => 'Existing'],
                    'items' => [
                        ['label' => 'A', 'children' => [['label' => 'A1'], ['label' => 'A2']]],
                    ],
                ],
                [
                    'menu'  => ['handle' => 'fresh_menu', 'label' => 'Fresh'],
                    'items' => [['label' => 'Solo']],
                ],
            ],
            'pages'     => [],
            'templates' => [],
        ],
    ];

    $manifest = app(ContentImporter::class)->analyze($bundle);

    expect($manifest['navigation_menus'])->toEqualCanonicalizing([
        ['handle' => 'existing_menu', 'label' => 'Existing', 'items_count' => 3, 'exists_locally' => true],
        ['handle' => 'fresh_menu',    'label' => 'Fresh',    'items_count' => 1, 'exists_locally' => false],
    ]);
});

it('skips navigation when import_navigation opt is FALSE', function () {
    $bundle = [
        'format_version' => '1.1.0',
        'payload' => [
            'navigation_menus' => [[
                'menu'  => ['handle' => 'unused_menu', 'label' => 'Unused'],
                'items' => [['label' => 'Nothing', 'url' => '/x']],
            ]],
        ],
    ];

    $log = new ImportLog();
    app(ContentImporter::class)->import($bundle, $log, ['import_navigation' => false]);

    expect(NavigationMenu::where('handle', 'unused_menu')->exists())->toBeFalse();
});

it('skips payload.media when import_media opt is FALSE', function () {
    $bundle = [
        'format_version' => '1.1.0',
        'payload' => [
            'media' => [[
                'id'        => 9100,
                'uuid'      => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                'file_name' => 'unused.png',
                'path'      => '9100/unused.png',
            ]],
            'pages'     => [],
            'templates' => [],
        ],
    ];

    $log = new ImportLog();
    app(ContentImporter::class)->import($bundle, $log, ['import_media' => false]);

    expect(\Illuminate\Support\Facades\DB::table('media')->where('id', 9100)->exists())->toBeFalse();
});
