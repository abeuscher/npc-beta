<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Runs the media-library scans for the operator-facing finder tool: an
 * unused-media scan (driven by MediaReferenceInventory), a duplicate scan
 * (content hash + filename/size), and a missing-file scan (media rows whose
 * stored file is gone from disk). All are read-only — they return report
 * data; deletion is confirmed separately on the tool page.
 */
class MediaFinderService
{
    public function __construct(private MediaReferenceInventory $inventory) {}

    /**
     * Every media row the inventory does not recognise as referenced, with its
     * classification (dead_collection / orphan_owner) and display metadata.
     *
     * @return array<int, array<string, mixed>>
     */
    public function scanUnused(): array
    {
        $rows = [];

        foreach (Media::query()->orderBy('id')->cursor() as $media) {
            $classification = $this->inventory->classify($media);

            if ($classification === MediaReferenceInventory::CLASS_LIVE) {
                continue;
            }

            $rows[] = [
                'id'              => (int) $media->id,
                'file_name'       => $media->file_name,
                'collection_name' => $media->collection_name,
                'size'            => (int) $media->size,
                'mime_type'       => $media->mime_type,
                'created_at'      => $media->created_at?->toDateTimeString(),
                'owner'           => $this->ownerLabel($media),
                'classification'  => $classification,
                'url'             => $this->previewUrl($media),
            ];
        }

        return $rows;
    }

    /**
     * Media rows whose stored file no longer exists on its disk. A missing
     * file that is still referenced is a visible breakage (broken image on a
     * live surface); an unreferenced one is just a dead row. The `referenced`
     * flag lets the operator prioritise.
     *
     * @return array<int, array<string, mixed>>
     */
    public function scanMissingFiles(): array
    {
        $rows = [];

        foreach (Media::query()->orderBy('id')->cursor() as $media) {
            if ($this->fileExists($media)) {
                continue;
            }

            $rows[] = [
                'id'              => (int) $media->id,
                'file_name'       => $media->file_name,
                'collection_name' => $media->collection_name,
                'size'            => (int) $media->size,
                'created_at'      => $media->created_at?->toDateTimeString(),
                'owner'           => $this->ownerLabel($media),
                'referenced'      => $this->inventory->classify($media) === MediaReferenceInventory::CLASS_LIVE,
            ];
        }

        return $rows;
    }

    private function fileExists(Media $media): bool
    {
        try {
            return Storage::disk($media->disk)->exists($media->getPathRelativeToRoot());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Clusters of likely-duplicate media. First pass groups by content hash
     * (identical bytes); a second pass groups remaining rows by filename+size.
     * Each member is tagged with whether it is currently referenced so the
     * operator can keep the referenced record when merging.
     *
     * @return array<int, array<string, mixed>>
     */
    public function scanDuplicates(): array
    {
        $all = Media::query()->orderBy('id')->get();

        $byHash = [];
        foreach ($all as $media) {
            $hash = $this->contentHash($media);
            if ($hash === null) {
                continue;
            }
            $byHash[$hash][] = $media;
        }

        $clusters = [];
        $clusteredByHash = [];
        foreach ($byHash as $members) {
            if (count($members) > 1) {
                foreach ($members as $member) {
                    $clusteredByHash[$member->id] = true;
                }
                $clusters[] = $this->cluster($members, 'identical_content');
            }
        }

        // Second pass keys on filename + size (no bytes needed), skipping rows
        // already reported as identical-content duplicates.
        $byNameSize = [];
        foreach ($all as $media) {
            if (isset($clusteredByHash[$media->id])) {
                continue;
            }
            $byNameSize[$media->file_name . '|' . $media->size][] = $media;
        }

        foreach ($byNameSize as $members) {
            if (count($members) > 1) {
                $clusters[] = $this->cluster($members, 'same_name_size');
            }
        }

        return $clusters;
    }

    /**
     * @param  array<int, Media>  $members
     * @return array<string, mixed>
     */
    private function cluster(array $members, string $reason): array
    {
        return [
            'reason'  => $reason,
            'count'   => count($members),
            'members' => array_map(fn (Media $m) => [
                'id'              => (int) $m->id,
                'file_name'       => $m->file_name,
                'collection_name' => $m->collection_name,
                'size'            => (int) $m->size,
                'created_at'      => $m->created_at?->toDateTimeString(),
                'owner'           => $this->ownerLabel($m),
                'referenced'      => $this->inventory->classify($m) === MediaReferenceInventory::CLASS_LIVE,
                'url'             => $this->previewUrl($m),
            ], $members),
        ];
    }

    private function contentHash(Media $media): ?string
    {
        try {
            $path = $media->getPath();
        } catch (\Throwable) {
            return null;
        }

        if (! is_string($path) || ! is_file($path)) {
            return null;
        }

        $hash = @hash_file('sha256', $path);

        return $hash === false ? null : $hash;
    }

    private function previewUrl(Media $media): ?string
    {
        if (! str_starts_with((string) $media->mime_type, 'image/')) {
            return null;
        }

        try {
            return $media->getUrl();
        } catch (\Throwable) {
            return null;
        }
    }

    private function ownerLabel(Media $media): string
    {
        $model = $media->model;

        if (! $model) {
            return 'No owner';
        }

        $label = match (true) {
            $model instanceof \App\Models\PageWidget     => $model->label ?: 'Widget',
            $model instanceof \App\Models\CollectionItem => $model->data['title'] ?? 'Collection Item',
            $model instanceof \App\Models\EmailTemplate  => $model->handle ?? 'Email Template',
            default => class_basename($model),
        };

        return class_basename($model) . ': ' . $label;
    }
}
