<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Computes and persists the SHA-256 content hash of a media's stored original.
 *
 * The hash is the identity signal for upload-time dedup and the 318 duplicate
 * scan. It is computed once — when the file lands (via MediaHasBeenAddedEvent)
 * or during backfill — and read from the media.content_hash column thereafter,
 * never re-read off disk on the hot path.
 */
class MediaContentHasher
{
    /**
     * Hash the stored original bytes for a media row. Returns null when the file
     * cannot be read (missing on disk, unreadable disk) so the caller can skip.
     */
    public function hashFor(Media $media): ?string
    {
        try {
            $disk = Storage::disk($media->disk);
            $path = $media->getPathRelativeToRoot();

            if (! $disk->exists($path)) {
                return null;
            }

            $stream = $disk->readStream($path);
        } catch (\Throwable) {
            return null;
        }

        if (! is_resource($stream)) {
            return null;
        }

        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);

        return hash_final($context);
    }

    /**
     * Compute and persist the content hash for a media row that does not yet
     * carry one. Saves quietly so the hash write does not re-fire model events.
     */
    public function persist(Media $media): void
    {
        if ($media->content_hash !== null) {
            return;
        }

        $hash = $this->hashFor($media);

        if ($hash === null) {
            return;
        }

        $media->content_hash = $hash;
        $media->saveQuietly();
    }
}
