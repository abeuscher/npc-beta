<?php

namespace App\Services;

use App\Models\PageWidget;
use App\Models\WidgetType;
use App\Services\WidgetConfigResolver;
use Illuminate\Support\Facades\Blade;

class WidgetRenderer
{
    /**
     * Render a single widget to HTML + inline styles + inline scripts.
     *
     * @param  array<string, array>  $fallbackCollectionData  Optional pre-resolved collection data (e.g. demo data for admin preview). Keyed by collection slot name.
     * @return array{html: string|null, styles: string, scripts: string}
     */
    public static function render(PageWidget $pw, array $columnChildren = [], array $fallbackCollectionData = []): array
    {
        $widgetType = $pw->widgetType;

        if (! $widgetType) {
            return ['html' => null, 'styles' => '', 'scripts' => ''];
        }

        $config  = app(WidgetConfigResolver::class)->resolve($pw);
        $styles  = '';
        $scripts = '';

        // Resolve image config fields to media objects
        $configMedia = [];
        foreach ($widgetType->config_schema ?? [] as $field) {
            if (in_array($field['type'] ?? '', ['image', 'video']) && ! empty($config[$field['key']])) {
                $configMedia[$field['key']] = $pw->getFirstMedia("config_{$field['key']}");
            }
        }

        // Resolve collection data (use fallback if real data is empty)
        $collectionData = [];
        foreach ($widgetType->collections ?? [] as $collSlot) {
            $collHandle  = $config['collection_handle'] ?? $collSlot;
            $queryConfig = $pw->query_config[$collSlot] ?? [];
            $resolved = WidgetDataResolver::resolve($collHandle, $queryConfig);
            $collectionData[$collSlot] = ! empty($resolved) ? $resolved : ($fallbackCollectionData[$collSlot] ?? []);
        }

        // Process inline images in richtext fields
        foreach ($widgetType->config_schema ?? [] as $field) {
            if (($field['type'] ?? '') === 'richtext' && ! empty($config[$field['key']])) {
                $config[$field['key']] = \App\Services\Media\InlineImageRenderer::process($config[$field['key']]);
            }
        }

        // Build template variables and render
        $html = '';

        if ($widgetType->render_mode === 'server') {
            $templateVars = [
                'config'             => $config,
                'configMedia'        => $configMedia,
                'collectionData'     => $collectionData,
                'pageContext'        => app(PageContext::class),
                'pageContextTokens'  => app(PageContextTokens::class),
            ];

            if (! empty($columnChildren)) {
                $templateVars['children'] = $columnChildren;
            }

            $html = $widgetType->template
                ? Blade::render($widgetType->template, $templateVars)
                : '';

            if ($widgetType->css) {
                $styles .= "\n" . $widgetType->css;
            }
            if ($widgetType->js) {
                $scripts .= "\n" . $widgetType->js;
            }
        } else {
            $html = $widgetType->code
                ? '<script>' . $widgetType->code . '</script>'
                : '';

            if ($widgetType->css) {
                $styles .= "\n" . $widgetType->css;
            }
        }

        return ['html' => $html, 'styles' => $styles, 'scripts' => $scripts];
    }

    /**
     * Collect CSS/JS/SCSS asset paths from a widget type into an accumulator.
     */
    public static function collectAssets(?WidgetType $widgetType, array &$assets): void
    {
        if (! $widgetType) {
            return;
        }

        $widgetAssets = $widgetType->assets ?? [];

        foreach (['css', 'js', 'scss'] as $type) {
            foreach ($widgetAssets[$type] ?? [] as $path) {
                if (! in_array($path, $assets[$type], true)) {
                    $assets[$type][] = $path;
                }
            }
        }
    }
}
