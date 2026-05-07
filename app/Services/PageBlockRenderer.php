<?php

namespace App\Services;

use App\Models\PageLayout;
use App\Models\PageWidget;

class PageBlockRenderer
{
    public function __construct(private AppearanceStyleComposer $styleComposer) {}

    public function renderWidgetBlock(PageWidget $pw): ?array
    {
        $widgetType = $pw->widgetType;

        if (! $widgetType) {
            return null;
        }

        $result = WidgetRenderer::render($pw);

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

    public function renderLayoutBlock(PageLayout $layout, string &$inlineStyles, string &$inlineScripts, array &$widgetAssets): ?array
    {
        $config = $layout->layout_config ?? [];
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

        $appearanceStyle = $this->styleComposer->composeForLayout($layout);

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
                $blockData = $this->renderWidgetBlock($pw);
                if ($blockData) {
                    $inlineStyle = $blockData['block']['inline_style'] ?? '';

                    $slotHtml .= '<div class="widget widget--' . e($pw->widgetType->handle) . '"'
                        . ' id="widget-' . e($pw->id) . '"'
                        . ($inlineStyle ? ' style="' . e($inlineStyle) . '"' : '')
                        . '>' . $blockData['block']['html'] . '</div>';

                    $inlineStyles  .= $blockData['styles'];
                    $inlineScripts .= $blockData['scripts'];
                    WidgetRenderer::collectAssets($pw->widgetType, $widgetAssets);
                }
            }

            $columnHtml .= '<div class="layout-column">' . $slotHtml . '</div>';
        }

        $gridHtml = '<div class="layout-grid" style="' . e($gridStyle) . '">' . $columnHtml . '</div>';

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
}
