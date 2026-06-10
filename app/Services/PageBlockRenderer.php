<?php

namespace App\Services;

use App\Models\PageLayout;
use App\Models\PageWidget;

class PageBlockRenderer
{
    // Mirrors the .layout-grid collapse container query ($bp-md in
    // resources/scss/_variables.scss): at/below this width a collapse_mobile
    // layout stacks to one full-width column, so a derived `sizes` value
    // advertises 100vw below it.
    private const COLLAPSE_BREAKPOINT = 768;

    public function __construct(private AppearanceStyleComposer $styleComposer) {}

    public function renderWidgetBlock(PageWidget $pw, ?string $columnSizes = null): ?array
    {
        $widgetType = $pw->widgetType;

        if (! $widgetType) {
            return null;
        }

        $result = WidgetRenderer::render($pw, columnSizes: $columnSizes);

        if ($result['html'] === null) {
            return null;
        }

        $composed = $this->styleComposer->compose($pw);

        $block = [
            'handle'                => $widgetType->handle,
            'instance_id'           => $pw->id,
            'html'                  => $result['html'],
            'css'                   => $widgetType->css ?? '',
            'js'                    => $widgetType->js ?? '',
            'inline_style'          => $composed['inline_style'],
            'background_full_width' => $composed['background_full_width'],
            'content_full_width'    => $composed['content_full_width'],
        ];

        return ['block' => $block, 'styles' => $result['styles'], 'scripts' => $result['scripts']];
    }

    public function renderLayoutBlock(PageLayout $layout, string &$inlineStyles, string &$inlineScripts): ?array
    {
        $config = $layout->layout_config ?? [];
        $display = $layout->display ?? 'grid';

        $gridStyle = 'display:' . $display . ';';

        if ($display === 'grid') {
            $gridStyle .= '--layout-cols:' . ($config['grid_template_columns'] ?? str_repeat('1fr ', $layout->columns)) . ';';
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

        $appearanceStyle = $this->styleComposer->composeForLayout($layout);

        $collapseMobile = ($config['collapse_mobile'] ?? true) !== false;
        $columnSizes    = $this->deriveColumnSizes($config, $layout->columns, $collapseMobile);

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
                $blockData = $this->renderWidgetBlock($pw, $columnSizes[$i] ?? null);
                if ($blockData) {
                    $inlineStyle = $blockData['block']['inline_style'] ?? '';

                    $slotHtml .= '<div class="widget widget--' . e($pw->widgetType->handle) . '"'
                        . ' id="widget-' . e($pw->id) . '"'
                        . ($inlineStyle ? ' style="' . e($inlineStyle) . '"' : '')
                        . '>' . $blockData['block']['html'] . '</div>';

                    $inlineStyles  .= $blockData['styles'];
                    $inlineScripts .= $blockData['scripts'];
                }
            }

            $columnHtml .= '<div class="layout-column">' . $slotHtml . '</div>';
        }

        $gridHtml = '<div class="layout-grid" data-collapse-mobile="' . ($collapseMobile ? 'true' : 'false') . '" style="' . e($gridStyle) . '">' . $columnHtml . '</div>';

        $fw = $this->styleComposer->resolveFullWidthForLayout($layout);

        $innerHtml = $fw['content_full_width']
            ? $gridHtml
            : '<div class="site-container">' . $gridHtml . '</div>';

        $outerStyle = $appearanceStyle;
        $html = '<div class="page-layout"' . ($outerStyle ? ' style="' . e($outerStyle) . '"' : '') . '>' . $innerHtml . '</div>';

        return [
            'handle'                => 'page_layout',
            'instance_id'           => $layout->id,
            'html'                  => $html,
            'css'                   => '',
            'js'                    => '',
            'inline_style'          => '',
            'background_full_width' => $fw['background_full_width'],
            'content_full_width'    => $fw['content_full_width'],
        ];
    }

    /**
     * Derive a responsive `sizes` value for each grid column from the layout's
     * `grid_template_columns` fraction, so an image in (say) a 2fr/3fr column
     * advertises ~40vw / ~60vw instead of a blanket 100vw and stops over-
     * downloading. Returns a map of column index → sizes string. A column is
     * omitted (→ the partial's 100vw default) when the track list can't be
     * reduced to plain `fr` units — px/%/auto/repeat()/minmax() are not guessed.
     *
     * collapse_mobile layouts stack to one full-width column at/below
     * COLLAPSE_BREAKPOINT, so the value carries a `(max-width: …) 100vw` clause.
     * The fraction ignores grid gaps and the site-container cap, which only
     * makes it err slightly large — a safe direction for `sizes`.
     *
     * @return array<int, string>
     */
    private function deriveColumnSizes(array $config, int $columns, bool $collapseMobile): array
    {
        if ($columns < 2) {
            return [];
        }

        $template = trim((string) ($config['grid_template_columns'] ?? ''));
        if ($template === '') {
            $template = trim(str_repeat('1fr ', $columns));
        }

        $tokens = preg_split('/\s+/', $template, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($tokens) !== $columns) {
            return [];
        }

        $fractions = [];
        foreach ($tokens as $token) {
            if (! preg_match('/^([0-9]*\.?[0-9]+)fr$/', $token, $m)) {
                return [];
            }
            $fractions[] = (float) $m[1];
        }

        $total = array_sum($fractions);
        if ($total <= 0) {
            return [];
        }

        $sizes = [];
        foreach ($fractions as $i => $fr) {
            $pct = max(1, min(100, (int) round($fr / $total * 100)));
            $sizes[$i] = $collapseMobile
                ? '(max-width: ' . self::COLLAPSE_BREAKPOINT . 'px) 100vw, ' . $pct . 'vw'
                : $pct . 'vw';
        }

        return $sizes;
    }
}
