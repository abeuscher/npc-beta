<?php

namespace Database\Seeders;

use App\Models\SampleImage;
use Illuminate\Database\Seeder;

class SampleImageLibrarySeeder extends Seeder
{
    public function run(): void
    {
        foreach (SampleImage::CATEGORIES as $category) {
            $this->syncCategory($category);
        }
    }

    private function syncCategory(string $category): void
    {
        $host   = SampleImage::forCategory($category);
        $dir    = resource_path("sample-images/{$category}");
        $onDisk = $this->filesOnDisk($dir);

        $existing = $host->getMedia($category)->keyBy('file_name');
        $diskMap  = collect($onDisk)->keyBy(fn ($path) => basename($path));

        // Remove media whose source file no longer exists on disk.
        foreach ($existing as $fileName => $media) {
            if (! $diskMap->has($fileName)) {
                $media->delete();
            }
        }

        // Add files on disk that aren't already in the collection.
        foreach ($diskMap as $fileName => $path) {
            if ($existing->has($fileName)) {
                continue;
            }

            try {
                $host->addMedia($path)
                    ->preservingOriginal()
                    ->usingFileName($fileName)
                    ->toMediaCollection($category);
            } catch (\Throwable $e) {
                $this->command?->warn("Could not ingest {$path}: {$e->getMessage()}");
            }
        }

        $this->command?->info("Sample images [{$category}]: {$diskMap->count()} on disk.");
    }

    /**
     * @return array<int, string>
     */
    private function filesOnDisk(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $files = [];
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_file($path)) {
                $files[] = $path;
            }
        }

        return $files;
    }
}
