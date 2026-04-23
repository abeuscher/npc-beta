<?php

namespace App\Services;

use App\Models\PageWidget;
use App\Models\WidgetType;

class WidgetAssetResolver
{
    private const MANIFEST_RELATIVE_PATH = 'build/widgets/manifest.json';
    private const BUNDLE_URL_PREFIX = '/build/widgets/';

    /** @var array<string, mixed>|null */
    private ?array $manifestCache = null;

    public function __construct(private readonly ?string $manifestPath = null) {}

    /**
     * Decoded build-server manifest. Reads from disk once per request.
     *
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        if ($this->manifestCache !== null) {
            return $this->manifestCache;
        }

        $path = $this->manifestPath ?? public_path(self::MANIFEST_RELATIVE_PATH);
        if (! is_readable($path)) {
            return $this->manifestCache = [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        return $this->manifestCache = is_array($decoded) ? $decoded : [];
    }

    /**
     * URL of the main widget CSS bundle, or null when the build server
     * has not produced one yet.
     */
    public function widgetCss(): ?string
    {
        $filename = $this->manifest()['css'] ?? null;
        return is_string($filename) && $filename !== '' ? self::BUNDLE_URL_PREFIX . $filename : null;
    }

    /**
     * URL of the main widget JS bundle, or null when the build server
     * has not produced one yet.
     */
    public function widgetJs(): ?string
    {
        $filename = $this->manifest()['js'] ?? null;
        return is_string($filename) && $filename !== '' ? self::BUNDLE_URL_PREFIX . $filename : null;
    }

    /**
     * Every per-library bundle entry declared in the manifest, keyed by lib
     * handle. Each entry has optional 'css' and 'js' URL strings.
     *
     * @return array<string, array{css?: string, js?: string}>
     */
    public function allLibs(): array
    {
        $libs = $this->manifest()['libs'] ?? [];
        return is_array($libs) ? $libs : [];
    }

    /**
     * Resolve a specific set of lib handles against the manifest. Handles
     * that do not appear in the manifest are silently dropped — fail-closed
     * behaviour at the asset boundary.
     *
     * @param  array<int, string>  $handles
     * @return array<string, array{css?: string, js?: string}>
     */
    public function libs(array $handles): array
    {
        $manifestLibs = $this->allLibs();
        $out = [];
        foreach ($handles as $handle) {
            if (isset($manifestLibs[$handle])) {
                $out[$handle] = $manifestLibs[$handle];
            }
        }
        return $out;
    }

    /**
     * Walk a set of widgets (WidgetType or PageWidget instances), collect the
     * lib handles declared under each widget type's assets['libs'] column,
     * dedupe, and resolve through the manifest.
     *
     * @param  iterable<WidgetType|PageWidget>  $items
     * @return array<string, array{css?: string, js?: string}>
     */
    public function libsForWidgets(iterable $items): array
    {
        $handles = [];
        foreach ($items as $item) {
            $widgetType = $item instanceof PageWidget ? $item->widgetType : $item;
            if (! $widgetType instanceof WidgetType) {
                continue;
            }
            $assets = $widgetType->assets ?? [];
            foreach ($assets['libs'] ?? [] as $handle) {
                if (is_string($handle) && $handle !== '') {
                    $handles[$handle] = true;
                }
            }
        }

        return $this->libs(array_keys($handles));
    }
}
