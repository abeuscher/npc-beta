<?php

namespace App\Services\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Upload-time duplicate detection: given an incoming file's content hash (and
 * optionally its original filename), find existing library assets the operator
 * could reuse instead of storing a fresh copy.
 *
 * Two signals, catching different cases:
 *  - content hash (identical bytes) — the exact re-upload; "use existing".
 *  - filename, different hash — the iteration case; "replace existing". Only
 *    meaningful on surfaces that preserve the original name (page-builder
 *    uploads use ->hashName(), so they pass hash only).
 *
 * Identical-hash rows are collapsed to a single candidate and the list is
 * sorted referenced-first (the asset the site actually uses is the likely
 * reuse target), so the picker is not itself a wall of duplicates.
 */
class MediaDedupService
{
    public function __construct(private MediaReferenceInventory $inventory) {}

    /**
     * @return array<int, array<string, mixed>> candidate descriptors
     */
    public function findMatches(string $hash, ?string $fileName = null): array
    {
        $candidates = [];

        // Identity: every row sharing the incoming bytes, collapsed to the best
        // representative (referenced-first, then oldest).
        $identical = Media::query()
            ->where('content_hash', $hash)
            ->orderBy('id')
            ->get();

        if ($identical->isNotEmpty()) {
            $best = $this->pickRepresentative($identical);
            $candidates[] = $this->describe($best, 'identical', $identical->count());
        }

        // Iteration: same filename, different bytes. Skipped when no filename is
        // supplied (hash-only surfaces) or the name is a randomised hashName.
        if ($fileName !== null && $fileName !== '' && ! $this->looksHashed($fileName)) {
            $named = Media::query()
                ->where('file_name', $fileName)
                ->where(function ($q) use ($hash) {
                    $q->whereNull('content_hash')->orWhere('content_hash', '!=', $hash);
                })
                ->orderBy('id')
                ->get();

            foreach ($named as $media) {
                $candidates[] = $this->describe($media, 'same_name', 1);
            }
        }

        // Referenced-first, then most-recent.
        usort($candidates, function (array $a, array $b) {
            return [$b['referenced'], $b['id']] <=> [$a['referenced'], $a['id']];
        });

        return $candidates;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Media>  $members
     */
    private function pickRepresentative($members): Media
    {
        return $members
            ->sortByDesc(fn (Media $m) => $this->isReferenced($m) ? 1 : 0)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(Media $media, string $matchType, int $duplicateCount): array
    {
        return [
            'id'              => (int) $media->id,
            'file_name'       => $media->file_name,
            'collection_name' => $media->collection_name,
            'size'            => (int) $media->size,
            'mime_type'       => $media->mime_type,
            'created_at'      => $media->created_at?->toDateTimeString(),
            'match_type'      => $matchType,
            'duplicate_count' => $duplicateCount,
            'referenced'      => $this->isReferenced($media),
            'url'             => $this->previewUrl($media),
        ];
    }

    private function isReferenced(Media $media): bool
    {
        return $this->inventory->classify($media) === MediaReferenceInventory::CLASS_LIVE;
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

    /**
     * Spatie's ->hashName() produces a 40-char hex basename. Filename matching on
     * those is near-useless, so we treat them as hash-only.
     */
    private function looksHashed(string $fileName): bool
    {
        $base = pathinfo($fileName, PATHINFO_FILENAME);

        return (bool) preg_match('/^[a-f0-9]{40}$/', $base);
    }
}
