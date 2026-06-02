<?php

namespace App\Services\Media;

use App\Models\Page;
use App\Models\PageLayout;
use App\Services\AppearanceStyleComposer;
use App\Services\WidgetRenderer;

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

        return static::renderPage($page);
    }

    /**
     * Render a system page by its ID.
     *
     * @return array{html: string, styles: string, scripts: string}|null
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

        $rootWidgets = $page->widgets()
            ->with('widgetType')
            ->where('is_active', true)
            ->whereNull('layout_id')
            ->orderBy('sort_order')
            ->get();

        $layouts = $page->layouts()
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

        foreach ($pageItems as $item) {
            if ($item['type'] === 'widget') {
                $pw = $item['data'];
                $result = WidgetRenderer::render($pw);
                if ($result['html'] !== null) {
                    $appearanceConfig = $pw->appearance_config ?? [];
                    $inlineStyle = static::buildInlineStyle($appearanceConfig);
                    // np-chrome-section is the host-rule hook for the vertical
                    // section-spacing primitive: chrome root widgets render as a
                    // bare wrapper (no .widget class), so without this class the
                    // --np-* spacing custom properties would have nothing to
                    // consume them. Only emitted when there is an inline style.
                    $inner = $inlineStyle
                        ? "<div class=\"np-chrome-section\" style=\"{$inlineStyle}\">{$result['html']}</div>"
                        : $result['html'];

                    $widgetType = $pw->widgetType;
                    $bgInstance = $appearanceConfig['layout']['background_full_width'] ?? null;
                    $contentInstance = $appearanceConfig['layout']['content_full_width'] ?? null;
                    $bgFw = $bgInstance !== null
                        ? (bool) $bgInstance
                        : (bool) ($widgetType?->background_full_width ?? false);
                    $contentFw = $contentInstance !== null
                        ? (bool) $contentInstance
                        : (bool) ($widgetType?->content_full_width ?? false);
                    if (! $bgFw && $contentFw) {
                        $bgFw = true;
                    }

                    $contentWrapped = $contentFw ? $inner : '<div class="site-container">' . $inner . '</div>';
                    $html .= $bgFw ? $contentWrapped : '<div class="site-container">' . $contentWrapped . '</div>';
                }
                $styles  .= $result['styles'];
                $scripts .= $result['scripts'];
            } else {
                $layout = $item['data'];
                $layoutHtml = static::renderLayoutBlock($layout, $styles, $scripts);
                $bgFw = (bool) ($layout->layout_config['background_full_width'] ?? false);
                $contentFw = (bool) ($layout->layout_config['content_full_width'] ?? false);
                if (! $bgFw && $contentFw) {
                    $bgFw = true;
                }
                $html .= $bgFw ? $layoutHtml : '<div class="site-container">' . $layoutHtml . '</div>';
            }
        }

        return ['html' => $html, 'styles' => $styles, 'scripts' => $scripts];
    }

    private static function renderLayoutBlock(PageLayout $layout, string &$inlineStyles, string &$inlineScripts): string
    {
        $config  = $layout->layout_config ?? [];
        $display = $layout->display ?? 'grid';

        $gridStyle = 'display:' . $display . ';';

        if ($display === 'grid') {
            $gridStyle .= 'grid-template-columns:' . ($config['grid_template_columns'] ?? str_repeat('1fr ', $layout->columns)) . ';';
        }

        if (! empty($config['gap'])) {
            $gridStyle .= 'gap:' . $config['gap'] . ';';
        }
        if (! empty($config['align_items'])) {
            $gridStyle .= 'align-items:' . $config['align_items'] . ';';
        }
        if (! empty($config['justify_items'])) {
            $gridStyle .= 'justify-items:' . $config['justify_items'] . ';';
        }
        if (! empty($config['justify_content'])) {
            $gridStyle .= 'justify-content:' . $config['justify_content'] . ';';
        }
        if (! empty($config['grid_auto_rows'])) {
            $gridStyle .= 'grid-auto-rows:' . $config['grid_auto_rows'] . ';';
        }
        if (! empty($config['flex_wrap'])) {
            $gridStyle .= 'flex-wrap:' . $config['flex_wrap'] . ';';
        }

        // Horizontal stays literal; vertical (top/bottom) becomes --np-* custom
        // properties so the host rule on .page-layout can scale it at narrow
        // widths, in lockstep with the page render path.
        $appearanceStyle = '';
        $spacingKeys = [
            'padding_right' => 'padding-right', 'padding_left' => 'padding-left',
            'margin_right' => 'margin-right', 'margin_left' => 'margin-left',
        ];
        foreach ($spacingKeys as $key => $cssProp) {
            $val = isset($config[$key]) && $config[$key] !== '' ? (int) $config[$key] : null;
            if ($val !== null) {
                $appearanceStyle .= $cssProp . ':' . $val . 'px;';
            }
        }

        $verticalVars = AppearanceStyleComposer::composeVerticalSpacingVars(
            ['top' => $config['padding_top'] ?? '', 'bottom' => $config['padding_bottom'] ?? ''],
            ['top' => $config['margin_top'] ?? '', 'bottom' => $config['margin_bottom'] ?? ''],
        );
        foreach ($verticalVars as $prop) {
            $appearanceStyle .= $prop . ';';
        }
        if (! empty($config['background_color'])) {
            $appearanceStyle .= 'background-color:' . $config['background_color'] . ';';
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

                $inlineStyle = static::buildInlineStyle($pw->appearance_config ?? []);

                $slotHtml .= '<div class="widget widget--' . e($pw->widgetType->handle) . '"'
                    . ' id="widget-' . e($pw->id) . '"'
                    . ($inlineStyle ? ' style="' . e($inlineStyle) . '"' : '')
                    . '>' . $result['html'] . '</div>';

                $inlineStyles  .= $result['styles'];
                $inlineScripts .= $result['scripts'];
            }

            $columnHtml .= '<div class="layout-column">' . $slotHtml . '</div>';
        }

        $gridHtml = '<div class="layout-grid" style="' . e($gridStyle) . '">' . $columnHtml . '</div>';

        $contentFw = (bool) ($config['content_full_width'] ?? false);
        $innerHtml = $contentFw ? $gridHtml : '<div class="site-container">' . $gridHtml . '</div>';

        return '<div class="page-layout"' . ($appearanceStyle ? ' style="' . e($appearanceStyle) . '"' : '') . '>' . $innerHtml . '</div>';
    }

    private static function buildInlineStyle(array $appearanceConfig): string
    {
        $styleProps = [];

        $bgColor = $appearanceConfig['background']['color'] ?? null;
        if (! empty($bgColor) && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $bgColor)) {
            $styleProps[] = 'background-color:' . $bgColor;
        }
        $textColor = $appearanceConfig['text']['color'] ?? null;
        if (! empty($textColor) && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $textColor)) {
            $styleProps[] = 'color:' . $textColor;
        }

        // Vertical (top/bottom) padding/margin is emitted as --np-* custom
        // properties so the host-layer rule can scale it at narrow widths, in
        // lockstep with the page render path (AppearanceStyleComposer). Horizontal
        // stays literal, preserving chrome's existing emission.
        $padding = $appearanceConfig['layout']['padding'] ?? [];
        $margin  = $appearanceConfig['layout']['margin'] ?? [];

        foreach (['right', 'left'] as $side) {
            $val = isset($padding[$side]) && $padding[$side] !== '' ? (int) $padding[$side] : null;
            if ($val !== null) {
                $styleProps[] = 'padding-' . $side . ':' . $val . 'px';
            }
        }
        foreach (['right', 'left'] as $side) {
            $val = isset($margin[$side]) && $margin[$side] !== '' ? (int) $margin[$side] : null;
            if ($val !== null) {
                $styleProps[] = 'margin-' . $side . ':' . $val . 'px';
            }
        }

        foreach (AppearanceStyleComposer::composeVerticalSpacingVars($padding, $margin) as $prop) {
            $styleProps[] = $prop;
        }

        foreach (AppearanceStyleComposer::composeBorderProps($appearanceConfig['layout']['border'] ?? []) as $prop) {
            $styleProps[] = $prop;
        }

        return implode(';', $styleProps);
    }
}
