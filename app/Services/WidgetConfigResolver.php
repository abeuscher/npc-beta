<?php

namespace App\Services;

use App\Models\PageWidget;

class WidgetConfigResolver
{
    public function __construct(protected WidgetRegistry $registry) {}

    /**
     * Compose a widget's config from defaults + theme overrides + instance overrides.
     * Later layers overwrite earlier ones per key.
     */
    public function resolve(PageWidget $pw): array
    {
        return array_merge(
            $this->resolvedDefaults($pw),
            $pw->config ?? [],
        );
    }

    /**
     * The composed defaults-plus-theme map without the instance layer.
     * Used as the wire contract for the inspector (`resolved_defaults` payload field)
     * so inspector display and renderer output draw from the same source of truth.
     */
    public function resolvedDefaults(PageWidget $pw): array
    {
        return array_merge(
            $this->baseDefaults($pw),
            $this->themeOverrides($pw),
        );
    }

    public function defaultFor(PageWidget $pw, string $key): mixed
    {
        return $this->resolvedDefaults($pw)[$key] ?? null;
    }

    public function hasOverride(PageWidget $pw, string $key): bool
    {
        $instance = $pw->config ?? [];
        if (! array_key_exists($key, $instance)) {
            return false;
        }
        return $instance[$key] !== ($this->resolvedDefaults($pw)[$key] ?? null);
    }

    private function baseDefaults(PageWidget $pw): array
    {
        $handle = $pw->widgetType?->handle;
        if ($handle && ($def = $this->registry->find($handle))) {
            return $def->defaults();
        }
        return $pw->widgetType?->getDefaultConfig() ?? [];
    }

    /**
     * Extension point for template/theme-level defaults (Stage N of the Sovereign Widget arc).
     * Stage 2 stub — returns no overrides.
     *
     * @return array<string, mixed>
     */
    private function themeOverrides(PageWidget $pw): array
    {
        return [];
    }
}
