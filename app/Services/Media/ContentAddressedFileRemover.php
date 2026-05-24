<?php

namespace App\Services\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\FileRemover\DefaultFileRemover;

/**
 * Refcounted deletion for content-addressed storage. Several media rows can share
 * one physical directory (identical bytes), so the files are removed only when
 * the row being deleted is the last one referencing that content_hash. While any
 * sibling remains, the row's database record is deleted but the bytes stay.
 */
class ContentAddressedFileRemover extends DefaultFileRemover
{
    public function removeAllFiles(Media $media): void
    {
        if ($this->contentSharedByOtherRows($media)) {
            return;
        }

        parent::removeAllFiles($media);
    }

    protected function contentSharedByOtherRows(Media $media): bool
    {
        $hash = $media->content_hash;

        if (! is_string($hash) || strlen($hash) !== 64) {
            return false;
        }

        return Media::query()
            ->where('content_hash', $hash)
            ->whereKeyNot($media->getKey())
            ->exists();
    }
}
