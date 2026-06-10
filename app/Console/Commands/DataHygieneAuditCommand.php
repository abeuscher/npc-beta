<?php

namespace App\Console\Commands;

use App\Services\DataHygieneAudit;
use Illuminate\Console\Command;

/**
 * Read-only audit of the derived/cruft data on the box it runs on (Fleet Data
 * Hygiene track, session 352 — the detection half). Reports four categories:
 * orphan event landing pages, residual source=scrub_data records, orphan
 * content-addressed media directories, and media rows whose owner is gone.
 *
 * Default output is a count-only summary — the same aggregate the Phase-2
 * count-only `/api/health` subcheck will read (safe, non-PII). `--deep`
 * additionally lists the actual offending records (node-local; the records
 * never cross the FM wire). It DETECTS only — cleanup stays in
 * `pages:prune-orphan-events` and `media:prune-orphans` (both --force to act).
 */
class DataHygieneAuditCommand extends Command
{
    protected $signature = 'app:data-hygiene {--deep : Additionally list the actual offending records (node-local, not just counts)}';

    protected $description = 'Audit accumulated derived/cruft data (orphan pages, scrub residue, orphan + dead-owner media). Read-only.';

    public function handle(DataHygieneAudit $audit): int
    {
        $counts = $audit->counts();
        $total  = array_sum($counts);

        $this->newLine();
        $this->info('Data hygiene audit — counts');
        $this->table(['Category', 'Count'], [
            ['Orphan event pages',          $counts['orphan_event_pages']],
            ['Residual scrub_data records', $counts['scrub_records']],
            ['Orphan media directories',    $counts['orphan_media_dirs']],
            ['Dead-owner media rows',       $counts['dead_owner_media']],
            ['— total —',                   $total],
        ]);

        if ($total === 0) {
            $this->info('Clean — no derived/cruft data detected.');

            return self::SUCCESS;
        }

        // The per-table scrub breakdown is useful context whenever residue exists.
        $scrub = array_filter($audit->scrubBreakdown());
        if ($scrub !== []) {
            $this->newLine();
            $this->info('Residual scrub_data by table:');
            $this->table(
                ['Table', 'Count'],
                collect($scrub)->map(fn ($n, $t) => [$t, $n])->values()->all(),
            );
        }

        if ($this->option('deep')) {
            $this->renderDeep($audit);
        } else {
            $this->newLine();
            $this->comment('Re-run with --deep to list the offending records. Clean up with:');
            $this->comment('  php artisan pages:prune-orphan-events --force   (orphan event pages)');
            $this->comment('  php artisan media:prune-dead-owner --force      (dead-owner media rows)');
            $this->comment('  php artisan media:prune-orphans --force         (orphan media dirs + files freed above)');
            $this->comment('  Residual scrub_data is cleared by the admin Random Data → Wipe action.');
        }

        return self::SUCCESS;
    }

    private function renderDeep(DataHygieneAudit $audit): void
    {
        $pages = $audit->orphanEventPages()->get(['id', 'title', 'slug', 'deleted_at']);
        if ($pages->isNotEmpty()) {
            $this->newLine();
            $this->info('Orphan event pages:');
            $this->table(
                ['ID', 'Title', 'Slug', 'Soft-deleted'],
                $pages->map(fn ($p) => [$p->id, $p->title, $p->slug, $p->deleted_at ? 'yes' : 'no'])->all(),
            );
        }

        $dirs = $audit->orphanMediaDirectories();
        if ($dirs !== []) {
            $this->newLine();
            $this->info('Orphan media directories:');
            $this->table(
                ['Path', 'Bytes'],
                collect($dirs)->map(fn ($d) => [$d['path'], $d['bytes']])->all(),
            );
        }

        $media = $audit->deadOwnerMedia();
        if ($media->isNotEmpty()) {
            $this->newLine();
            $this->info('Dead-owner media rows:');
            $this->table(
                ['Media ID', 'Owner type', 'Owner ID', 'Collection', 'File'],
                $media->map(fn ($m) => [$m->id, $m->model_type, $m->model_id, $m->collection_name, $m->file_name])->all(),
            );
        }
    }
}
