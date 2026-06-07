<?php

namespace App\Services\ImportExport\Import;

/**
 * Per-import media-byte resolver. Carries the absolute path of an extracted
 * bundle's media/ root (set for the duration of a single import() call when the
 * source was a zip) and resolves descriptor paths against it. When the root is
 * null the importer is on the unchanged JSON/local-disk path and `archiveFile()`
 * returns null so callers fall back to their on-disk behaviour
 * (media-portability draft decision #2).
 *
 * A fresh instance is constructed per import() call and threaded to the entity
 * importers that rewire media, so the shared state is explicit rather than held
 * on the importer object across calls.
 */
class BundleMediaArchive
{
    public function __construct(private ?string $mediaRoot = null) {}

    /**
     * Archive-first resolution (media-portability draft decision #2): the
     * absolute path of a descriptor's file inside the extracted bundle media
     * tree, or null when there is no archive or the file is absent — in which
     * case callers fall back to the unchanged local-disk behaviour. Re-guards
     * traversal and confirms the resolved path stays within the media root.
     */
    public function archiveFile(string $path): ?string
    {
        if ($this->mediaRoot === null || $path === '') {
            return null;
        }
        if (str_contains($path, '..') || str_starts_with($path, '/')) {
            return null;
        }

        $abs = $this->mediaRoot . '/' . $path;
        if (! is_file($abs)) {
            return null;
        }

        $real     = realpath($abs);
        $rootReal = realpath($this->mediaRoot);
        if ($real === false || $rootReal === false || ! str_starts_with($real, $rootReal . '/')) {
            return null;
        }

        return $real;
    }
}
