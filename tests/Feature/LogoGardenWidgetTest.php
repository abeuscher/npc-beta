<?php

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function sampleLogoPath(): string
{
    $files = glob(resource_path('sample-images/logos/*'));
    $files = array_values(array_filter($files, fn ($p) => is_file($p) && ! str_starts_with(basename($p), '.')));
    if (empty($files)) {
        throw new RuntimeException('No sample logos available in resources/sample-images/logos/');
    }
    return $files[0];
}

// ── Widget type seeder ──────────────────────────────────────────────────────

it('seeder creates logo_garden widget type with correct config and collections', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $wt = WidgetType::where('handle', 'logo_garden')->first();

    expect($wt)->not->toBeNull()
        ->and($wt->label)->toBe('Logo Garden')
        ->and($wt->collections)->toBe([])
        ->and($wt->category)->toBe(['content', 'media']);

    $keys = collect($wt->config_schema)->pluck('key')->all();
    expect($keys)->toContain('collection_handle')
        ->toContain('image_field')
        ->toContain('display_mode')
        ->toContain('show_name')
        ->toContain('name_field')
        ->toContain('container_background_color')
        ->not->toContain('background_color')
        ->toContain('logos_per_row')
        ->toContain('logo_max_height')
        ->toContain('carousel_duration')
        ->toContain('flip_duration')
        ->toContain('gap');

    $gapField = collect($wt->config_schema)->firstWhere('key', 'gap');
    expect($gapField['type'])->toBe('number')
        ->and($gapField['default'])->toBe(16)
        ->and($gapField['group'])->toBe('appearance');
});

// ── LogoGardenDemoSeeder ────────────────────────────────────────────────────

it('logo garden demo seeder creates collection and items', function () {
    $this->artisan('db:seed', ['--class' => 'App\\Widgets\\LogoGarden\\DemoSeeder']);

    $collection = Collection::where('handle', 'logo-garden-demo')->first();

    expect($collection)->not->toBeNull()
        ->and($collection->name)->toBe('Logo Garden Demo')
        ->and($collection->source_type)->toBe('custom')
        ->and($collection->is_public)->toBeTrue();

    $fieldKeys = collect($collection->fields)->pluck('key')->all();
    expect($fieldKeys)->toBe(['name', 'logo']);

    $items = CollectionItem::where('collection_id', $collection->id)->get();
    expect($items)->toHaveCount(9);

    $names = $items->pluck('data.name')->all();
    foreach ($names as $name) {
        expect($name)->toBeString()->not->toBeEmpty();
    }
});

it('logo garden demo seeder is idempotent', function () {
    $this->artisan('db:seed', ['--class' => 'App\\Widgets\\LogoGarden\\DemoSeeder']);
    $this->artisan('db:seed', ['--class' => 'App\\Widgets\\LogoGarden\\DemoSeeder']);

    expect(Collection::where('handle', 'logo-garden-demo')->count())->toBe(1);
    $collection = Collection::where('handle', 'logo-garden-demo')->first();
    expect(CollectionItem::where('collection_id', $collection->id)->count())->toBe(9);
});

// ── Debug generator seedWidgetCollections ───────────────────────────────────

it('seedWidgetCollections runs every widget demo seeder', function () {
    $widget = new \App\Filament\Widgets\DashboardDebugGeneratorWidget();
    $widget->seedWidgetCollections();

    expect(Collection::where('handle', 'carousel-demo')->exists())->toBeTrue()
        ->and(Collection::where('handle', 'chart-demo')->exists())->toBeTrue()
        ->and(Collection::where('handle', 'logo-garden-demo')->exists())->toBeTrue()
        ->and(Collection::where('handle', 'board-members-demo')->exists())->toBeTrue();

    expect($widget->feedback)->toStartWith('Widget demo collections seeded:');
});

// ── Logo garden blade template rendering ────────────────────────────────────

it('logo garden renders static grid with collection data', function () {
    Storage::fake('public');
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $collection = Collection::create([
        'name'        => 'Test Logos',
        'handle'      => 'test-logos',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [
            ['key' => 'name', 'type' => 'text',  'label' => 'Name'],
            ['key' => 'logo', 'type' => 'image', 'label' => 'Logo'],
        ],
    ]);

    $item = CollectionItem::create([
        'collection_id' => $collection->id,
        'data'          => ['name' => 'Acme Corp'],
        'sort_order'    => 0,
        'is_published'  => true,
    ]);

    // Attach a test image
    $item->addMedia(sampleLogoPath())
        ->preservingOriginal()
        ->toMediaCollection('logo');

    $page = Page::factory()->create(['slug' => 'logo-test', 'status' => 'published']);
    $wt = WidgetType::where('handle', 'logo_garden')->first();

    $page->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'collection_handle' => 'test-logos',
            'image_field'       => 'logo',
            'display_mode'      => 'static',
            'show_name'         => true,
            'name_field'        => 'name',
            'logos_per_row'     => 3,
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $response = $this->get('/logo-test');

    $response->assertOk();
    $response->assertSee('widget-logo-garden--static', false);
    $response->assertSee('Acme Corp');
    $response->assertSee('logo-garden__cell', false);
});

it('logo garden renders carousel mode markup', function () {
    Storage::fake('public');
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $collection = Collection::create([
        'name'        => 'Carousel Logos',
        'handle'      => 'carousel-logos',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [
            ['key' => 'name', 'type' => 'text',  'label' => 'Name'],
            ['key' => 'logo', 'type' => 'image', 'label' => 'Logo'],
        ],
    ]);

    $item = CollectionItem::create([
        'collection_id' => $collection->id,
        'data'          => ['name' => 'Test Co'],
        'sort_order'    => 0,
        'is_published'  => true,
    ]);

    $item->addMedia(sampleLogoPath())
        ->preservingOriginal()
        ->toMediaCollection('logo');

    $page = Page::factory()->create(['slug' => 'logo-carousel-test', 'status' => 'published']);
    $wt = WidgetType::where('handle', 'logo_garden')->first();

    $page->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'collection_handle' => 'carousel-logos',
            'image_field'       => 'logo',
            'display_mode'      => 'carousel',
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $response = $this->get('/logo-carousel-test');

    $response->assertOk();
    $response->assertSee('widget-logo-garden--carousel', false);
    $response->assertSee('swiper', false);
});

it('logo garden renders flipper mode markup', function () {
    Storage::fake('public');
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $collection = Collection::create([
        'name'        => 'Flipper Logos',
        'handle'      => 'flipper-logos',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [
            ['key' => 'name', 'type' => 'text',  'label' => 'Name'],
            ['key' => 'logo', 'type' => 'image', 'label' => 'Logo'],
        ],
    ]);

    $item1 = CollectionItem::create([
        'collection_id' => $collection->id,
        'data'          => ['name' => 'Flip A'],
        'sort_order'    => 0,
        'is_published'  => true,
    ]);
    $item1->addMedia(sampleLogoPath())
        ->preservingOriginal()
        ->toMediaCollection('logo');

    $item2 = CollectionItem::create([
        'collection_id' => $collection->id,
        'data'          => ['name' => 'Flip B'],
        'sort_order'    => 1,
        'is_published'  => true,
    ]);
    $item2->addMedia(sampleLogoPath())
        ->preservingOriginal()
        ->toMediaCollection('logo');

    $page = Page::factory()->create(['slug' => 'logo-flipper-test', 'status' => 'published']);
    $wt = WidgetType::where('handle', 'logo_garden')->first();

    $page->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'collection_handle' => 'flipper-logos',
            'image_field'       => 'logo',
            'display_mode'      => 'flipper',
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $response = $this->get('/logo-flipper-test');

    $response->assertOk();
    $response->assertSee('widget-logo-garden--flipper', false);
    $response->assertSee('logo-garden__flip-container', false);
    $response->assertSee('logo-garden__flipper', false);
});

// ── Gap config field — logo garden ─────────────────────────────────────────

it('logo garden carousel renders default spaceBetween when gap is not set', function () {
    Storage::fake('public');
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $collection = Collection::create([
        'name'        => 'Gap Default Logos',
        'handle'      => 'gap-default-logos',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [
            ['key' => 'name', 'type' => 'text',  'label' => 'Name'],
            ['key' => 'logo', 'type' => 'image', 'label' => 'Logo'],
        ],
    ]);

    $item = CollectionItem::create([
        'collection_id' => $collection->id,
        'data'          => ['name' => 'Default Gap Co'],
        'sort_order'    => 0,
        'is_published'  => true,
    ]);
    $item->addMedia(sampleLogoPath())
        ->preservingOriginal()
        ->toMediaCollection('logo');

    $page = Page::factory()->create(['slug' => 'logo-gap-default', 'status' => 'published']);
    $wt = WidgetType::where('handle', 'logo_garden')->first();

    $page->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'collection_handle' => 'gap-default-logos',
            'image_field'       => 'logo',
            'display_mode'      => 'carousel',
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $response = $this->get('/logo-gap-default');

    $response->assertOk();
    $response->assertSee('spaceBetween: 16', false);
});

it('logo garden carousel renders custom spaceBetween from gap config', function () {
    Storage::fake('public');
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $collection = Collection::create([
        'name'        => 'Gap Custom Logos',
        'handle'      => 'gap-custom-logos',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [
            ['key' => 'name', 'type' => 'text',  'label' => 'Name'],
            ['key' => 'logo', 'type' => 'image', 'label' => 'Logo'],
        ],
    ]);

    $item = CollectionItem::create([
        'collection_id' => $collection->id,
        'data'          => ['name' => 'Custom Gap Co'],
        'sort_order'    => 0,
        'is_published'  => true,
    ]);
    $item->addMedia(sampleLogoPath())
        ->preservingOriginal()
        ->toMediaCollection('logo');

    $page = Page::factory()->create(['slug' => 'logo-gap-custom', 'status' => 'published']);
    $wt = WidgetType::where('handle', 'logo_garden')->first();

    $page->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'collection_handle' => 'gap-custom-logos',
            'image_field'       => 'logo',
            'display_mode'      => 'carousel',
            'gap'               => 48,
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $response = $this->get('/logo-gap-custom');

    $response->assertOk();
    $response->assertSee('spaceBetween: 48', false);
});
