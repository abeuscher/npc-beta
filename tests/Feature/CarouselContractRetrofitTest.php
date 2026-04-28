<?php

use App\Models\Collection as CmsCollection;
use App\Models\CollectionItem;
use App\Models\Page;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use App\Widgets\Carousel\CarouselDefinition;
use App\WidgetPrimitive\AmbientContexts\PageAmbientContext;
use App\WidgetPrimitive\ContractResolver;
use App\WidgetPrimitive\SlotContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

// guards: Carousel whitelist (slide DTO with _media[image], caption_template token-substitution non-leak); N>=2 redundant for ContractResolver mutations per session-241 audit.
it('projects only contract-declared fields onto Carousel rows (fail-closed whitelist)', function () {
    $collection = CmsCollection::create([
        'handle'      => 'audit-slides',
        'name'        => 'Audit Slides',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [
            ['key' => 'title',       'type' => 'text'],
            ['key' => 'description', 'type' => 'text'],
            ['key' => 'image',       'type' => 'image'],
        ],
    ]);

    foreach (range(0, 2) as $i) {
        CollectionItem::create([
            'collection_id' => $collection->id,
            'sort_order'    => $i,
            'is_published'  => true,
            'data'          => [
                'title'          => "Slide $i Title",
                'description'    => "Slide $i Description",
                'internal_notes' => 'NOTES_NOTLEAKED_SENTINEL',
                'legacy_id'      => 'LEGACY_ID_SENTINEL',
            ],
        ]);
    }

    // Resolver-level invariant: contract-declared shape only.
    $resolver = app(ContractResolver::class);
    $contract = (new CarouselDefinition())->dataContract(['collection_handle' => 'audit-slides']);
    $dto = $resolver->resolve([$contract], new SlotContext(new PageAmbientContext()))[0];

    expect($dto['items'])->toHaveCount(3)
        ->and(array_keys($dto['items'][0]))->toEqualCanonicalizing(['title', 'description', '_media'])
        ->and(array_keys($dto['items'][1]))->toEqualCanonicalizing(['title', 'description', '_media'])
        ->and(array_keys($dto['items'][2]))->toEqualCanonicalizing(['title', 'description', '_media'])
        ->and($dto['items'][0])->not->toHaveKey('internal_notes')
        ->and($dto['items'][0])->not->toHaveKey('legacy_id')
        ->and($dto['items'][0]['_media'])->toHaveKey('image');

    // HTML-level invariant: sentinels do not leak through token substitution.
    $wt = WidgetType::where('handle', 'carousel')->firstOrFail();
    $host = Page::factory()->create(['title' => 'Carousel Host', 'slug' => 'carousel-host', 'status' => 'published']);
    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'collection_handle' => 'audit-slides',
            'image_field'       => 'image',
            'caption_template'  => '{{item.title}} | {{item.description}} | {{item.internal_notes}} | {{item.legacy_id}}',
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)
        ->toContain('Slide 0 Title')
        ->toContain('Slide 0 Description')
        ->toContain('Slide 1 Title')
        ->toContain('Slide 2 Title')
        ->not->toContain('NOTES_NOTLEAKED_SENTINEL')
        ->not->toContain('LEGACY_ID_SENTINEL');
});

// guards: Carousel query pattern (1 collections + 1 collection_items + 1 media, swiper-slide HTML count); N>=2 redundant for ContractResolver mutations per session-241 audit.
it('renders Carousel through the contract resolver only, with one collections + one collection_items + one media select', function () {
    $collection = CmsCollection::create([
        'handle'      => 'audit-slides',
        'name'        => 'Audit Slides',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [
            ['key' => 'title',       'type' => 'text'],
            ['key' => 'description', 'type' => 'text'],
            ['key' => 'image',       'type' => 'image'],
        ],
    ]);

    foreach (range(0, 2) as $i) {
        CollectionItem::create([
            'collection_id' => $collection->id,
            'sort_order'    => $i,
            'is_published'  => true,
            'data'          => ['title' => "Live Slide $i", 'description' => "Caption $i"],
        ]);
    }

    CollectionItem::create([
        'collection_id' => $collection->id,
        'sort_order'    => 99,
        'is_published'  => false,
        'data'          => ['title' => 'Unpublished Slide Should Not Render', 'description' => 'hidden'],
    ]);

    $wt = WidgetType::where('handle', 'carousel')->firstOrFail();
    $host = Page::factory()->create(['title' => 'Query Host', 'slug' => 'query-host', 'status' => 'published']);
    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'collection_handle' => 'audit-slides',
            'image_field'       => 'image',
            'caption_template'  => '{{item.title}}',
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    DB::enableQueryLog();
    $html = WidgetRenderer::render($pw)['html'];
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $collectionSelects = array_values(array_filter($queries, function ($q) {
        $sql = $q['query'];
        return str_starts_with($sql, 'select')
            && str_contains($sql, 'from "collections"')
            && str_contains($sql, '"handle"')
            && str_contains($sql, '"is_active"')
            && str_contains($sql, '"is_public"');
    }));

    $itemSelects = array_values(array_filter($queries, function ($q) {
        $sql = $q['query'];
        return str_starts_with($sql, 'select')
            && str_contains($sql, 'from "collection_items"')
            && str_contains($sql, '"is_published"')
            && str_contains($sql, '"sort_order"');
    }));

    $mediaSelects = array_values(array_filter($queries, fn ($q) => str_starts_with($q['query'], 'select') && str_contains($q['query'], 'from "media"')));

    expect(substr_count($html, '<div class="swiper-slide">'))->toBe(3)
        ->and($html)->not->toContain('Unpublished Slide Should Not Render')
        ->and(count($collectionSelects))->toBe(1)
        ->and(count($itemSelects))->toBe(1)
        ->and(count($mediaSelects))->toBe(1);
});
