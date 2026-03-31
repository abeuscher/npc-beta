<?php

namespace App\Services;

use App\Models\PageWidget;
use App\Models\WidgetType;
use Illuminate\Support\Facades\Blade;

class WidgetRenderer
{
    /**
     * Render a single widget to HTML + inline styles + inline scripts.
     *
     * @return array{html: string|null, styles: string, scripts: string}
     */
    public static function render(PageWidget $pw, array $columnChildren = []): array
    {
        $widgetType = $pw->widgetType;

        if (! $widgetType) {
            return ['html' => null, 'styles' => '', 'scripts' => ''];
        }

        $config  = $pw->config ?? [];
        $styles  = '';
        $scripts = '';

        // Resolve image config fields to media objects
        $configMedia = [];
        foreach ($widgetType->config_schema ?? [] as $field) {
            if (($field['type'] ?? '') === 'image' && ! empty($config[$field['key']])) {
                $configMedia[$field['key']] = $pw->getFirstMedia("config_{$field['key']}");
            }
        }

        // Resolve collection data
        $collectionData = [];
        foreach ($widgetType->collections ?? [] as $collSlot) {
            $collHandle  = $config['collection_handle'] ?? $collSlot;
            $queryConfig = $pw->query_config[$collSlot] ?? [];
            $collectionData[$collSlot] = WidgetDataResolver::resolve($collHandle, $queryConfig);
        }

        // Process inline images in richtext fields
        foreach ($widgetType->config_schema ?? [] as $field) {
            if (($field['type'] ?? '') === 'richtext' && ! empty($config[$field['key']])) {
                $config[$field['key']] = InlineImageRenderer::process($config[$field['key']]);
            }
        }

        // Build template variables and render
        $html = '';

        if ($widgetType->render_mode === 'server') {
            $templateVars = [
                'config'         => $config,
                'configMedia'    => $configMedia,
                'collectionData' => $collectionData,
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
