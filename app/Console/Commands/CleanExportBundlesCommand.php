<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanExportBundlesCommand extends Command
{
    protected $signature = 'exports:clean {--hours=48 : Delete bundle-export artifacts older than this many hours}';

    protected $description = 'Reap queued bundle-export artifacts under exports/bundles older than the TTL. Backups are reaped separately by backup:clean.';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $disk  = Storage::disk('local');
        $base  = 'exports/bundles';

        if (! $disk->exists($base)) {
            $this->info('No export bundles directory; nothing to reap.');

            return self::SUCCESS;
        }

        $cutoff  = now()->subHours($hours)->getTimestamp();
        $deleted = 0;

        foreach ($disk->directories($base) as $dir) {
            $files  = $disk->allFiles($dir);
            $newest = 0;

            foreach ($files as $file) {
                $newest = max($newest, $disk->lastModified($file));
            }

            if ($files === [] || $newest < $cutoff) {
                $disk->deleteDirectory($dir);
                $deleted++;
            }
        }

        $this->info("Reaped {$deleted} export bundle artifact(s) older than {$hours}h.");

        return self::SUCCESS;
    }
}
