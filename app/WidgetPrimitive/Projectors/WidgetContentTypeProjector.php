<?php

namespace App\WidgetPrimitive\Projectors;

use App\Models\CollectionItem;
use App\WidgetPrimitive\DataContract;
use Illuminate\Support\Collection;

final class WidgetContentTypeProjector
{
    /**
     * Project a pre-fetched Eloquent collection of CollectionItems into a
     * row-set DTO shaped by the contract's declared fields. When the contract's
     * content type declares image fields, each row gains a '_media' map of
     * resolved media models keyed by image field.
     *
     * @param  Collection<int, CollectionItem>  $items
     * @return array{items: array<int, array<string, mixed>>}
     */
    public function project(DataContract $contract, Collection $items): array
    {
        if ($contract->contentType === null) {
            return ['items' => []];
        }

        $rows = $items->map(function (CollectionItem $item) use ($contract) {
            $data = $item->data ?? [];
            $media = [];
            foreach ($contract->contentType->imageFieldKeys() as $imageKey) {
                $media[$imageKey] = $item->getFirstMedia($imageKey);
            }
            return $this->projectRow($contract, $data, $media);
        })->values()->all();

        return ['items' => $rows];
    }

    /**
     * Project a list of fallback rows (plain associative arrays, typically
     * demo-data payloads from WidgetDataResolver) through the same field
     * projection used for live items. Pre-existing '_media' on each row is
     * honoured; undeclared fields are stripped.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{items: array<int, array<string, mixed>>}
     */
    public function projectFallback(DataContract $contract, array $rows): array
    {
        if ($contract->contentType === null) {
            return ['items' => []];
        }

        $projected = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $media = isset($row['_media']) && is_array($row['_media']) ? $row['_media'] : [];
            $projected[] = $this->projectRow($contract, $row, $media);
        }

        return ['items' => $projected];
    }

    /**
     * Shape one row against the contract's declared fields plus content-type
     * image fields.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $media
     * @return array<string, mixed>
     */
    public function projectRow(DataContract $contract, array $data, array $media = []): array
    {
        $row = [];
        foreach ($contract->fields as $field) {
            $row[$field] = $data[$field] ?? '';
        }

        $imageKeys = $contract->contentType?->imageFieldKeys() ?? [];
        if ($imageKeys !== []) {
            $projectedMedia = [];
            foreach ($imageKeys as $imageKey) {
                $projectedMedia[$imageKey] = $media[$imageKey] ?? null;
            }
            $row['_media'] = $projectedMedia;
        }

        return $row;
    }
}
