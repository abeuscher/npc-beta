<?php

namespace App\Console\Commands;

use App\Services\AssetBuildService;
use Illuminate\Console\Command;

class BuildPublicAssets extends Command
{
    protected $signature = 'build:public {--debug}';

    protected $description = 'Build the public CSS and JS bundles via the build server';

    public function handle(AssetBuildService $service): int
    {
        $this->info('Building public assets…');

        $result = $service->build(debug: $this->option('debug'));

        if (! $result->success) {
            $this->error($result->message);

            return self::FAILURE;
        }

        $this->info("CSS: {$result->cssFilename} ({$this->formatBytes($result->cssSize)})");
        $this->info("JS:  {$result->jsFilename} ({$this->formatBytes($result->jsSize)})");

        // Report library bundles from manifest
        $manifest = json_decode(@file_get_contents(public_path('build/widgets/manifest.json')) ?: '{}', true);
        $libs = $manifest['libs'] ?? [];
        if ($libs) {
            $this->info('Library bundles: ' . implode(', ', array_keys($libs)));
        }

        $this->info("Built in {$result->buildTimeMs}ms");

        return self::SUCCESS;
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        return round($bytes / 1024, 1) . ' KB';
    }
}
