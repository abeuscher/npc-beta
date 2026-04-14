<?php

use App\Models\Collection;
use App\Models\Page;
use App\Models\PageLayout;
use App\Models\PageWidget;
use App\Models\Template;
use App\Models\User;
use App\Models\WidgetType;
use App\Services\ImportExport\ContentExporter;
use App\Services\ImportExport\ContentImporter;
use App\Services\ImportExport\ImportLog;
use App\Services\ImportExport\InvalidImportBundleException;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        PageWidget::create([
            'page_id'        => $page->id,
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

// ── Round-trip: text widgets only ───────────────────────────────────────────

it('round-trips a page with text widgets only', function () {
    $page = ieMakePageWithWidgets('round-trip-text', [
        ['handle' => 'text_block', 'label' => 'Block A', 'config' => ['content' => '<p>Hello A</p>']],
        ['handle' => 'text_block', 'label' => 'Block B', 'config' => ['content' => '<p>Hello B</p>']],
    ]);

    $bundle = app(ContentExporter::class)->exportPages([$page->id]);

    // Wipe widgets but keep the page row to verify overwrite path
    PageWidget::where('page_id', $page->id)->delete();
    $page->update(['title' => 'Wiped', 'meta_title' => null]);

    app(ContentImporter::class)->import($bundle, new ImportLog());

    $reloaded = Page::where('slug', 'round-trip-text')->first();
    expect($reloaded->id)->toBe($page->id); // id preserved
    expect($reloaded->title)->toBe('Test round-trip-text');

    $widgets = PageWidget::where('page_id', $reloaded->id)->orderBy('sort_order')->get();
    expect($widgets)->toHaveCount(2);
    expect($widgets[0]->label)->toBe('Block A');
    expect($widgets[0]->config['content'])->toBe('<p>Hello A</p>');
    expect($widgets[1]->label)->toBe('Block B');
});

// ── Round-trip: column layout ───────────────────────────────────────────────

it('round-trips a page with a column layout containing widgets', function () {
    $page = Page::factory()->create(['slug' => 'round-trip-layout', 'status' => 'published']);

    $layout = PageLayout::create([
        'page_id'       => $page->id,
        'label'         => 'Two-Col',
        'display'       => 'grid',
        'columns'       => 2,
        'layout_config' => ['grid_template_columns' => '1fr 1fr', 'gap' => '1rem'],
        'sort_order'    => 0,
    ]);

    $textWt = WidgetType::where('handle', 'text_block')->first();

    PageWidget::create([
        'page_id' => $page->id, 'layout_id' => $layout->id, 'column_index' => 0,
        'widget_type_id' => $textWt->id, 'label' => 'Left',
        'config' => ['content' => '<p>Left col</p>'],
        'query_config' => [], 'appearance_config' => [], 'sort_order' => 0, 'is_active' => true,
    ]);
    PageWidget::create([
        'page_id' => $page->id, 'layout_id' => $layout->id, 'column_index' => 1,
        'widget_type_id' => $textWt->id, 'label' => 'Right',
        'config' => ['content' => '<p>Right col</p>'],
        'query_config' => [], 'appearance_config' => [], 'sort_order' => 0, 'is_active' => true,
    ]);

    $bundle = app(ContentExporter::class)->exportPages([$page->id]);

    PageWidget::where('page_id', $page->id)->delete();
    PageLayout::where('page_id', $page->id)->delete();

    app(ContentImporter::class)->import($bundle, new ImportLog());

    $layouts = PageLayout::where('page_id', $page->id)->get();
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

// ── Round-trip: media reference (Tier 1 rewiring) ───────────────────────────

it('round-trips a page with a logo widget media reference', function () {
    Storage::fake('public');

    $page = Page::factory()->create(['slug' => 'round-trip-media', 'status' => 'published']);
    $logoWt = WidgetType::where('handle', 'logo')->firstOrFail();

    $widget = PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $logoWt->id,
        'label'          => 'Site Logo',
        'config'         => ['logo' => null, 'text' => 'Acme', 'link_url' => '/'],
        'query_config'   => [],
        'appearance_config' => [],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $media = $widget->addMedia(resource_path('sample-images/logos/logo-adidas.png'))
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
    expect($exportedWidget['media'][0]['file_name'])->toBe('logo-adidas.png');

    // Wipe the widget (DB row only — file stays on disk because Storage::fake doesn't auto-delete)
    PageWidget::where('page_id', $page->id)->delete();

    $log = new ImportLog();
    app(ContentImporter::class)->import($bundle, $log);

    expect($log->hasWarnings())->toBeFalse();

    $reimported = PageWidget::where('page_id', $page->id)->first();
    expect($reimported)->not->toBeNull();
    expect($reimported->config['text'])->toBe('Acme');

    $reimportedMedia = $reimported->getFirstMedia('config_logo');
    expect($reimportedMedia)->not->toBeNull();
    expect($reimportedMedia->file_name)->toBe('logo-adidas.png');
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
    PageWidget::whereIn('page_id', [$p1->id, $p2->id, $p3->id])->delete();

    app(ContentImporter::class)->import($bundle, new ImportLog());

    foreach (['bulk-1' => '<p>P1</p>', 'bulk-2' => '<p>P2</p>', 'bulk-3' => '<p>P3</p>'] as $slug => $expected) {
        $page    = Page::where('slug', $slug)->first();
        $widget  = PageWidget::where('page_id', $page->id)->first();
        expect($widget->config['content'])->toBe($expected);
    }
});

// ── Round-trip: page template chrome (the migrate:fresh case) ───────────────

it('round-trips a page template with customised chrome and header/footer pages', function () {
    // Build a customised page template with its own header/footer system pages.
    $template = Template::create([
        'name'             => 'Custom Chrome',
        'type'             => 'page',
        'primary_color'    => '#bada55',
        'header_bg_color'  => '#000000',
        'footer_bg_color'  => '#222222',
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
    PageWidget::create([
        'page_id' => $headerPage->id, 'widget_type_id' => $logoWt->id,
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
    PageWidget::create([
        'page_id' => $footerPage->id, 'widget_type_id' => $textWt->id,
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
    PageWidget::whereIn('page_id', [$headerPage->id, $footerPage->id])->delete();
    Page::whereIn('id', [$headerPage->id, $footerPage->id])->forceDelete();
    $template->update(['header_page_id' => null, 'footer_page_id' => null, 'primary_color' => null]);

    app(ContentImporter::class)->import($bundle, new ImportLog());

    $reloadedTemplate = Template::where('name', 'Custom Chrome')->first();
    expect($reloadedTemplate->primary_color)->toBe('#bada55');
    expect($reloadedTemplate->custom_scss)->toBe('.custom { color: red; }');
    expect($reloadedTemplate->header_page_id)->not->toBeNull();
    expect($reloadedTemplate->footer_page_id)->not->toBeNull();

    $reloadedHeader = Page::find($reloadedTemplate->header_page_id);
    expect($reloadedHeader->slug)->toBe('_header_custom_chrome');
    $headerWidget = PageWidget::where('page_id', $reloadedHeader->id)->first();
    expect($headerWidget->config['text'])->toBe('Brand');

    $reloadedFooter = Page::find($reloadedTemplate->footer_page_id);
    $footerWidget   = PageWidget::where('page_id', $reloadedFooter->id)->first();
    expect($footerWidget->config['content'])->toBe('<p>© 2026 Acme</p>');
});

// ── Slug collision: re-import overwrites in place ───────────────────────────

it('overwrites a page with a colliding slug while preserving its id', function () {
    $page = ieMakePageWithWidgets('collision', [
        ['handle' => 'text_block', 'label' => 'Original', 'config' => ['content' => '<p>v1</p>']],
    ]);
    $originalId = $page->id;

    $bundle = app(ContentExporter::class)->exportPages([$page->id]);

    // Mutate the existing page to verify it gets reset
    PageWidget::where('page_id', $page->id)->delete();
    PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => WidgetType::where('handle', 'text_block')->first()->id,
        'label'          => 'Stale',
        'config'         => ['content' => '<p>STALE</p>'],
        'query_config'   => [], 'appearance_config' => [], 'sort_order' => 0, 'is_active' => true,
    ]);

    app(ContentImporter::class)->import($bundle, new ImportLog());

    $reloaded = Page::where('slug', 'collision')->first();
    expect($reloaded->id)->toBe($originalId);

    $widgets = PageWidget::where('page_id', $reloaded->id)->get();
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

    $widgets = PageWidget::where('page_id', $reloaded->id)->get();
    expect($widgets)->toHaveCount(1);
    expect($widgets[0]->label)->toBe('Original');
});

// ── Fallback: missing collection handle ─────────────────────────────────────

it('clears collection_handle when the referenced collection does not exist', function () {
    $blogWt = WidgetType::where('handle', 'blog_listing')->firstOrFail();

    $page = Page::factory()->create(['slug' => 'missing-coll', 'status' => 'published']);
    PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $blogWt->id,
        'label'          => 'Listing',
        'config'         => ['collection_handle' => 'does_not_exist'],
        'query_config'   => [], 'appearance_config' => [], 'sort_order' => 0, 'is_active' => true,
    ]);

    $bundle = app(ContentExporter::class)->exportPages([$page->id]);

    PageWidget::where('page_id', $page->id)->delete();

    $log = new ImportLog();
    app(ContentImporter::class)->import($bundle, $log);

    $widget = PageWidget::where('page_id', $page->id)->first();
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

    $widget = PageWidget::whereHas('page', fn ($q) => $q->where('slug', 'missing-media'))->first();
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
