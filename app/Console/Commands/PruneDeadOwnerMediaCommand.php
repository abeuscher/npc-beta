<?php

namespace App\Console\Commands;

use App\Services\DataHygieneAudit;
use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Deletes dead-owner media rows — `media` rows whose polymorphic owner model no
 * longer exists. These are the residue a query-builder mass delete leaves when
 * it bypasses Spatie's per-model teardown (pre-352 the scrub wipe did exactly
 * this to event/product media; see RandomDataGenerator::wipe).
 *
 * This is the cleanup half of DataHygieneAudit's `dead_owner_media` category.
 * Spatie's own `media-library:clean --delete-orphaned` CANNOT do this job on
 * this schema: its orphan check joins `media.model_id` (varchar) against the
 * owner PK (uuid), and Postgres rejects the varchar=uuid comparison. This
 * command instead reuses DataHygieneAudit::deadOwnerMedia() — which compares
 * stringified ids in PHP and so works — then deletes each Media model so
 * Spatie's refcounted ContentAddressedFileRemover fires and the files go too.
 *
 * Destructive → dry-run report by default; --force to delete. A soft-deleted
 * (recoverable) owner counts as present, so its media is never touched. Run
 * `media:prune-orphans --force` afterwards to sweep any directories freed.
 */
class PruneDeadOwnerMediaCommand extends Command
{
    protected $signature = 'media:prune-dead-owner {--force : Delete the dead-owner media rows (default is a dry-run report)}';

    protected $description = 'Remove media rows whose polymorphic owner model is gone (Spatie media-library:clean --delete-orphaned cannot, on this schema).';

    public function handle(DataHygieneAudit $audit): int
    {
        $dead = $audit->deadOwnerMedia();

        if ($dead->isEmpty()) {
            $this->info('No dead-owner media rows found.');

            return self::SUCCESS;
        }

        $summary = sprintf(
            '%d dead-owner media row%s',
            $dead->count(),
            $dead->count() === 1 ? '' : 's',
        );

        if (! $this->option('force')) {
            $this->warn("[dry-run] {$summary} would be deleted. Re-run with --force to delete.");

            return self::SUCCESS;
        }

        $deleted = 0;
        foreach ($dead as $row) {
            // Re-fetch the full model so the file teardown has disk/conversions.
            if (Media::find($row->id)?->delete()) {
                $deleted++;
            }
        }

        $this->info("Pruned {$deleted} dead-owner media row".($deleted === 1 ? '' : 's').'.');
        $this->comment('Run media:prune-orphans --force to sweep any content-addressed directories this freed.');

        return self::SUCCESS;
    }
}
