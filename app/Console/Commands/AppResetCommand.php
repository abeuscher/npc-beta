<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class AppResetCommand extends Command
{
    protected $signature = 'app:reset';

    protected $description = 'Wipe the database and on-disk media tree, then run migrate:fresh --seed. Dev-only — refuses to run on production.';

    public function handle(): int
    {
        if ($this->laravel->environment('production')) {
            $this->error('app:reset cannot run in production. This is a dev-only command.');

            return self::FAILURE;
        }

        Artisan::call('migrate:fresh', [
            '--seed' => true,
            '--force' => true,
        ], $this->getOutput());

        $disk = Storage::disk('public');

        foreach ($disk->directories() as $dir) {
            $disk->deleteDirectory($dir);
        }

        foreach ($disk->files() as $file) {
            $disk->delete($file);
        }

        $tempPath = storage_path('media-library/temp');

        if (File::isDirectory($tempPath)) {
            File::cleanDirectory($tempPath);
        }

        $this->info('Reset complete. Database reseeded, storage cleared.');

        return self::SUCCESS;
    }
}
