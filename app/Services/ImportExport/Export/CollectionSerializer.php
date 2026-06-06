<?php

namespace App\Services\ImportExport\Export;

use App\Models\Collection as CollectionModel;
use App\Models\CollectionItem;
use App\Models\Tag;

/**
 * Serializes Collection rows (shell + full item list) into the bundle's
 * portable collection shape. Each item's custom `data` JSON travels verbatim
 * (the collection's `fields` config is the source of truth) and each item's
 * tags travel by (name, type). Item-level media is out of scope. Session A001.
 */
class CollectionSerializer
{
    /**
     * @param  array<int, string>  $collectionIds
     * @return array<int, array<string, mixed>>
     */
    public function serializeMany(array $collectionIds): array
    {
        if (empty($collectionIds)) {
            return [];
        }

        return CollectionModel::whereIn('id', $collectionIds)
            ->with(['collectionItems' => fn ($q) => $q->orderBy('sort_order'), 'collectionItems.tags'])
            ->get()
            ->map(fn (CollectionModel $c) => $this->serializeCollection($c))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeCollection(CollectionModel $collection): array
    {
        return [
            'collection' => [
                'name'             => $collection->name,
                'handle'           => $collection->handle,
                'description'      => $collection->description,
                'fields'           => $collection->fields ?? [],
                'accepted_sources' => $collection->accepted_sources ?? ['human'],
                'source_type'      => $collection->source_type,
                'is_public'        => (bool) $collection->is_public,
                'is_active'        => (bool) $collection->is_active,
            ],
            'items' => $collection->collectionItems->map(fn (CollectionItem $item) => [
                'sort_order'   => (int) $item->sort_order,
                'is_published' => (bool) $item->is_published,
                'data'         => $item->data ?? [],
                'tags'         => $item->tags->map(fn (Tag $t) => [
                    'name' => $t->name,
                    'type' => $t->type,
                ])->all(),
            ])->all(),
        ];
    }
}
