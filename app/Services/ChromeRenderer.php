<?php

namespace App\Services;

use App\Models\Page;
use App\Models\PageWidget;
use App\Services\InlineImageRenderer;
use App\Services\WidgetDataResolver;
use Illuminate\Support\Facades\Blade;

class ChromeRenderer
{
    /**
     * Render a system page's widgets to HTML + inline styles + inline scripts.
     * Returns null if the page has no active widgets.
     *
     * @return array{html: string, styles: string, scripts: string}|null
     */
    public static function render(string $slug): ?array
    {
        $page = Page::where('slug', $slug)
            ->where('type', 'system')
            ->published()
            ->first();

        if (! $page) {
            return null;
        }

        $widgets = $page->pageWidgets()
            ->with(['widgetType', 'children.widgetType', 'children.children.widgetType'])
            ->where('is_active', true)
            ->whereNull('parent_widget_id')
            ->orderBy('sort_order')
            ->get();

        if ($widgets->isEmpty()) {
            return null;
        }

        $html    = '';
        $styles  = '';
        $scripts = '';

        foreach ($widgets as $pw) {
            [$blockHtml, $blockStyles, $blockScripts] = static::renderWidget($pw);
            if ($blockHtml !== null) {
                $styleConfig = $pw->style_config ?? [];
                $inlineStyle = static::buildInlineStyle($styleConfig);
                $html .= $inlineStyle
                    ? "<div style=\"{$inlineStyle}\">{$blockHtml}</div>"
                    : $blockHtml;
            }
            $styles  .= $blockStyles;
            $scripts .= $blockScripts;
        }

        return ['html' => $html, 'styles' => $styles, 'scripts' => $scripts];
    }

    private static function renderWidget(PageWidget $pw): array
    {
        $widgetType = $pw->widgetType;

        if (! $widgetType) {
            return [null, '', ''];
        }

        $config      = $pw->config ?? [];
        $styles      = '';
        $scripts     = '';
        $html        = '';

        $configMedia = [];
        foreach ($widgetType->config_schema ?? [] as $field) {
            if (($field['type'] ?? '') === 'image' && !empty($config[$field['key']])) {
                $configMedia[$field['key']] = $pw->getFirstMedia("config_{$field['key']}");
            }
        }

        $collectionData = [];
        foreach ($widgetType->collections ?? [] as $collSlot) {
            $collHandle = $config['collection_handle'] ?? $collSlot;
            $queryConfig = $pw->query_config[$collSlot] ?? [];
            $collectionData[$collSlot] = WidgetDataResolver::resolve($collHandle, $queryConfig);
        }

        foreach ($widgetType->config_schema ?? [] as $field) {
            if (($field['type'] ?? '') === 'richtext' && !empty($config[$field['key']])) {
                $config[$field['key']] = InlineImageRenderer::process($config[$field['key']]);
            }
        }

        if ($widgetType->render_mode === 'server') {
            $templateVars = ['config' => $config, 'configMedia' => $configMedia, 'collectionData' => $collectionData];

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

        return [$html, $styles, $scripts];
    }

    private static function buildInlineStyle(array $styleConfig): string
    {
        $parts = [];
        foreach (['padding', 'margin'] as $prop) {
            if (! empty($styleConfig[$prop])) {
                $parts[] = "{$prop}: {$styleConfig[$prop]}";
            }
        }

        return implode('; ', $parts);
    }
}
