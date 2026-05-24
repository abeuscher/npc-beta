<?php

namespace App\Services\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Stores a media file under a directory derived from its content_hash rather than
 * its id, so two media rows with identical bytes resolve to the same physical
 * path (and the same conversions/responsive-images alongside it). The hash is the
 * SHA-256 set by MediaContentHasher when the file lands.
 *
 * The hash is not yet known during the brief window of the initial add — the file
 * is copied to disk before MediaHasBeenAddedEvent fires — so getPath falls back to
 * the legacy id-based path until the hash exists. The add-time listener relocates
 * the file to its content-addressed path the moment the hash is computed (see
 * MediaRelocator + AppServiceProvider).
 */
class ContentAddressedPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->getBasePath($media);
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getBasePath($media).'conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getBasePath($media).'responsive-images/';
    }

    protected function getBasePath(Media $media): string
    {
        $hash = $media->content_hash;

        if (! is_string($hash) || strlen($hash) !== 64) {
            return self::legacyBasePath($media);
        }

        return self::contentBasePath($hash);
    }

    /**
     * The content-addressed base directory for a hash, sharded one byte deep to
     * keep any single directory small. Always ends in a slash.
     */
    public static function contentBasePath(string $hash): string
    {
        return self::prefix().'cas/'.substr($hash, 0, 2).'/'.$hash.'/';
    }

    /**
     * The legacy id-based base directory (the DefaultPathGenerator shape) — the
     * initial-add fallback and the relocation source for existing files.
     */
    public static function legacyBasePath(Media $media): string
    {
        return self::prefix().$media->getKey().'/';
    }

    private static function prefix(): string
    {
        $prefix = (string) config('media-library.prefix', '');

        return $prefix !== '' ? rtrim($prefix, '/').'/' : '';
    }
}
