<?php

namespace App\Services\Media;

use App\Models\Page;
use App\Services\WidgetRenderer;

class ChromeRenderer
{
    /**
     * Render a system page's widgets to HTML + inline styles + inline scripts.
     * Returns null if the page has no active widgets.
     *
     * @return array{html: string, styles: string, scripts: string, assets: array{css: string[], js: string[], scss: string[]}}|null
     */
    public static function render(string $slug): ?array
    {
        $page = Page::where('slug', $slug)
            ->where('type', 'system')
            ->published()
            ->first();

        return static::renderPage($page);
    }

    /**
     * Render a system page by its ID.
     *
     * @return array{html: string, styles: string, scripts: string, assets: array{css: string[], js: string[], scss: string[]}}|null
     */
    public static function renderById(string $pageId): ?array
    {
        $page = Page::where('id', $pageId)
            ->where('type', 'system')
            ->published()
            ->first();

        return static::renderPage($page);
    }

    private static function renderPage(?Page $page): ?array
    {
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
        $assets  = ['css' => [], 'js' => [], 'scss' => []];

        foreach ($widgets as $pw) {
            $result = WidgetRenderer::render($pw);
            if ($result['html'] !== null) {
                $styleConfig = $pw->style_config ?? [];
                $inlineStyle = static::buildInlineStyle($styleConfig);
                $html .= $inlineStyle
                    ? "<div style=\"{$inlineStyle}\">{$result['html']}</div>"
                    : $result['html'];
            }
            $styles  .= $result['styles'];
            $scripts .= $result['scripts'];
            WidgetRenderer::collectAssets($pw->widgetType, $assets);
        }

        return ['html' => $html, 'styles' => $styles, 'scripts' => $scripts, 'assets' => $assets];
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
