<?php

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Widget type seeder ──────────────────────────────────────────────────────

it('seeder creates logo_garden widget type with correct config and collections', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $wt = WidgetType::where('handle', 'logo_garden')->first();

    expect($wt)->not->toBeNull()
        ->and($wt->label)->toBe('Logo Garden')
        ->and($wt->collections)->toBe(['logos'])
        ->and($wt->category)->toBe(['content', 'media']);

    $keys = collect($wt->config_schema)->pluck('key')->all();
    expect($keys)->toContain('collection_handle')
        ->toContain('image_field')
        ->toContain('display_mode')
        ->toContain('show_name')
        ->toContain('name_field')
        ->toContain('background_color')
        ->toContain('logos_per_row')
        ->toContain('logo_max_height')
        ->toContain('carousel_duration')
        ->toContain('flip_duration');
});

// ── LogoGardenDemoSeeder ────────────────────────────────────────────────────

it('logo garden demo seeder creates collection and items', function () {
    $this->artisan('db:seed', ['--class' => 'LogoGardenDemoSeeder']);

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
    expect($names)->toContain('Adidas')
        ->toContain('Google')
        ->toContain('YouTube');
});

it('logo garden demo seeder is idempotent', function () {
    $this->artisan('db:seed', ['--class' => 'LogoGardenDemoSeeder']);
    $this->artisan('db:seed', ['--class' => 'LogoGardenDemoSeeder']);

    expect(Collection::where('handle', 'logo-garden-demo')->count())->toBe(1);
    $collection = Collection::where('handle', 'logo-garden-demo')->first();
    expect(CollectionItem::where('collection_id', $collection->id)->count())->toBe(9);
});

// ── Debug generator seedWidgetCollections ───────────────────────────────────

it('seedWidgetCollections runs all three demo seeders', function () {
    $widget = new \App\Filament\Widgets\DashboardDebugGeneratorWidget();
    $widget->seedWidgetCollections();

    expect(Collection::where('handle', 'carousel-demo')->exists())->toBeTrue()
        ->and(Collection::where('handle', 'chart-demo')->exists())->toBeTrue()
        ->and(Collection::where('handle', 'logo-garden-demo')->exists())->toBeTrue();

    expect($widget->feedback)->toBe('Widget demo collections seeded (carousel, chart, logo garden, board members, products).');
});

// ── Logo garden blade template rendering ────────────────────────────────────

it('logo garden renders static grid with collection data', function () {
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
    $item->addMedia(resource_path('sample-images/logos/logo-adidas.png'))
        ->preservingOriginal()
        ->toMediaCollection('logo');

    $page = Page::factory()->create(['slug' => 'logo-test', 'status' => 'published']);
    $wt = WidgetType::where('handle', 'logo_garden')->first();

    PageWidget::create([
        'page_id'        => $page->id,
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

    $item->addMedia(resource_path('sample-images/logos/logo-google.png'))
        ->preservingOriginal()
        ->toMediaCollection('logo');

    $page = Page::factory()->create(['slug' => 'logo-carousel-test', 'status' => 'published']);
    $wt = WidgetType::where('handle', 'logo_garden')->first();

    PageWidget::create([
        'page_id'        => $page->id,
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
    $item1->addMedia(resource_path('sample-images/logos/logo-spotify.png'))
        ->preservingOriginal()
        ->toMediaCollection('logo');

    $item2 = CollectionItem::create([
        'collection_id' => $collection->id,
        'data'          => ['name' => 'Flip B'],
        'sort_order'    => 1,
        'is_published'  => true,
    ]);
    $item2->addMedia(resource_path('sample-images/logos/logo-amazon.png'))
        ->preservingOriginal()
        ->toMediaCollection('logo');

    $page = Page::factory()->create(['slug' => 'logo-flipper-test', 'status' => 'published']);
    $wt = WidgetType::where('handle', 'logo_garden')->first();

    PageWidget::create([
        'page_id'        => $page->id,
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
