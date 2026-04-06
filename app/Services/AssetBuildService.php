<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Models\WidgetType;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AssetBuildService
{
    protected string $outputDir;
    protected string $libsDir;
    protected string $manifestPath;

    /**
     * JS source for each library bundle. Each entry produces a self-contained
     * script that registers the expected globals without importing Alpine.
     */
    protected const LIBRARY_SOURCES = [
        'swiper' => [
            'js' => <<<'JS'
import Swiper from 'swiper';
import { Navigation, Pagination, Autoplay, EffectFade, EffectCoverflow, FreeMode } from 'swiper/modules';
window.Swiper = Swiper;
window.SwiperModules = { Navigation, Pagination, Autoplay, EffectFade, EffectCoverflow, FreeMode };
JS,
            'scss' => [
                'node_modules/swiper/swiper.scss',
                'node_modules/swiper/modules/navigation.scss',
                'node_modules/swiper/modules/pagination.scss',
                'node_modules/swiper/modules/effect-fade.scss',
            ],
        ],
        'chart.js' => [
            'js' => <<<'JS'
import Chart from 'chart.js/auto';
window.Chart = Chart;
JS,
        ],
        'jcalendar' => [
            'js' => <<<'JS'
import { calendarJs } from 'jcalendar.js/dist/calendar.export.js';
window.calendarJs = calendarJs;
JS,
            'css' => ['node_modules/jcalendar.js/dist/calendar.js.min.css'],
        ],
    ];

    public function __construct()
    {
        $this->outputDir = public_path('build/widgets');
        $this->libsDir = public_path('build/libs');
        $this->manifestPath = $this->outputDir . '/manifest.json';
    }

    public function build(bool $debug = false): BuildResult
    {
        $startTime = microtime(true);

        $url = SiteSetting::get('build_server_url', '') ?: config('services.build_server.url');
        $apiKey = SiteSetting::get('build_server_api_key', '') ?: config('services.build_server.api_key');

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

        // Build per-library bundles for admin preview
        $libs = $this->buildLibraryBundles($url, $apiKey, $debug);

        // Write manifest
        $manifest = [
            'css' => $cssSize > 0 ? $cssFilename : null,
            'js' => $jsSize > 0 ? $jsFilename : null,
            'libs' => $libs,
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

    /**
     * Build a self-contained JS (and optionally CSS) bundle for each library.
     *
     * @return array<string, array{js?: string, css?: string}>  lib identifier → asset paths
     */
    protected function buildLibraryBundles(string $url, string $apiKey, bool $debug): array
    {
        if (! File::isDirectory($this->libsDir)) {
            File::makeDirectory($this->libsDir, 0755, true);
        }

        $libs = [];

        foreach (self::LIBRARY_SOURCES as $name => $libDef) {
            $baseName = str_replace('.', '', $name); // chart.js → chartjs
            $jsFilename = $baseName . '.js';
            $cssFilename = $baseName . '.css';

            // Collect SCSS sources from node_modules
            $scssSources = [];
            foreach ($libDef['scss'] ?? [] as $scssPath) {
                $fullPath = base_path($scssPath);
                if (file_exists($fullPath)) {
                    $scssSources[] = [
                        'path' => "libs/{$name}/" . basename($scssPath),
                        'content' => file_get_contents($fullPath),
                    ];
                }
            }

            // Collect CSS sources from node_modules
            $cssSources = [];
            foreach ($libDef['css'] ?? [] as $cssPath) {
                $fullPath = base_path($cssPath);
                if (file_exists($fullPath)) {
                    $cssSources[] = [
                        'path' => "libs/{$name}/" . basename($cssPath),
                        'content' => file_get_contents($fullPath),
                    ];
                }
            }

            $payload = [
                'output' => [
                    'css_filename' => $cssFilename,
                    'js_filename' => $jsFilename,
                ],
                'sources' => [
                    'scss' => $scssSources,
                    'css' => $cssSources,
                    'js' => [
                        ['path' => "libs/{$name}/index.js", 'content' => $libDef['js']],
                    ],
                ],
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
                Log::warning("Library bundle build failed for {$name}", ['error' => $e->getMessage()]);
                continue;
            }

            if (! $response->successful()) {
                Log::warning("Library bundle build returned HTTP {$response->status()} for {$name}");
                continue;
            }

            $body = $response->json();

            if (empty($body['success'])) {
                if ($debug && ! empty($body['errors'])) {
                    Log::warning("Library bundle build errors for {$name}", ['errors' => $body['errors']]);
                }
                continue;
            }

            $libEntry = [];

            // Write JS bundle
            if (! empty($body['files']['js']['content'])) {
                $jsContent = base64_decode($body['files']['js']['content']);
                if ($jsContent !== false && strlen($jsContent) > 0) {
                    File::put($this->libsDir . '/' . $jsFilename, $jsContent);
                    $libEntry['js'] = '/build/libs/' . $jsFilename;
                    Log::info("Library JS built: {$name}", ['size' => strlen($jsContent)]);
                }
            }

            // Write CSS bundle
            if (! empty($body['files']['css']['content'])) {
                $cssContent = base64_decode($body['files']['css']['content']);
                if ($cssContent !== false && strlen($cssContent) > 0) {
                    File::put($this->libsDir . '/' . $cssFilename, $cssContent);
                    $libEntry['css'] = '/build/libs/' . $cssFilename;
                    Log::info("Library CSS built: {$name}", ['size' => strlen($cssContent)]);
                }
            }

            if (! empty($libEntry)) {
                $libs[$name] = $libEntry;
            }
        }

        return $libs;
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

        // Append button style overrides from Design System settings
        $buttonOverrides = $this->generateButtonOverrideCss();
        if ($buttonOverrides) {
            $combined .= "// ── button-overrides (from DB) ──\n" . $buttonOverrides . "\n";
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

    protected function generateButtonOverrideCss(): string
    {
        $styles = SiteSetting::get('button_styles');
        if (! $styles || ! is_array($styles)) {
            return '';
        }

        $radiusMap = [
            'sharp'            => '0',
            'slightly-rounded' => '0.25em',
            'rounded'          => '0.5em',
            'pill'             => '999px',
        ];

        $variantHandles = ['primary', 'secondary', 'text', 'destructive', 'link'];
        $lines = [":root {\n"];

        foreach ($variantHandles as $handle) {
            $v = $styles[$handle] ?? [];

            if (! empty($v['border_radius'])) {
                $lines[] = "    --btn-{$handle}-radius: " . ($radiusMap[$v['border_radius']] ?? '0.25em') . ";\n";
            }
            if (! empty($v['bg_color'])) {
                $lines[] = "    --btn-{$handle}-bg: {$v['bg_color']};\n";
            }
            if (! empty($v['text_color'])) {
                $lines[] = "    --btn-{$handle}-color: {$v['text_color']};\n";
            }
            if (! empty($v['border_color'])) {
                $lines[] = "    --btn-{$handle}-border-color: {$v['border_color']};\n";
            }
            if (! empty($v['border_width']) && $v['border_width'] !== '0') {
                $lines[] = "    --btn-{$handle}-border-width: {$v['border_width']};\n";
            }
            if (! empty($v['font_weight'])) {
                $lines[] = "    --btn-{$handle}-font-weight: {$v['font_weight']};\n";
            }
            if (! empty($v['text_transform']) && $v['text_transform'] !== 'none') {
                $lines[] = "    --btn-{$handle}-text-transform: {$v['text_transform']};\n";
            }
        }

        // Icon settings
        $icon = $styles['icon'] ?? [];
        $iconSizeMap = ['match' => '1em', 'larger' => '1.25em', '1.5x' => '1.5em'];
        if (! empty($icon['icon_size'])) {
            $lines[] = "    --btn-icon-size: " . ($iconSizeMap[$icon['icon_size']] ?? '1em') . ";\n";
        }

        $lines[] = "}\n";

        return implode('', $lines);
    }
}
