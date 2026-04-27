<?php

use App\Models\Collection as CmsCollection;
use App\Models\CollectionItem;
use App\Models\Page;
use App\WidgetPrimitive\AmbientContexts\PageAmbientContext;
use App\WidgetPrimitive\ContentType;
use App\WidgetPrimitive\ContractResolver;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\SlotContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->resolver = app(ContractResolver::class);
});

function slotWithCurrentPage(?Page $page = null): SlotContext
{
    return new SlotContext(new PageAmbientContext($page));
}

it('returns DTOs indexed to match the input contract list', function () {
    $contracts = [
        new DataContract('1.0.0', DataContract::SOURCE_PAGE_CONTEXT, ['title']),
        new DataContract('1.0.0', DataContract::SOURCE_PAGE_CONTEXT, ['title', 'date']),
        new DataContract('1.0.0', DataContract::SOURCE_PAGE_CONTEXT),
    ];

    $page = Page::factory()->create(['title' => 'Hello', 'published_at' => '2026-04-22 00:00:00']);

    $dtos = $this->resolver->resolve($contracts, slotWithCurrentPage($page));

    expect($dtos)->toHaveCount(3)
        ->and($dtos[0])->toBe(['title' => 'Hello'])
        ->and($dtos[1])->toBe(['title' => 'Hello', 'date' => 'April 22, 2026'])
        ->and($dtos[2])->toHaveKeys(['title', 'date', 'excerpt', 'author', 'starts_at', 'location'])
        ->and($dtos[2]['title'])->toBe('Hello');
});

it('omits fields not declared on the contract', function () {
    $page = Page::factory()->create(['title' => 'Secret', 'meta_description' => 'Hidden excerpt']);

    $contract = new DataContract('1.0.0', DataContract::SOURCE_PAGE_CONTEXT, ['title']);

    $dto = $this->resolver->resolve([$contract], slotWithCurrentPage($page))[0];

    expect($dto)->toHaveKey('title')
        ->and($dto)->not->toHaveKey('excerpt')
        ->and($dto)->not->toHaveKey('meta_description');
});

it('returns empty scalars when the slot has no current page', function () {
    $contract = new DataContract('1.0.0', DataContract::SOURCE_PAGE_CONTEXT, ['title', 'date']);

    $dto = $this->resolver->resolve([$contract], new SlotContext(new PageAmbientContext()))[0];

    expect($dto)->toBe(['title' => '', 'date' => '']);
});

it('resolves a system_model post contract into a row-set DTO', function () {
    Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Post A',
        'slug'         => 'post-a',
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ]);

    $contract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['title', 'slug'],
        model: 'post',
    );

    $dto = $this->resolver->resolve([$contract], new SlotContext(new PageAmbientContext()))[0];

    expect($dto)->toHaveKey('items')
        ->and($dto['items'])->toHaveCount(1)
        ->and($dto['items'][0])->toHaveKeys(['title', 'slug'])
        ->and($dto['items'][0])->not->toHaveKey('excerpt')
        ->and($dto['items'][0]['title'])->toBe('Post A')
        ->and($dto['items'][0]['slug'])->toBe('post-a');
});

it('batches system_model resolution when multiple contracts share the same filter shape', function () {
    Page::factory()->count(3)->create([
        'type'         => 'post',
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ]);

    $contracts = [
        new DataContract('1.0.0', DataContract::SOURCE_SYSTEM_MODEL, ['title'], model: 'post'),
        new DataContract('1.0.0', DataContract::SOURCE_SYSTEM_MODEL, ['title', 'slug'], model: 'post'),
    ];

    DB::enableQueryLog();
    $dtos = $this->resolver->resolve($contracts, new SlotContext(new PageAmbientContext()));
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $postQueries = array_filter($queries, fn ($q) => str_contains($q['query'], 'pages'));

    expect($dtos)->toHaveCount(2)
        ->and($dtos[0]['items'])->toHaveCount(3)
        ->and($dtos[1]['items'])->toHaveCount(3)
        ->and(count($postQueries))->toBeLessThan(3);
});

it('resolves a widget_content_type contract into a row-set DTO with declared fields only', function () {
    $collection = CmsCollection::create([
        'handle'      => 'slides-test',
        'name'        => 'Slides',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [
            ['key' => 'title', 'type' => 'text'],
            ['key' => 'image', 'type' => 'image'],
            ['key' => 'secret_internal', 'type' => 'text'],
        ],
    ]);

    CollectionItem::create([
        'collection_id' => $collection->id,
        'sort_order'    => 0,
        'is_published'  => true,
        'data'          => [
            'title'           => 'Slide One',
            'secret_internal' => 'should not appear',
        ],
    ]);

    $contentType = new ContentType(
        handle: 'carousel.slide',
        fields: [
            ['key' => 'title', 'type' => 'text'],
            ['key' => 'image', 'type' => 'image'],
        ],
    );

    $contract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_WIDGET_CONTENT_TYPE,
        fields: ['title'],
        resourceHandle: 'slides-test',
        contentType: $contentType,
    );

    $dto = $this->resolver->resolve([$contract], new SlotContext(new PageAmbientContext()))[0];

    expect($dto)->toHaveKey('items')
        ->and($dto['items'])->toHaveCount(1)
        ->and($dto['items'][0]['title'])->toBe('Slide One')
        ->and($dto['items'][0])->not->toHaveKey('secret_internal')
        ->and($dto['items'][0]['_media'])->toHaveKey('image');
});

it('returns an empty item list when a widget_content_type references an unknown collection handle', function () {
    $contentType = new ContentType(handle: 'x', fields: [['key' => 'title', 'type' => 'text']]);

    $contract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_WIDGET_CONTENT_TYPE,
        fields: ['title'],
        resourceHandle: 'does-not-exist',
        contentType: $contentType,
    );

    $dto = $this->resolver->resolve([$contract], new SlotContext(new PageAmbientContext()))[0];

    expect($dto)->toBe(['items' => []]);
});
