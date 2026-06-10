<?php

namespace App\Console\Commands;

use App\Services\DataHygieneAudit;
use Illuminate\Console\Command;

/**
 * Force-deletes orphaned event landing pages — pages of type 'event' that no
 * Event references via landing_page_id.
 *
 * These accumulate when events are removed by a path that bypasses the
 * per-model delete cascade (EventObserver::deleted) — historically the scrub
 * wipe (RandomDataGenerator::wipe), which mass-deletes scrub events but left
 * their landing pages behind because the pages were tagged source='human'
 * rather than inheriting the event's scrub source. Both leaks are now fixed at
 * the source; this command clears the pre-existing orphans (and is a standing
 * repair tool for any future bulk-delete path that misses its pages).
 *
 * Each page is force-deleted so PageObserver::deleting tears down its widgets +
 * layouts too. Destructive → dry-run report by default; --force to delete.
 *
 * Detection (which pages are orphans) lives in DataHygieneAudit so the audit
 * (`app:data-hygiene`) and this cleanup command share one definition.
 */
class PruneOrphanEventPagesCommand extends Command
{
    protected $signature = 'pages:prune-orphan-events {--force : Delete the orphaned event landing pages (default is a dry-run report)}';

    protected $description = "Remove type='event' landing pages that no Event references via landing_page_id.";

    public function handle(DataHygieneAudit $audit): int
    {
        $orphans = $audit->orphanEventPages()->get();

        if ($orphans->isEmpty()) {
            $this->info('No orphan event landing pages found.');

            return self::SUCCESS;
        }

        $summary = sprintf(
            '%d orphan event landing page%s',
            $orphans->count(),
            $orphans->count() === 1 ? '' : 's',
        );

        if (! $this->option('force')) {
            $this->warn("[dry-run] {$summary} would be deleted. Re-run with --force to delete.");

            return self::SUCCESS;
        }

        foreach ($orphans as $page) {
            $page->forceDelete();
        }

        $this->info("Pruned {$summary}.");

        return self::SUCCESS;
    }
}
