<?php

namespace App\Services\ImportExport\Import;

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Tag;
use App\Services\ImportExport\ImportLog;

/**
 * Imports a serialized collection: upsert by handle, replace its items
 * wholesale, and re-tag each item via firstOrCreate(name,type). Item `data`
 * JSON travels verbatim — the collection's `fields` config is the shape's
 * source of truth, not the importer. Session A001.
 */
class CollectionImporter
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function import(array $data, ImportLog $log): void
    {
        $collectionData = $data['collection'] ?? null;
        $handle         = $collectionData['handle'] ?? null;

        if (! is_array($collectionData) || ! is_string($handle) || $handle === '') {
            $log->warning('Collection entry missing collection.handle, skipped.');

            return;
        }

        $attributes = [
            'name'             => $collectionData['name'] ?? $handle,
            'handle'           => $handle,
            'description'      => $collectionData['description'] ?? null,
            'fields'           => $collectionData['fields'] ?? [],
            'accepted_sources' => $collectionData['accepted_sources'] ?? ['human'],
            'source_type'      => $collectionData['source_type'] ?? 'custom',
            'is_public'        => (bool) ($collectionData['is_public'] ?? false),
            'is_active'        => (bool) ($collectionData['is_active'] ?? true),
        ];

        $collection = Collection::where('handle', $handle)->first();
        if ($collection) {
            $collection->update($attributes);
        } else {
            $collection = Collection::create($attributes);
        }

        CollectionItem::where('collection_id', $collection->id)->delete();

        foreach ($data['items'] ?? [] as $sortIndex => $itemRow) {
            if (! is_array($itemRow)) {
                continue;
            }

            $item = CollectionItem::create([
                'collection_id' => $collection->id,
                'data'          => $itemRow['data'] ?? [],
                'sort_order'    => (int) ($itemRow['sort_order'] ?? $sortIndex),
                'is_published'  => (bool) ($itemRow['is_published'] ?? false),
            ]);

            $tagIds = [];
            foreach ($itemRow['tags'] ?? [] as $tagRow) {
                $tagName = $tagRow['name'] ?? null;
                $tagType = $tagRow['type'] ?? 'collection_item';
                if (! is_string($tagName) || $tagName === '') {
                    continue;
                }
                $tag = Tag::firstOrCreate(
                    ['name' => $tagName, 'type' => $tagType],
                );
                $tagIds[] = $tag->id;
            }

            if (! empty($tagIds)) {
                $item->tags()->sync($tagIds);
            }
        }
    }
}
