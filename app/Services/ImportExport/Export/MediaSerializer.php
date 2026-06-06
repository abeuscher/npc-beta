<?php

namespace App\Services\ImportExport\Export;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Serializes Spatie Media rows into posture-B descriptors for the bundle's
 * standalone media seed (media-portability draft decisions #3/#4/#6), and
 * collects the by-reference media a page/template bundle points at so a
 * combined bundle can seed media first. Session 320 content-addressing.
 */
class MediaSerializer
{
    /**
     * Serialize a specific set of media rows (ID-preserving seed list).
     *
     * @param  array<int, int|string>  $mediaIds
     * @return array<int, array<string, mixed>>
     */
    public function serializeIds(array $mediaIds): array
    {
        return Media::whereIn('id', $mediaIds)
            ->orderBy('id')
            ->get()
            ->map(fn (Media $m) => $this->serializeRow($m))
            ->all();
    }

    /**
     * Serialize every media row on the install.
     *
     * @return array<int, array<string, mixed>>
     */
    public function serializeAll(): array
    {
        return Media::orderBy('id')
            ->get()
            ->map(fn (Media $m) => $this->serializeRow($m))
            ->all();
    }

    /**
     * Walk the per-page / per-widget media descriptors already produced by the
     * page serializer and collect every media id they reference, then emit a
     * posture-B descriptor list (same shape serializeIds() emits) so the bundle
     * carries a self-contained media seed that the importer can replay before
     * pages re-attach their by-reference references.
     *
     * @param  array<int, array<string, mixed>>  $serializedPages
     * @param  array<int, array<string, mixed>>  $serializedTemplates
     * @return array<int, array<string, mixed>>
     */
    public function collectReferenced(array $serializedPages, array $serializedTemplates): array
    {
        $ids = [];

        $walk = function (array $widgets) use (&$ids, &$walk): void {
            foreach ($widgets as $item) {
                if (($item['type'] ?? 'widget') === 'layout') {
                    foreach ($item['slots'] ?? [] as $slotWidgets) {
                        $walk($slotWidgets);
                    }
                    continue;
                }
                foreach ($item['media'] ?? [] as $desc) {
                    $id = $this->mediaIdFromDescriptor($desc);
                    if ($id !== null) {
                        $ids[] = $id;
                    }
                }
            }
        };

        foreach ($serializedPages as $page) {
            foreach ($page['media'] ?? [] as $desc) {
                $id = $this->mediaIdFromDescriptor($desc);
                if ($id !== null) {
                    $ids[] = $id;
                }
            }
            $walk($page['widgets'] ?? []);
        }

        foreach ($serializedTemplates as $tpl) {
            $walk($tpl['widgets'] ?? []);
        }

        $ids = array_values(array_unique($ids));
        if (empty($ids)) {
            return [];
        }

        return Media::whereIn('id', $ids)
            ->orderBy('id')
            ->get()
            ->map(fn (Media $m) => $this->serializeRow($m))
            ->all();
    }

    /**
     * Resolve a media descriptor's id: the explicit `id` field when present,
     * else the legacy id parsed from a `{id}/` path token (older bundles, before
     * paths became content-addressed).
     *
     * @param  array<string, mixed>  $desc
     */
    private function mediaIdFromDescriptor(array $desc): ?int
    {
        if (isset($desc['id']) && is_numeric($desc['id'])) {
            return (int) $desc['id'];
        }

        $path = $desc['path'] ?? null;
        if (is_string($path) && preg_match('/^(\d+)\//', $path, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Posture-B descriptor: every column needed to recreate the row by raw
     * explicit-id insert on the target, plus content_hash and the on-disk path
     * (content-addressed since session 320) so the seeded bytes land where the
     * row resolves.
     *
     * @return array<string, mixed>
     */
    private function serializeRow(Media $m): array
    {
        return [
            'id'                => $m->id,
            'uuid'              => $m->uuid,
            'model_type'        => $m->model_type,
            'model_id'          => $m->model_id,
            'collection_name'   => $m->collection_name,
            'name'              => $m->name,
            'file_name'         => $m->file_name,
            'mime_type'         => $m->mime_type,
            'disk'              => $m->disk,
            'conversions_disk'  => $m->conversions_disk,
            'size'              => $m->size,
            'manipulations'     => $m->manipulations ?? [],
            'custom_properties' => $m->custom_properties ?? [],
            'responsive_images' => $m->responsive_images ?? [],
            'order_column'      => $m->order_column,
            'content_hash'      => $m->content_hash,
            'path'              => $m->getPathRelativeToRoot(),
        ];
    }
}
