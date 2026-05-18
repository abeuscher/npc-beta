<?php

namespace App\Services\ImportExport;

use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Container primitive for self-contained content+media bundles (session 303,
 * media-portability draft decision #1/#2/#7). A bundle zip is:
 *
 *   bundle.zip
 *   ├── bundle.json                  the unchanged ContentExporter envelope
 *   └── media/{media_id}/{file_name} one entry per media descriptor
 *
 * The envelope shape and FORMAT_VERSION are untouched — the zip is detected by
 * file type, never a version field. JSON-only bundles never become zips; only
 * the explicit "export with media" path produces one.
 */
class BundleArchive
{
    /**
     * Hard ceilings for the zip-bomb guard (decision #7). Defaults are generous
     * for real media (already-compressed images/video deflate ~1:1) but reject
     * a highly-compressible bomb. Overridable via the constructor so tests can
     * assert rejection against a tiny ceiling.
     */
    public const DEFAULT_MAX_TOTAL_UNCOMPRESSED = 1_073_741_824; // 1 GiB

    public const DEFAULT_MAX_ENTRIES = 50_000;

    public function __construct(
        private int $maxTotalUncompressed = self::DEFAULT_MAX_TOTAL_UNCOMPRESSED,
        private int $maxEntries = self::DEFAULT_MAX_ENTRIES,
    ) {}

    /**
     * Build a bundle zip at $zipPath from an envelope plus the media bytes its
     * descriptors point at. Reads each file via its descriptor disk, so it is
     * disk-agnostic (public, spaces, …). The written bundle.json carries
     * media_transport = "embedded".
     *
     * @param  array<string, mixed>  $envelope
     */
    public function build(array $envelope, string $zipPath): void
    {
        $dir = dirname($zipPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $envelope['media_transport'] = 'embedded';

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Could not create bundle archive at {$zipPath}.");
        }

        $zip->addFromString(
            'bundle.json',
            json_encode($envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        $seen = [];
        foreach ($this->collectDescriptors($envelope['payload'] ?? []) as $desc) {
            $disk = $desc['disk'] ?? null;
            $path = $desc['path'] ?? null;

            if (! is_string($disk) || ! is_string($path) || $path === '') {
                continue;
            }
            if (str_contains($path, '..') || str_starts_with($path, '/')) {
                continue;
            }

            $entry = 'media/' . $path;
            if (isset($seen[$entry])) {
                continue;
            }
            $seen[$entry] = true;

            if (! Storage::disk($disk)->exists($path)) {
                continue;
            }

            $zip->addFromString($entry, Storage::disk($disk)->get($path));
        }

        $zip->close();
    }

    /**
     * Extract a bundle zip to a fresh temp directory after the zip-slip and
     * zip-bomb guards pass. Returns the parsed envelope and the absolute path of
     * the extracted media/ root (may not exist if the bundle carried no media).
     * The caller owns cleanup of the returned directory.
     *
     * @return array{envelope: array<string, mixed>, mediaRoot: string, tempDir: string}
     */
    public function extract(string $zipPath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new InvalidImportBundleException('Bundle archive is not a readable zip.');
        }

        if ($zip->numFiles > $this->maxEntries) {
            $zip->close();
            throw new InvalidImportBundleException(
                "Bundle archive has too many entries ({$zip->numFiles} > {$this->maxEntries})."
            );
        }

        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                $zip->close();
                throw new InvalidImportBundleException('Bundle archive entry is unreadable.');
            }

            $name = $stat['name'];
            $this->assertSafeEntryName($zip, $name, $i);

            $total += (int) $stat['size'];
            if ($total > $this->maxTotalUncompressed) {
                $zip->close();
                throw new InvalidImportBundleException(
                    "Bundle archive exceeds the {$this->maxTotalUncompressed}-byte uncompressed ceiling."
                );
            }
        }

        $tempDir = rtrim(sys_get_temp_dir(), '/') . '/bundle_' . bin2hex(random_bytes(8));
        mkdir($tempDir, 0700, true);

        if (! $zip->extractTo($tempDir)) {
            $zip->close();
            $this->rmrf($tempDir);
            throw new InvalidImportBundleException('Bundle archive could not be extracted.');
        }
        $zip->close();

        $jsonPath = $tempDir . '/bundle.json';
        if (! is_file($jsonPath)) {
            $this->rmrf($tempDir);
            throw new InvalidImportBundleException('Bundle archive is missing bundle.json.');
        }

        $envelope = json_decode((string) file_get_contents($jsonPath), true);
        if (! is_array($envelope)) {
            $this->rmrf($tempDir);
            throw new InvalidImportBundleException('Bundle archive bundle.json is not valid JSON.');
        }

        return [
            'envelope'  => $envelope,
            'mediaRoot' => $tempDir . '/media',
            'tempDir'   => $tempDir,
        ];
    }

    /**
     * Zip-slip defence (decision #7): reject absolute paths, traversal, and
     * symlink entries before anything is written to disk.
     */
    private function assertSafeEntryName(ZipArchive $zip, string $name, int $index): void
    {
        if ($name === '' || str_starts_with($name, '/') || str_starts_with($name, '\\')) {
            $zip->close();
            throw new InvalidImportBundleException("Unsafe absolute entry path in bundle archive: {$name}");
        }

        $normalized = str_replace('\\', '/', $name);
        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '..') {
                $zip->close();
                throw new InvalidImportBundleException("Path traversal in bundle archive entry: {$name}");
            }
        }

        // Unix mode is the high 16 bits of the external attributes; 0xA000 = symlink.
        $opsys = 0;
        $attr = 0;
        if ($zip->getExternalAttributesIndex($index, $opsys, $attr)) {
            $mode = ($attr >> 16) & 0xFFFF;
            if (($mode & 0xF000) === 0xA000) {
                $zip->close();
                throw new InvalidImportBundleException("Symlink entry rejected in bundle archive: {$name}");
            }
        }
    }

    /**
     * Walk the payload tree and yield every media descriptor (any array
     * carrying disk + path + file_name). Uniformly covers page media, widget
     * media nested in layouts/slots, and the standalone payload.media list.
     *
     * @param  mixed  $node
     * @return \Generator<int, array<string, mixed>>
     */
    private function collectDescriptors(mixed $node): \Generator
    {
        if (! is_array($node)) {
            return;
        }

        if (isset($node['disk'], $node['path'], $node['file_name'])
            && is_string($node['disk']) && is_string($node['path'])) {
            yield $node;

            return;
        }

        foreach ($node as $child) {
            yield from $this->collectDescriptors($child);
        }
    }

    private function rmrf(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
