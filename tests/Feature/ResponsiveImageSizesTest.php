<?php

use App\Models\Page;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

/**
 * Build an Image widget on $page (optionally inside a layout column) backed by
 * an uploaded media with seeded responsive conversions, so the <picture>
 * <source sizes="…"> path renders deterministically — media-library conversion
 * jobs don't fire inside the RefreshDatabase transaction, so we seed them.
 */
function imageWidgetWithConversions(Page $page, ?string $layoutId = null, int $columnIndex = 0): \App\Models\PageWidget
{
    $pw = $page->widgets()->create([
        'widget_type_id' => WidgetType::where('handle', 'image')->firstOrFail()->id,
        'layout_id'      => $layoutId,
        'column_index'   => $layoutId ? $columnIndex : null,
        'config'         => ['image' => 'photo.jpg'],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $media = $pw->addMedia(UploadedFile::fake()->image('photo.jpg', 1600, 1200))
        ->usingFileName('photo.jpg')
        ->toMediaCollection('config_image', 'public');

    // Seed only registered conversion names (ImageSizeProfile default
    // breakpoints) so x-picture's getUrl($name) resolves without a real job run.
    $media->generated_conversions = ['webp' => true, 'responsive-768' => true, 'responsive-1280' => true];
    $media->save();

    return $pw->fresh();
}

it('emits a derived sizes value threaded through to the picture source', function () {
    Storage::fake('public');

    $page = Page::factory()->create(['slug' => 'sizes-thread', 'status' => 'published', 'published_at' => now()->subDay()]);
    $pw   = imageWidgetWithConversions($page);

    $html = WidgetRenderer::render($pw, columnSizes: '(max-width: 768px) 100vw, 60vw')['html'];

    expect($html)
        ->toContain('<source')
        ->toContain('sizes="(max-width: 768px) 100vw, 60vw"');
});

it('falls back to a concrete 100vw sizes when no column context is supplied', function () {
    Storage::fake('public');

    $page = Page::factory()->create(['slug' => 'sizes-default', 'status' => 'published', 'published_at' => now()->subDay()]);
    $pw   = imageWidgetWithConversions($page);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)
        ->toContain('<source')
        ->toContain('sizes="100vw"');
});

it('derives per-column sizes from a 2fr/3fr grid on the public render path', function () {
    Storage::fake('public');

    $page = Page::factory()->create(['slug' => 'sizes-grid', 'status' => 'published', 'published_at' => now()->subDay()]);

    $layout = $page->layouts()->create([
        'label'         => 'Two Col',
        'display'       => 'grid',
        'columns'       => 2,
        'layout_config' => ['grid_template_columns' => '2fr 3fr', 'collapse_mobile' => true],
        'sort_order'    => 0,
    ]);

    imageWidgetWithConversions($page, $layout->id, 0); // 2fr → 40vw
    imageWidgetWithConversions($page, $layout->id, 1); // 3fr → 60vw

    $html = $this->get('/sizes-grid')->assertOk()->getContent();

    expect($html)
        ->toContain('sizes="(max-width: 768px) 100vw, 40vw"')
        ->toContain('sizes="(max-width: 768px) 100vw, 60vw"')
        ->not->toContain('sizes="100vw"');
});

it('omits the mobile-stack clause when the layout does not collapse on mobile', function () {
    Storage::fake('public');

    $page = Page::factory()->create(['slug' => 'sizes-nocollapse', 'status' => 'published', 'published_at' => now()->subDay()]);

    $layout = $page->layouts()->create([
        'label'         => 'Two Col',
        'display'       => 'grid',
        'columns'       => 2,
        'layout_config' => ['grid_template_columns' => '1fr 1fr', 'collapse_mobile' => false],
        'sort_order'    => 0,
    ]);

    imageWidgetWithConversions($page, $layout->id, 0);

    $html = $this->get('/sizes-nocollapse')->assertOk()->getContent();

    expect($html)
        ->toContain('sizes="50vw"')
        ->not->toContain('max-width: 768px');
});

it('does not derive sizes when the column track list is not plain fr units', function () {
    Storage::fake('public');

    $page = Page::factory()->create(['slug' => 'sizes-pxtrack', 'status' => 'published', 'published_at' => now()->subDay()]);

    $layout = $page->layouts()->create([
        'label'         => 'Sidebar',
        'display'       => 'grid',
        'columns'       => 2,
        'layout_config' => ['grid_template_columns' => '200px 1fr', 'collapse_mobile' => true],
        'sort_order'    => 0,
    ]);

    imageWidgetWithConversions($page, $layout->id, 1);

    $html = $this->get('/sizes-pxtrack')->assertOk()->getContent();

    expect($html)->toContain('sizes="100vw"');
});
