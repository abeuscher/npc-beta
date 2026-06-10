<?php

namespace App\Services;

use App\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Read-only audit of the derived/cruft data accumulated on the box it runs on —
 * the shared core of the Fleet Data Hygiene track (session 352). Build-once:
 * one computation powers three consumers — the `app:data-hygiene` CLI report
 * (now), the count-only `/api/health` subcheck (Phase 2, reads counts() only),
 * and the gated deep mode (records, node-local). It DETECTS; it never deletes —
 * cleanup stays in `pages:prune-orphan-events` / `media:prune-orphans`, which now
 * share this class's detection primitives rather than re-deriving them.
 *
 * Four categories:
 *   - orphan_event_pages — type=event landing pages no Event references
 *   - scrub_records      — rows still tagged source=scrub_data (pure cruft on a
 *                          real box; reuses RandomDataGenerator::scrubCounts())
 *   - orphan_media_dirs  — content-addressed dirs referenced by no live row
 *   - dead_owner_media   — media rows whose owning model is gone (hard-deleted)
 *
 * PRIVACY BOUNDARY (track-level, governs the later FM phase): counts() returns
 * aggregate counts only — the non-PII signal that may cross the FM wire in
 * Phase 2. records() returns actual rows and is node-local only (the CLI --deep
 * mode); it is never exposed to Fleet Manager.
 */
class DataHygieneAudit
{
    public function __construct(private readonly RandomDataGenerator $generator) {}

    // ── Count-only signal (safe, non-PII — the Phase-2 health subcheck reads this) ──

    /**
     * Aggregate count per category. The only shape that may cross the FM wire.
     *
     * @return array<string,int>
     */
    public function counts(): array
    {
        return [
            'orphan_event_pages' => $this->orphanEventPages()->count(),
            'scrub_records'      => array_sum($this->scrubBreakdown()),
            'orphan_media_dirs'  => count($this->orphanMediaDirectories()),
            'dead_owner_media'   => $this->deadOwnerMedia()->count(),
        ];
    }

    /** Total cruft across every category — the single number a health check can threshold. */
    public function total(): int
    {
        return array_sum($this->counts());
    }

    /**
     * Per-table source=scrub_data counts (CLI detail). Reuses the generator's
     * own definition of "what is scrub data" so the two never drift.
     *
     * @return array<string,int>
     */
    public function scrubBreakdown(): array
    {
        return $this->generator->scrubCounts();
    }

    // ── Detection primitives — count() them for the signal, get() them for --deep ──

    /**
     * type=event landing pages that no Event references via landing_page_id.
     * The single source of truth for "orphan event page" — `pages:prune-orphan-events`
     * consumes this same query.
     */
    public function orphanEventPages(): Builder
    {
        return Page::query()
            ->withTrashed()
            ->where('type', 'event')
            ->whereDoesntHave('event');
    }

    /**
     * Content-addressed media directories (cas/<hash>/) whose hash is referenced
     * by no live media row — orphaned when a bulk row clear bypasses the
     * refcounted file remover. The single source of truth for "orphan media
     * directory"; `media:prune-orphans` consumes this and deletes the paths.
     *
     * @return array<int,array{path:string,bytes:int}>
     */
    public function orphanMediaDirectories(): array
    {
        $disk    = Storage::disk(config('media-library.disk_name', 'public'));
        $casRoot = $this->casRoot();

        // Hashes still referenced by a live row; a dir whose name is absent is orphan.
        $live = Media::query()
            ->whereNotNull('content_hash')
            ->pluck('content_hash')
            ->flip();

        $orphans = [];

        foreach ($disk->directories($casRoot) as $shard) {
            foreach ($disk->directories($shard) as $hashDir) {
                $hash = basename($hashDir);

                // Only ever consider well-formed content-hash directories.
                if (strlen($hash) !== 64 || ! ctype_xdigit($hash)) {
                    continue;
                }

                if ($live->has($hash)) {
                    continue;
                }

                $bytes = 0;
                foreach ($disk->allFiles($hashDir) as $file) {
                    $bytes += $disk->size($file);
                }

                $orphans[] = ['path' => $hashDir, 'bytes' => $bytes];
            }
        }

        return $orphans;
    }

    /**
     * Media rows whose owning model no longer exists (hard-deleted) — the exact
     * residue a query-builder mass delete leaves behind (the pre-352 scrub-wipe
     * bug). A soft-deleted owner still counts as present: its row exists, so
     * existence is checked against the raw table.
     *
     * @return Collection<int,Media>
     */
    public function deadOwnerMedia(): Collection
    {
        $dead = collect();

        Media::query()
            ->select('id', 'model_type', 'model_id', 'collection_name', 'file_name')
            ->get()
            ->groupBy('model_type')
            ->each(function (Collection $group, string $type) use ($dead) {
                $class = Relation::getMorphedModel($type) ?: $type;

                // An owner class that no longer exists in code → every row is dead.
                if (! class_exists($class)) {
                    $group->each(fn ($media) => $dead->push($media));

                    return;
                }

                $instance = new $class;

                // Pull the owner table's ids and compare in PHP rather than
                // emitting a SQL `media.model_id = <owner>.id` comparison:
                // media.model_id is varchar (it has to hold keys for mixed-PK
                // owner models) while uuid-keyed owners have a uuid PK, and
                // Postgres rejects the varchar=uuid join without a cast. That
                // same mismatch is what breaks Spatie's media-library:clean
                // --delete-orphaned on this schema; comparing stringified ids
                // here sidesteps it entirely. Owner tables that back media are
                // bounded, so pulling their ids is cheap.
                $aliveIds = DB::table($instance->getTable())
                    ->pluck($instance->getKeyName())
                    ->map(fn ($id) => (string) $id)
                    ->flip();

                $group->each(function ($media) use ($aliveIds, $dead) {
                    if (! $aliveIds->has((string) $media->model_id)) {
                        $dead->push($media);
                    }
                });
            });

        return $dead;
    }

    /** Root of the content-addressed media tree on the configured disk. */
    public function casRoot(): string
    {
        $prefix = (string) config('media-library.prefix', '');

        return ($prefix !== '' ? rtrim($prefix, '/').'/' : '').'cas';
    }
}
