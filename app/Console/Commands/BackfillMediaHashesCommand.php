<?php

namespace App\Console\Commands;

use App\Services\Media\MediaContentHasher;
use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class BackfillMediaHashesCommand extends Command
{
    protected $signature = 'media:backfill-hashes';

    protected $description = 'Compute and store the content_hash for media rows that do not yet have one.';

    public function handle(MediaContentHasher $hasher): int
    {
        $hashed = 0;
        $skipped = 0;

        Media::query()
            ->whereNull('content_hash')
            ->orderBy('id')
            ->chunkById(200, function ($media) use ($hasher, &$hashed, &$skipped): void {
                foreach ($media as $item) {
                    $hasher->persist($item);

                    if ($item->content_hash !== null) {
                        $hashed++;
                    } else {
                        $skipped++;
                    }
                }
            });

        $this->info("Hashed {$hashed} media; skipped {$skipped} (file unreadable or missing).");

        return self::SUCCESS;
    }
}
