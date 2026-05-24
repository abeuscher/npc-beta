<?php

namespace App\Console\Commands;

use App\Services\Media\ContentAddressedPathGenerator;
use App\Services\Media\MediaContentHasher;
use App\Services\Media\MediaReferenceInventory;
use App\Services\Media\MediaRelocator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One-time relocation of an existing library to content-addressed storage:
 * moves every media file from its legacy id-based directory to its hash-based
 * directory (collapsing byte-duplicates onto one physical copy), then rewrites
 * embedded /storage/{id}/ URLs in rich-text to the new content-addressed form.
 *
 * Idempotent and resumable — re-running is a no-op for already-relocated files
 * and already-rewritten content, so it is safe to invoke from the migration and
 * to re-run by hand on a larger library.
 */
class RelocateMediaToCasCommand extends Command
{
    protected $signature = 'media:relocate-cas';

    protected $description = 'Relocate media files to content-addressed storage and rewrite embedded URLs.';

    public function handle(MediaContentHasher $hasher, MediaRelocator $relocator): int
    {
        $counts = [
            MediaRelocator::RESULT_MOVED   => 0,
            MediaRelocator::RESULT_DEDUPED => 0,
            MediaRelocator::RESULT_NOOP    => 0,
            MediaRelocator::RESULT_NO_HASH => 0,
        ];

        Media::query()
            ->orderBy('id')
            ->chunkById(200, function ($media) use ($hasher, $relocator, &$counts): void {
                foreach ($media as $item) {
                    // A row added before the hash backfill could still be null;
                    // hash it so it can be placed in content-addressed storage.
                    $hasher->persist($item);

                    $counts[$relocator->relocate($item)]++;
                }
            });

        $this->info(sprintf(
            'Relocated media: %d moved, %d deduped, %d already in place, %d unhashable.',
            $counts[MediaRelocator::RESULT_MOVED],
            $counts[MediaRelocator::RESULT_DEDUPED],
            $counts[MediaRelocator::RESULT_NOOP],
            $counts[MediaRelocator::RESULT_NO_HASH],
        ));

        $rewritten = $this->rewriteEmbeddedUrls();
        $this->info("Rewrote embedded /storage/ URLs in {$rewritten} rich-text rows.");

        return self::SUCCESS;
    }

    /**
     * Replace every embedded /storage/{id}/ URL with the content-addressed
     * /storage/cas/{shard}/{hash}/ form. The sub-path after the base (file name,
     * conversions/, responsive-images/) is preserved, so this prefix swap covers
     * originals and derived files alike.
     */
    private function rewriteEmbeddedUrls(): int
    {
        $hashById = Media::query()->pluck('content_hash', 'id');

        $rewritten = 0;

        foreach (MediaReferenceInventory::EMBEDDED_SURFACES as [$table, $column]) {
            foreach (DB::table($table)->select('id', $column)->cursor() as $row) {
                $content = (string) ($row->{$column} ?? '');

                if ($content === '' || ! str_contains($content, '/storage/')) {
                    continue;
                }

                $updated = preg_replace_callback(
                    '#/storage/(\d+)/#',
                    function (array $m) use ($hashById): string {
                        $id = (int) $m[1];
                        $hash = $hashById[$id] ?? null;

                        if (! is_string($hash) || strlen($hash) !== 64) {
                            return $m[0];
                        }

                        return '/storage/'.ContentAddressedPathGenerator::contentBasePath($hash);
                    },
                    $content,
                );

                if ($updated !== $content) {
                    DB::table($table)->where('id', $row->id)->update([$column => $updated]);
                    $rewritten++;
                }
            }
        }

        return $rewritten;
    }
}
