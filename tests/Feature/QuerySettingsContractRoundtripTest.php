<?php

use App\Models\Collection as CmsCollection;
use App\Models\CollectionItem;
use App\Models\Event;
use App\Models\Page;
use App\Models\Tag;
use App\Models\WidgetType;
use App\Services\PageContext;
use App\Services\WidgetRenderer;
use App\Widgets\BarChart\BarChartDefinition;
use App\Widgets\BlogListing\BlogListingDefinition;
use App\Widgets\BoardMembers\BoardMembersDefinition;
use App\Widgets\Carousel\CarouselDefinition;
use App\Widgets\EventsListing\EventsListingDefinition;
use App\WidgetPrimitive\ContractResolver;
use App\WidgetPrimitive\SlotContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

function buildSwctCollection(string $handle, int $count = 5): CmsCollection
{
    $collection = CmsCollection::create([
        'handle'      => $handle,
        'name'        => "Audit $handle",
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [
            ['key' => 'title',       'type' => 'text'],
            ['key' => 'description', 'type' => 'text'],
            ['key' => 'image',       'type' => 'image'],
        ],
    ]);

    foreach (range(0, $count - 1) as $i) {
        CollectionItem::create([
            'collection_id' => $collection->id,
            'sort_order'    => $i,
            'is_published'  => true,
            'data'          => ['title' => "Slide $i", 'description' => "Caption $i"],
        ]);
    }

    return $collection;
}

function carouselWidgetWithQueryConfig(string $handle, array $queryConfig): \App\Models\PageWidget
{
    $wt = WidgetType::where('handle', 'carousel')->firstOrFail();
    $host = Page::factory()->create([
        'title'  => 'Host ' . uniqid(),
        'slug'   => 'host-' . uniqid(),
        'status' => 'published',
    ]);

    return $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'collection_handle' => $handle,
            'image_field'       => 'image',
            'caption_template'  => '{{item.title}}',
        ],
        'query_config'   => $queryConfig,
        'sort_order'     => 0,
        'is_active'      => true,
    ]);
}

function resolveContract(\App\WidgetPrimitive\DataContract $contract, array $userQueryConfig = []): array
{
    $merged = array_merge(
        $contract->filters,
        array_intersect_key($userQueryConfig, array_flip(['limit', 'order_by', 'direction', 'include_tags', 'exclude_tags']))
    );

    $contractWithUser = new \App\WidgetPrimitive\DataContract(
        version: $contract->version,
        source: $contract->source,
        fields: $contract->fields,
        filters: $merged,
        model: $contract->model,
        resourceHandle: $contract->resourceHandle,
        contentType: $contract->contentType,
        querySettings: $contract->querySettings,
    );

    return app(ContractResolver::class)->resolve([$contractWithUser], new SlotContext(new PageContext()))[0];
}

it('flows limit through query_config into the SWCT contract resolver', function () {
    buildSwctCollection('limit-test', 5);
    $pw = carouselWidgetWithQueryConfig('limit-test', ['limit' => 3]);

    $html = WidgetRenderer::render($pw)['html'];

    expect(substr_count($html, '<div class="swiper-slide">'))->toBe(3);
});

it('flows limit through query_config into the post contract resolver', function () {
    foreach (range(0, 4) as $i) {
        Page::factory()->create([
            'type'         => 'post',
            'title'        => "Limit Post $i",
            'slug'         => "limit-post-$i",
            'status'       => 'published',
            'published_at' => now()->subDays($i + 1),
        ]);
    }

    $wt = WidgetType::where('handle', 'blog_listing')->firstOrFail();
    $host = Page::factory()->create(['title' => 'Limit Host', 'slug' => 'limit-host', 'status' => 'published']);
    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'heading'          => '',
            'content_template' => '<article class="card">{{item.title}}</article>',
            'columns'          => 1,
            'items_per_page'   => 10,
            'show_search'      => false,
            'sort_default'     => 'newest',
        ],
        'query_config'   => ['limit' => 2],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $html = WidgetRenderer::render($pw)['html'];

    expect(substr_count($html, '<article class="card">'))->toBe(2);
});

it('flows limit through query_config into the event contract resolver', function () {
    foreach (range(0, 4) as $i) {
        Event::factory()->create([
            'title'     => "Limit Event $i",
            'slug'      => "limit-event-$i",
            'status'    => 'published',
            'starts_at' => now()->addDays($i + 1),
        ]);
    }

    $wt = WidgetType::where('handle', 'events_listing')->firstOrFail();
    $host = Page::factory()->create(['title' => 'Event Limit Host', 'slug' => 'event-limit-host', 'status' => 'published']);
    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'heading'          => '',
            'content_template' => '<article class="card">{{item.title}}</article>',
            'columns'          => 1,
            'items_per_page'   => 10,
            'show_search'      => false,
            'sort_default'     => 'soonest',
        ],
        'query_config'   => ['limit' => 2],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $html = WidgetRenderer::render($pw)['html'];

    expect(substr_count($html, '<article class="card">'))->toBe(2);
});

it('honors order_by from query_config (BoardMembers by mapped name field)', function () {
    $collection = CmsCollection::create([
        'handle'      => 'name-order',
        'name'        => 'Name Order',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [
            ['key' => 'name', 'type' => 'text'],
        ],
    ]);

    foreach (['Charlie', 'Alpha', 'Bravo'] as $i => $name) {
        CollectionItem::create([
            'collection_id' => $collection->id,
            'sort_order'    => $i,
            'is_published'  => true,
            'data'          => ['name' => $name],
        ]);
    }

    $contract = (new BoardMembersDefinition())->dataContract([
        'collection_handle' => 'name-order',
        'image_field'       => '',
        'name_field'        => 'name',
    ]);

    $dto = resolveContract($contract, ['order_by' => 'name', 'direction' => 'asc']);

    expect(array_column($dto['items'], 'name'))->toBe(['Alpha', 'Bravo', 'Charlie']);
});

it('falls back to the source-arm default when order_by is not allowlisted', function () {
    $collection = buildSwctCollection('fallback-order', 3);

    $contract = (new CarouselDefinition())->dataContract(['collection_handle' => 'fallback-order']);

    DB::enableQueryLog();
    $dto = resolveContract($contract, ['order_by' => 'id']);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $itemQueries = array_filter($queries, fn ($q) => str_contains($q['query'], 'from "collection_items"'));
    $sql = array_values($itemQueries)[0]['query'];

    expect($sql)->toContain('order by "sort_order"')
        ->and($dto['items'])->toHaveCount(3);
});

it('reverses sort with direction: desc', function () {
    $collection = CmsCollection::create([
        'handle'      => 'dir-test',
        'name'        => 'Dir Test',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [
            ['key' => 'name', 'type' => 'text'],
        ],
    ]);

    foreach (['Alpha', 'Bravo', 'Charlie'] as $i => $name) {
        CollectionItem::create([
            'collection_id' => $collection->id,
            'sort_order'    => $i,
            'is_published'  => true,
            'data'          => ['name' => $name],
        ]);
    }

    $contract = (new BoardMembersDefinition())->dataContract([
        'collection_handle' => 'dir-test',
        'image_field'       => '',
        'name_field'        => 'name',
    ]);

    $dto = resolveContract($contract, ['order_by' => 'name', 'direction' => 'desc']);

    expect(array_column($dto['items'], 'name'))->toBe(['Charlie', 'Bravo', 'Alpha']);
});

it('filters SWCT items by include_tags via the unified tags relation', function () {
    $collection = buildSwctCollection('tag-include', 3);
    $featureTag = Tag::create(['name' => 'feature', 'type' => 'collection']);

    $items = $collection->collectionItems()->orderBy('sort_order')->get();
    $items[0]->tags()->attach($featureTag->id);
    $items[1]->tags()->attach($featureTag->id);

    $contract = (new CarouselDefinition())->dataContract(['collection_handle' => 'tag-include']);

    $dto = resolveContract($contract, ['include_tags' => ['feature']]);

    expect($dto['items'])->toHaveCount(2)
        ->and(array_column($dto['items'], 'title'))->toEqualCanonicalizing(['Slide 0', 'Slide 1']);
});

it('filters posts by include_tags via the unified tags relation', function () {
    $featureTag = Tag::create(['name' => 'feature', 'type' => 'post']);

    $tagged = Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Tagged Post',
        'slug'         => 'tagged-post',
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ]);
    $tagged->tags()->attach($featureTag->id);

    Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Untagged Post',
        'slug'         => 'untagged-post',
        'status'       => 'published',
        'published_at' => now()->subDays(2),
    ]);

    $contract = (new BlogListingDefinition())->dataContract([]);

    $dto = resolveContract($contract, ['include_tags' => ['feature']]);

    expect($dto['items'])->toHaveCount(1)
        ->and($dto['items'][0]['title'])->toBe('Tagged Post');
});

it('filters events by include_tags via the unified tags relation', function () {
    $featureTag = Tag::create(['name' => 'feature', 'type' => 'event']);

    $tagged = Event::factory()->create([
        'title'     => 'Tagged Event',
        'slug'      => 'tagged-event',
        'status'    => 'published',
        'starts_at' => now()->addDay(),
    ]);
    $tagged->tags()->attach($featureTag->id);

    Event::factory()->create([
        'title'     => 'Untagged Event',
        'slug'      => 'untagged-event',
        'status'    => 'published',
        'starts_at' => now()->addDays(2),
    ]);

    $contract = (new EventsListingDefinition())->dataContract([]);

    $dto = resolveContract($contract, ['include_tags' => ['feature']]);

    expect($dto['items'])->toHaveCount(1)
        ->and($dto['items'][0]['title'])->toBe('Tagged Event');
});

it('excludes SWCT items by exclude_tags', function () {
    $collection = buildSwctCollection('tag-exclude', 3);
    $hiddenTag = Tag::create(['name' => 'hidden', 'type' => 'collection']);

    $items = $collection->collectionItems()->orderBy('sort_order')->get();
    $items[0]->tags()->attach($hiddenTag->id);

    $contract = (new CarouselDefinition())->dataContract(['collection_handle' => 'tag-exclude']);

    $dto = resolveContract($contract, ['exclude_tags' => ['hidden']]);

    expect($dto['items'])->toHaveCount(2)
        ->and(array_column($dto['items'], 'title'))->toEqualCanonicalizing(['Slide 1', 'Slide 2']);
});

it('locks date_range as an immutable contract default (user query_config cannot override)', function () {
    Event::factory()->create([
        'title'     => 'Future Event',
        'slug'      => 'future-event',
        'status'    => 'published',
        'starts_at' => now()->addDay(),
    ]);

    $pastEvent = Event::factory()->create([
        'title'     => 'Past Event Should Not Render',
        'slug'      => 'past-event',
        'status'    => 'published',
        'starts_at' => now()->subDays(7),
    ]);

    $wt = WidgetType::where('handle', 'events_listing')->firstOrFail();
    $host = Page::factory()->create(['title' => 'Date Host', 'slug' => 'date-host', 'status' => 'published']);
    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'heading'          => '',
            'content_template' => '<article class="card">{{item.title}}</article>',
            'columns'          => 1,
            'items_per_page'   => 10,
            'show_search'      => false,
            'sort_default'     => 'soonest',
        ],
        // Attempt user override of the immutable date_range default.
        'query_config'   => ['date_range' => ['from' => '-30 days']],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)
        ->toContain('Future Event')
        ->not->toContain('Past Event Should Not Render');
});

it('does not add joins or subqueries when query_config is empty (SWCT no-filter pin)', function () {
    buildSwctCollection('no-filter', 3);
    $pw = carouselWidgetWithQueryConfig('no-filter', []);

    DB::enableQueryLog();
    WidgetRenderer::render($pw);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $itemQueries = array_values(array_filter($queries, fn ($q) => str_contains($q['query'], 'from "collection_items"')));

    expect(count($itemQueries))->toBe(1)
        ->and($itemQueries[0]['query'])->not->toContain('whereExists')
        ->and($itemQueries[0]['query'])->not->toContain('exists (select');
});

it('adds exactly one whereExists subquery when include_tags is supplied (SWCT filter pin)', function () {
    buildSwctCollection('filter-pin', 3);
    $tag = Tag::create(['name' => 'feature', 'type' => 'collection']);

    $pw = carouselWidgetWithQueryConfig('filter-pin', ['include_tags' => ['feature']]);
    $pw->setRelation('widgetType', $pw->widgetType);

    DB::enableQueryLog();
    WidgetRenderer::render($pw);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $itemQueries = array_values(array_filter($queries, fn ($q) => str_contains($q['query'], 'from "collection_items"')));
    $sql = $itemQueries[0]['query'];

    expect(count($itemQueries))->toBe(1)
        ->and(substr_count($sql, 'exists (select'))->toBe(1)
        ->and($sql)->toContain('"taggables"');
});
