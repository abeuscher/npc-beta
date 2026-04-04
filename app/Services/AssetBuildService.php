<?php

namespace App\Services;

use App\Models\WidgetType;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AssetBuildService
{
    protected string $outputDir;
    protected string $manifestPath;

    public function __construct()
    {
        $this->outputDir = public_path('build/widgets');
        $this->manifestPath = $this->outputDir . '/manifest.json';
    }

    public function build(bool $debug = false): BuildResult
    {
        $startTime = microtime(true);

        $url = config('services.build_server.url');
        $apiKey = config('services.build_server.api_key');

        if (! $url || ! $apiKey) {
            return BuildResult::fail('Build server URL or API key not configured.');
        }

        // Collect sources
        $sources = $this->collectSources();

        // Generate content hash for cache-busting filenames
        $hash = substr(md5(json_encode($sources)), 0, 8);
        $cssFilename = "public-widgets-{$hash}.css";
        $jsFilename = "public-widgets-{$hash}.js";

        // Build request payload
        $payload = [
            'output' => [
                'css_filename' => $cssFilename,
                'js_filename' => $jsFilename,
            ],
            'sources' => $sources,
            'options' => [
                'minify' => true,
                'source_maps' => false,
            ],
        ];

        try {
            $response = Http::timeout(30)
                ->withToken($apiKey)
                ->post(rtrim($url, '/') . '/build', $payload);
        } catch (\Throwable $e) {
            Log::error('Build server request failed', ['error' => $e->getMessage()]);

            return BuildResult::fail('Build server unreachable: ' . $e->getMessage());
        }

        if (! $response->successful()) {
            $body = $response->json() ?? [];
            $msg = 'Build server returned HTTP ' . $response->status();

            if ($debug && ! empty($body['errors'])) {
                $msg .= "\n" . json_encode($body['errors'], JSON_PRETTY_PRINT);
            }

            Log::error('Build server error', ['status' => $response->status(), 'body' => $body]);

            return BuildResult::fail($msg);
        }

        $body = $response->json();

        if (empty($body['success'])) {
            $msg = 'Build failed.';
            if ($debug && ! empty($body['errors'])) {
                $msg .= "\n" . json_encode($body['errors'], JSON_PRETTY_PRINT);
            }

            return BuildResult::fail($msg);
        }

        // Validate response structure
        if (empty($body['files']['css']['content']) && empty($body['files']['js']['content'])) {
            return BuildResult::fail('Build server returned no file content.');
        }

        // Ensure output directory exists
        if (! File::isDirectory($this->outputDir)) {
            File::makeDirectory($this->outputDir, 0755, true);
        }

        // Write bundles
        $cssSize = 0;
        $jsSize = 0;

        if (! empty($body['files']['css']['content'])) {
            $cssContent = base64_decode($body['files']['css']['content']);
            if ($cssContent === false) {
                return BuildResult::fail('Failed to decode CSS bundle.');
            }
            $cssPath = $this->outputDir . '/' . $cssFilename;
            // Validate filename is safe
            if (basename($cssFilename) !== $cssFilename) {
                return BuildResult::fail('Invalid CSS filename.');
            }
            File::put($cssPath, $cssContent);
            $cssSize = strlen($cssContent);
        }

        if (! empty($body['files']['js']['content'])) {
            $jsContent = base64_decode($body['files']['js']['content']);
            if ($jsContent === false) {
                return BuildResult::fail('Failed to decode JS bundle.');
            }
            $jsPath = $this->outputDir . '/' . $jsFilename;
            if (basename($jsFilename) !== $jsFilename) {
                return BuildResult::fail('Invalid JS filename.');
            }
            File::put($jsPath, $jsContent);
            $jsSize = strlen($jsContent);
        }

        // Write manifest
        $manifest = [
            'css' => $cssSize > 0 ? $cssFilename : null,
            'js' => $jsSize > 0 ? $jsFilename : null,
            'built_at' => now()->toIso8601String(),
        ];
        File::put($this->manifestPath, json_encode($manifest, JSON_PRETTY_PRINT));

        // Clean up old bundles
        $this->cleanOldBundles($cssFilename, $jsFilename);

        $elapsed = round((microtime(true) - $startTime) * 1000);

        Log::info('Public asset build complete', [
            'css' => $cssFilename,
            'js' => $jsFilename,
            'css_size' => $cssSize,
            'js_size' => $jsSize,
            'build_time_ms' => $elapsed,
        ]);

        return BuildResult::success($cssFilename, $jsFilename, $cssSize, $jsSize, $elapsed);
    }

    protected function collectSources(): array
    {
        $scss = [];
        $css = [];
        $js = [];

        // Site-level SCSS partials — concatenated into a single source so
        // variables are available to all subsequent rules. The build server
        // compiles each SCSS entry individually, so we send one combined file.
        $partialOrder = [
            '_variables.scss',
            '_base.scss',
            '_layout.scss',
            '_grid.scss',
            '_forms.scss',
            '_controls.scss',
            '_buttons.scss',
            '_icons.scss',
            '_media.scss',
            '_custom.scss',
        ];

        $combined = '';
        foreach ($partialOrder as $partial) {
            $path = resource_path('scss/' . $partial);
            if (file_exists($path)) {
                $content = file_get_contents($path);
                // Strip @use "variables" lines — variables are included inline above
                $content = preg_replace('/@use\s+"variables"\s+as\s+\*;\s*\n?/', '', $content);
                $combined .= "// ── {$partial} ──\n" . $content . "\n";
            }
        }

        if ($combined) {
            $scss[] = [
                'path' => 'theme/public.scss',
                'content' => $combined,
            ];
        }

        // Widget CSS/JS from widget type records
        $widgetTypes = WidgetType::whereNotNull('css')
            ->orWhereNotNull('js')
            ->get(['handle', 'css', 'js']);

        foreach ($widgetTypes as $wt) {
            if ($wt->css) {
                $css[] = [
                    'path' => 'widgets/' . $wt->handle . '/style.css',
                    'content' => $wt->css,
                ];
            }
            if ($wt->js) {
                $js[] = [
                    'path' => 'widgets/' . $wt->handle . '/script.js',
                    'content' => $wt->js,
                ];
            }
        }

        // Widget SCSS from asset paths
        $widgetTypesWithAssets = WidgetType::whereNotNull('assets')->get(['handle', 'assets']);
        foreach ($widgetTypesWithAssets as $wt) {
            $assets = $wt->assets ?? [];
            foreach ($assets['scss'] ?? [] as $scssPath) {
                $fullPath = base_path($scssPath);
                if (file_exists($fullPath)) {
                    $content = file_get_contents($fullPath);
                    $content = preg_replace('/@use\s+"variables"\s+as\s+\*;\s*\n?/', '', $content);
                    $scss[] = [
                        'path' => 'widgets/' . $wt->handle . '/' . basename($scssPath),
                        'content' => $content,
                    ];
                }
            }
            foreach ($assets['css'] ?? [] as $cssPath) {
                if (str_starts_with($cssPath, 'http')) {
                    continue; // External URLs stay as <link> tags
                }
                $fullPath = base_path($cssPath);
                if (file_exists($fullPath)) {
                    $css[] = [
                        'path' => 'widgets/' . $wt->handle . '/' . basename($cssPath),
                        'content' => file_get_contents($fullPath),
                    ];
                }
            }
            foreach ($assets['js'] ?? [] as $jsPath) {
                if (str_starts_with($jsPath, 'http')) {
                    continue;
                }
                $fullPath = base_path($jsPath);
                if (file_exists($fullPath)) {
                    $js[] = [
                        'path' => 'widgets/' . $wt->handle . '/' . basename($jsPath),
                        'content' => file_get_contents($fullPath),
                    ];
                }
            }
        }

        return [
            'scss' => $scss,
            'css' => $css,
            'js' => $js,
        ];
    }

    protected function cleanOldBundles(string $currentCss, string $currentJs): void
    {
        if (! File::isDirectory($this->outputDir)) {
            return;
        }

        foreach (File::files($this->outputDir) as $file) {
            $name = $file->getFilename();

            // Keep the manifest and current bundles
            if ($name === 'manifest.json' || $name === $currentCss || $name === $currentJs) {
                continue;
            }

            // Only delete files that match our naming pattern
            if (preg_match('/^public-widgets-[a-f0-9]{8}\.(css|js)$/', $name)) {
                File::delete($file->getPathname());
            }
        }
    }
}
