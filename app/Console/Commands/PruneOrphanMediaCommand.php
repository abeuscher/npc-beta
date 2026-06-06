<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Sweeps the content-addressed media tree (cas/<hash[0:2]>/<hash>/) for
 * directories whose content_hash is referenced by no live `media` row, and
 * deletes them. These accumulate because the refcounted file remover
 * (ContentAddressedFileRemover) only runs on a model `->delete()`; bulk row
 * clears that bypass Eloquent events — migrate:fresh, demo:reset, scrub
 * regeneration, Playwright resetDatabase() — truncate the table without firing
 * it, orphaning the files. spatie's `media-library:clean` does not cover this
 * (it prunes orphan rows + stale conversions, not whole tree dirs with no row).
 *
 * Destructive, so a dry-run report is the default; the delete pass requires an
 * explicit --force. Local-only growth (the demo droplet restores, never seeds),
 * so this is on-demand, deliberately not wired into any deploy/demo path.
 */
class PruneOrphanMediaCommand extends Command
{
    protected $signature = 'media:prune-orphans {--force : Delete the orphaned directories (default is a dry-run report)}';

    protected $description = 'Remove content-addressed media directories (cas/<hash>/) referenced by no live media row.';

    public function handle(): int
    {
        $disk    = Storage::disk(config('media-library.disk_name', 'public'));
        $casRoot = $this->casRoot();

        // The set of hashes still referenced by a live row. Built once from the
        // index; a directory whose name is not in it is an orphan.
        $live = Media::query()
            ->whereNotNull('content_hash')
            ->pluck('content_hash')
            ->flip();

        $orphans = [];
        $bytes   = 0;

        foreach ($disk->directories($casRoot) as $shard) {
            foreach ($disk->directories($shard) as $hashDir) {
                $hash = basename($hashDir);

                // Only ever touch well-formed content-hash directories; leave
                // anything else in the tree (legacy id dirs never live here, but
                // be conservative) untouched.
                if (strlen($hash) !== 64 || ! ctype_xdigit($hash)) {
                    continue;
                }

                if ($live->has($hash)) {
                    continue;
                }

                $orphans[] = $hashDir;

                foreach ($disk->allFiles($hashDir) as $file) {
                    $bytes += $disk->size($file);
                }
            }
        }

        if ($orphans === []) {
            $this->info('No orphan media directories found.');

            return self::SUCCESS;
        }

        $summary = sprintf(
            '%d orphan media director%s (%s)',
            count($orphans),
            count($orphans) === 1 ? 'y' : 'ies',
            $this->humanBytes($bytes),
        );

        if (! $this->option('force')) {
            $this->warn("[dry-run] {$summary} would be deleted. Re-run with --force to delete.");

            return self::SUCCESS;
        }

        foreach ($orphans as $hashDir) {
            $disk->deleteDirectory($hashDir);
        }

        // Drop shard directories left empty by the sweep so the tree stays tidy.
        foreach ($disk->directories($casRoot) as $shard) {
            if ($disk->allFiles($shard) === [] && $disk->directories($shard) === []) {
                $disk->deleteDirectory($shard);
            }
        }

        $this->info("Pruned {$summary}.");

        return self::SUCCESS;
    }

    private function casRoot(): string
    {
        $prefix = (string) config('media-library.prefix', '');

        return ($prefix !== '' ? rtrim($prefix, '/').'/' : '').'cas';
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i     = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return sprintf('%.1f %s', $value, $units[$i]);
    }
}
