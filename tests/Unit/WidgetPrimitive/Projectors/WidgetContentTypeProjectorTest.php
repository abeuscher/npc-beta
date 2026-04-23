<?php

use App\Models\Collection as CmsCollection;
use App\Models\CollectionItem;
use App\WidgetPrimitive\ContentType;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\Projectors\WidgetContentTypeProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function carouselContract(array $fields = ['title']): DataContract
{
    return new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_WIDGET_CONTENT_TYPE,
        fields: $fields,
        resourceHandle: 'slides-unit',
        contentType: new ContentType(
            handle: 'carousel.slide',
            fields: [
                ['key' => 'title', 'type' => 'text'],
                ['key' => 'image', 'type' => 'image'],
            ],
        ),
    );
}

it('projects collection items into a row-set DTO with declared fields only', function () {
    $collection = CmsCollection::create([
        'handle'      => 'slides-unit',
        'name'        => 'Slides Unit',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [['key' => 'title', 'type' => 'text']],
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

    $contract = carouselContract(fields: ['title']);
    $items = CollectionItem::where('collection_id', $collection->id)->get();

    $dto = app(WidgetContentTypeProjector::class)->project($contract, $items);

    expect($dto['items'])->toHaveCount(1)
        ->and($dto['items'][0]['title'])->toBe('Slide One')
        ->and($dto['items'][0])->not->toHaveKey('secret_internal')
        ->and($dto['items'][0])->toHaveKey('_media')
        ->and($dto['items'][0]['_media'])->toHaveKey('image');
});

it('returns an empty item list when the contract has no content type', function () {
    $contract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_WIDGET_CONTENT_TYPE,
        fields: ['title'],
        resourceHandle: 'anything',
        contentType: null,
    );

    $dto = app(WidgetContentTypeProjector::class)->project($contract, collect());

    expect($dto)->toBe(['items' => []]);
});

it('projects fallback rows through the same field filter as live items', function () {
    $contract = carouselContract(fields: ['title']);

    $fallback = [
        ['title' => 'Fallback One', 'secret_internal' => 'should not appear', '_media' => []],
        ['title' => 'Fallback Two', '_media' => []],
    ];

    $dto = app(WidgetContentTypeProjector::class)->projectFallback($contract, $fallback);

    expect($dto['items'])->toHaveCount(2)
        ->and($dto['items'][0]['title'])->toBe('Fallback One')
        ->and($dto['items'][0])->not->toHaveKey('secret_internal')
        ->and($dto['items'][0])->toHaveKey('_media')
        ->and($dto['items'][1]['title'])->toBe('Fallback Two');
});
