<?php

use App\Models\Collection;
use App\Models\CollectionItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function makeCollection(array $overrides = []): Collection
{
    return Collection::create(array_merge([
        'name'        => 'Test Collection',
        'handle'      => 'test-collection',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [
            ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true, 'helpText' => '', 'options' => []],
        ],
    ], $overrides));
}

it('stores arbitrary JSONB data correctly', function () {
    $collection = makeCollection();

    $item = CollectionItem::create([
        'collection_id' => $collection->id,
        'data'          => ['title' => 'Hello World', 'extra' => 42],
        'sort_order'    => 1,
        'is_published'  => true,
    ]);

    expect($item->data['title'])->toBe('Hello World')
        ->and($item->data['extra'])->toBe(42);
});

it('casts data as array', function () {
    $collection = makeCollection();

    $item = CollectionItem::create([
        'collection_id' => $collection->id,
        'data'          => ['title' => 'Cast Test'],
        'sort_order'    => 0,
        'is_published'  => false,
    ]);

    // Reload from DB to confirm cast survives round-trip.
    $fresh = CollectionItem::find($item->id);

    expect($fresh->data)->toBeArray()
        ->and($fresh->data['title'])->toBe('Cast Test');
});

it('soft-deleted items are excluded from default queries', function () {
    $collection = makeCollection();

    $item = CollectionItem::create([
        'collection_id' => $collection->id,
        'data'          => ['title' => 'To Be Deleted'],
        'sort_order'    => 0,
        'is_published'  => true,
    ]);

    $item->delete();

    expect(CollectionItem::find($item->id))->toBeNull()
        ->and(CollectionItem::withTrashed()->find($item->id))->not->toBeNull();
});
