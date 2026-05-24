<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Moves a media's files from the legacy id-based directory to its
 * content-addressed directory, collapsing byte-duplicates onto one physical
 * copy. Used both at upload time (relocate the just-written original once its
 * hash is known) and by the one-time relocation of the existing library.
 *
 * Idempotent and resumable: a file already at its content-addressed target is
 * deleted from the source (it is the same bytes), and an already-relocated media
 * (empty source directory) is a no-op.
 */
class MediaRelocator
{
    public const RESULT_NOOP = 'noop';
    public const RESULT_MOVED = 'moved';
    public const RESULT_DEDUPED = 'deduped';
    public const RESULT_NO_HASH = 'no_hash';

    public function relocate(Media $media): string
    {
        $hash = $media->content_hash;

        if (! is_string($hash) || strlen($hash) !== 64) {
            return self::RESULT_NO_HASH;
        }

        $oldBase = ContentAddressedPathGenerator::legacyBasePath($media);
        $newBase = ContentAddressedPathGenerator::contentBasePath($hash);

        if ($oldBase === $newBase) {
            return self::RESULT_NOOP;
        }

        $disk = Storage::disk($media->disk);
        $oldDir = rtrim($oldBase, '/');

        $files = $disk->allFiles($oldDir);

        if (empty($files)) {
            return self::RESULT_NOOP;
        }

        $deduped = false;

        foreach ($files as $file) {
            $relative = ltrim(substr($file, strlen($oldDir)), '/');
            $target = $newBase.$relative;

            if ($disk->exists($target)) {
                // Identical bytes are already stored at the content-addressed
                // path (another row got here first) — drop this copy.
                $disk->delete($file);
                $deduped = true;

                continue;
            }

            $disk->move($file, $target);
        }

        if (empty($disk->allFiles($oldDir))) {
            $disk->deleteDirectory($oldDir);
        }

        return $deduped ? self::RESULT_DEDUPED : self::RESULT_MOVED;
    }
}
