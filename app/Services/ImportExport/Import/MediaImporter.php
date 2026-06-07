<?php

namespace App\Services\ImportExport\Import;

use App\Services\ImportExport\ImportLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Imports the payload.media pass — a posture-B ID-preserving standalone media
 * seed (media-portability draft decisions #3/#4/#6). Raw explicit-id insert
 * that preserves content_hash so the bytes land at the row's content-addressed
 * path (session 320). Runs inside the import transaction; returns the ids it
 * actually seeded so the orchestrator can enqueue conversion regeneration.
 */
class MediaImporter
{
    /**
     * @param  array<int, array<string, mixed>>  $descriptors
     * @return array<int, int>
     */
    public function import(array $descriptors, ImportLog $log, BundleMediaArchive $archive): array
    {
        $targetDisk = config('media-library.disk_name', 'public');
        $seeded     = [];

        foreach ($descriptors as $desc) {
            $id       = $desc['id'] ?? null;
            $fileName = $desc['file_name'] ?? null;
            $srcPath  = $desc['path'] ?? null;

            if (! $id || ! $fileName || ! $srcPath) {
                $log->warning('Media descriptor missing id/file_name/path, skipped.');

                continue;
            }

            // Defence in depth: the path is both the byte lookup and the write
            // target, so refuse traversal even though it came from our exporter.
            if (str_contains($srcPath, '..') || str_starts_with($srcPath, '/')) {
                $log->warning("Media #{$id}: unsafe path '{$srcPath}', skipped.");

                continue;
            }

            // Collision policy: identical → idempotent skip; divergent → warn
            // and skip (operator re-exports from a clean source). No clobber.
            $existing = DB::table('media')->where('id', $id)->first();
            if ($existing) {
                if (($existing->uuid ?? null) === ($desc['uuid'] ?? null)
                    && $existing->file_name === $fileName) {
                    $log->info("Media #{$id}: already seeded, skipped.");
                } else {
                    $log->warning("Media #{$id}: id exists with a different uuid/file_name on this install — skipped (export from a clean source to resolve).");
                }

                continue;
            }

            // Resolve bytes archive-first, then the source disk.
            $bytes = null;
            $archiveAbs = $archive->archiveFile($srcPath);
            if ($archiveAbs !== null) {
                $bytes = file_get_contents($archiveAbs);
            } elseif (! str_contains($srcPath, '..') && ! str_starts_with($srcPath, '/')
                && Storage::disk($desc['disk'] ?? 'public')->exists($srcPath)) {
                $bytes = Storage::disk($desc['disk'] ?? 'public')->get($srcPath);
            }

            if ($bytes === null || $bytes === false) {
                $log->warning("Media #{$id}: file bytes not found in the bundle or on disk, skipped.");

                continue;
            }

            // Orphan-owner policy: park the media even if its original owner
            // row is absent on this install (resolution is path-based).
            $modelType = $desc['model_type'] ?? null;
            $modelId   = $desc['model_id'] ?? null;
            if (is_string($modelType) && class_exists($modelType) && $modelId !== null) {
                try {
                    if (! $modelType::query()->whereKey($modelId)->exists()) {
                        $log->info("Media #{$id}: owner {$modelType}#{$modelId} absent — parked.");
                    }
                } catch (\Throwable) {
                    // Owner check is best-effort/informational only.
                }
            }

            DB::table('media')->insert([
                'id'                    => $id,
                'uuid'                  => $desc['uuid'] ?? (string) Str::uuid(),
                'model_type'            => $modelType ?? '',
                'model_id'              => $modelId ?? 0,
                'collection_name'       => $desc['collection_name'] ?? 'default',
                'name'                  => $desc['name'] ?? pathinfo($fileName, PATHINFO_FILENAME),
                'file_name'             => $fileName,
                'mime_type'             => $desc['mime_type'] ?? null,
                'disk'                  => $targetDisk,
                'conversions_disk'      => $desc['conversions_disk'] ?? $targetDisk,
                'size'                  => $desc['size'] ?? strlen($bytes),
                'content_hash'          => $desc['content_hash'] ?? null,
                'manipulations'         => json_encode($desc['manipulations'] ?? []),
                'custom_properties'     => json_encode($desc['custom_properties'] ?? []),
                'generated_conversions' => json_encode([]),
                'responsive_images'     => json_encode($desc['responsive_images'] ?? []),
                'order_column'          => $desc['order_column'] ?? null,
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);

            // Write the original bytes at the path the descriptor declares — the
            // content-addressed location the seeded row (carrying the same
            // content_hash) resolves to, or the legacy id path when no hash.
            Storage::disk($targetDisk)->put($srcPath, $bytes);
            $seeded[] = (int) $id;
        }

        if (! empty($seeded)) {
            // Keep autoincrement ahead of the explicit ids we just inserted.
            DB::statement(
                "SELECT setval(pg_get_serial_sequence('media', 'id'), GREATEST((SELECT COALESCE(MAX(id), 1) FROM media), 1))"
            );
        }

        return $seeded;
    }
}
