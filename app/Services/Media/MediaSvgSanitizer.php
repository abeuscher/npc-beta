<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Sanitizes a freshly-added SVG media's stored original in place, stripping any
 * executable content (script / foreignObject / event handlers / javascript: URIs)
 * via SvgSanitizer.
 *
 * Runs at add time, before MediaContentHasher computes the content hash — so the
 * hash and the content-addressed relocation both see the cleaned bytes and no
 * re-hash is needed. This is the single media-add seam shared by every upload
 * path (the builder API controllers, the inline-image upload, Filament
 * SpatieMediaLibraryFileUpload fields, Media::copy(), importer seeding), so one
 * hook here covers them all.
 *
 * The stored-XSS vector this closes: registerMediaConversions() skips SVG, so the
 * original is served raw on direct URL navigation (a standalone-document context
 * that runs <script>). Neutralizing the bytes at rest defangs that.
 */
class MediaSvgSanitizer
{
    /**
     * A minimal inert SVG substituted when the upload is not parseable as XML
     * (SvgSanitizer::sanitize() returns null), so a malformed or hostile .svg can
     * never reach disk with its original bytes intact.
     */
    private const EMPTY_SVG = '<svg xmlns="http://www.w3.org/2000/svg"></svg>';

    /**
     * Returns true when the stored file was rewritten (an SVG whose bytes
     * changed), false when there was nothing to do — not an SVG, unreadable, or
     * already clean.
     */
    public function sanitize(Media $media): bool
    {
        if (! $this->isSvg($media)) {
            return false;
        }

        try {
            $disk = Storage::disk($media->disk);
            $path = $media->getPathRelativeToRoot();

            if (! $disk->exists($path)) {
                return false;
            }

            $original = $disk->get($path);
        } catch (\Throwable) {
            return false;
        }

        $clean = SvgSanitizer::sanitize($original) ?? self::EMPTY_SVG;

        if ($clean === $original) {
            return false;
        }

        $disk->put($path, $clean);

        // Keep the row's byte count consistent with the rewritten file. The hash
        // is computed after this returns (MediaContentHasher), so it sees $clean.
        $media->size = strlen($clean);
        $media->saveQuietly();

        return true;
    }

    private function isSvg(Media $media): bool
    {
        if ($media->mime_type === 'image/svg+xml') {
            return true;
        }

        return strtolower(pathinfo((string) $media->file_name, PATHINFO_EXTENSION)) === 'svg';
    }
}
