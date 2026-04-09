<?php

namespace App\Services\Media;

use App\Models\Page;
use App\Models\PageLayout;
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

        $rootWidgets = $page->pageWidgets()
            ->with('widgetType')
            ->where('is_active', true)
            ->whereNull('layout_id')
            ->orderBy('sort_order')
            ->get();

        $layouts = PageLayout::where('page_id', $page->id)
            ->with(['widgets' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'), 'widgets.widgetType'])
            ->orderBy('sort_order')
            ->get();

        if ($rootWidgets->isEmpty() && $layouts->isEmpty()) {
            return null;
        }

        // Merge into ordered page flow
        $pageItems = collect();

        foreach ($rootWidgets as $pw) {
            $pageItems->push(['type' => 'widget', 'sort_order' => $pw->sort_order, 'data' => $pw]);
        }

        foreach ($layouts as $layout) {
            $pageItems->push(['type' => 'layout', 'sort_order' => $layout->sort_order, 'data' => $layout]);
        }

        $pageItems = $pageItems->sortBy('sort_order')->values();

        $html    = '';
        $styles  = '';
        $scripts = '';
        $assets  = ['css' => [], 'js' => [], 'scss' => []];

        foreach ($pageItems as $item) {
            if ($item['type'] === 'widget') {
                $pw = $item['data'];
                $result = WidgetRenderer::render($pw);
                if ($result['html'] !== null) {
                    $styleConfig = $pw->style_config ?? [];
                    $inlineStyle = static::buildInlineStyle($styleConfig);
                    $inner = $inlineStyle
                        ? "<div style=\"{$inlineStyle}\">{$result['html']}</div>"
                        : $result['html'];

                    $widgetType  = $pw->widgetType;
                    $instanceFw  = $styleConfig['full_width'] ?? null;
                    $isFullWidth = $instanceFw !== null
                        ? (bool) $instanceFw
                        : (bool) ($widgetType?->full_width ?? false);

                    $html .= $isFullWidth
                        ? $inner
                        : '<div class="site-container">' . $inner . '</div>';
                }
                $styles  .= $result['styles'];
                $scripts .= $result['scripts'];
                WidgetRenderer::collectAssets($pw->widgetType, $assets);
            } else {
                $layout = $item['data'];
                $layoutHtml  = static::renderLayoutBlock($layout, $styles, $scripts, $assets);
                $isFullWidth = (bool) (($layout->layout_config['full_width'] ?? false));
                $html .= $isFullWidth
                    ? $layoutHtml
                    : '<div class="site-container">' . $layoutHtml . '</div>';
            }
        }

        return ['html' => $html, 'styles' => $styles, 'scripts' => $scripts, 'assets' => $assets];
    }

    private static function renderLayoutBlock(PageLayout $layout, string &$inlineStyles, string &$inlineScripts, array &$widgetAssets): string
    {
        $config  = $layout->layout_config ?? [];
        $display = $layout->display ?? 'grid';

        $containerStyle = 'display:' . $display . ';';

        if ($display === 'grid') {
            $containerStyle .= 'grid-template-columns:' . ($config['grid_template_columns'] ?? str_repeat('1fr ', $layout->columns)) . ';';
        }

        if (! empty($config['gap'])) {
            $containerStyle .= 'gap:' . $config['gap'] . ';';
        }
        if (! empty($config['align_items'])) {
            $containerStyle .= 'align-items:' . $config['align_items'] . ';';
        }
        if (! empty($config['justify_items'])) {
            $containerStyle .= 'justify-items:' . $config['justify_items'] . ';';
        }
        if (! empty($config['justify_content'])) {
            $containerStyle .= 'justify-content:' . $config['justify_content'] . ';';
        }
        if (! empty($config['grid_auto_rows'])) {
            $containerStyle .= 'grid-auto-rows:' . $config['grid_auto_rows'] . ';';
        }
        if (! empty($config['flex_wrap'])) {
            $containerStyle .= 'flex-wrap:' . $config['flex_wrap'] . ';';
        }

        $spacingKeys = [
            'padding_top' => 'padding-top', 'padding_right' => 'padding-right',
            'padding_bottom' => 'padding-bottom', 'padding_left' => 'padding-left',
            'margin_top' => 'margin-top', 'margin_right' => 'margin-right',
            'margin_bottom' => 'margin-bottom', 'margin_left' => 'margin-left',
        ];
        foreach ($spacingKeys as $key => $cssProp) {
            $val = isset($config[$key]) && $config[$key] !== '' ? (int) $config[$key] : null;
            if ($val !== null) {
                $containerStyle .= $cssProp . ':' . $val . 'px;';
            }
        }
        if (! empty($config['background_color'])) {
            $containerStyle .= 'background-color:' . $config['background_color'] . ';';
        }

        // Group children by column_index
        $slots = [];
        foreach ($layout->widgets as $widget) {
            $idx = $widget->column_index ?? 0;
            $slots[$idx][] = $widget;
        }

        $columnHtml = '';
        for ($i = 0; $i < $layout->columns; $i++) {
            $slotWidgets = $slots[$i] ?? [];
            $slotHtml = '';

            foreach ($slotWidgets as $pw) {
                $result = WidgetRenderer::render($pw);
                if ($result['html'] === null) {
                    continue;
                }

                $sc = $pw->style_config ?? [];
                $styleProps = [];
                $spacingKeys = [
                    'padding_top' => 'padding-top', 'padding_right' => 'padding-right',
                    'padding_bottom' => 'padding-bottom', 'padding_left' => 'padding-left',
                    'margin_top' => 'margin-top', 'margin_right' => 'margin-right',
                    'margin_bottom' => 'margin-bottom', 'margin_left' => 'margin-left',
                ];
                foreach ($spacingKeys as $key => $cssProp) {
                    $val = isset($sc[$key]) && $sc[$key] !== '' ? (int) $sc[$key] : null;
                    if ($val !== null) {
                        $styleProps[] = $cssProp . ':' . $val . 'px';
                    }
                }
                $inlineStyle = implode(';', $styleProps);

                $slotHtml .= '<div class="widget widget--' . e($pw->widgetType->handle) . '"'
                    . ' id="widget-' . e($pw->id) . '"'
                    . ($inlineStyle ? ' style="' . e($inlineStyle) . '"' : '')
                    . '>' . $result['html'] . '</div>';

                $inlineStyles  .= $result['styles'];
                $inlineScripts .= $result['scripts'];
                WidgetRenderer::collectAssets($pw->widgetType, $widgetAssets);
            }

            $columnHtml .= '<div class="layout-column">' . $slotHtml . '</div>';
        }

        return '<div class="page-layout" style="' . e($containerStyle) . '">' . $columnHtml . '</div>';
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
