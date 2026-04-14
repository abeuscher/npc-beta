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

        $configFullWidth = $pw->config['full_width'] ?? null;
        $fullWidth = $configFullWidth !== null ? (bool) $configFullWidth : $composed['is_full_width'];

        $block = [
            'handle'       => $widgetType->handle,
            'instance_id'  => $pw->id,
            'html'         => $result['html'],
            'css'          => $widgetType->css ?? '',
            'js'           => $widgetType->js ?? '',
            'inline_style' => $composed['inline_style'],
            'full_width'   => $fullWidth,
        ];

        return ['block' => $block, 'styles' => $result['styles'], 'scripts' => $result['scripts']];
    }

    public function renderLayoutBlock(PageLayout $layout, string &$inlineStyles, string &$inlineScripts, array &$widgetAssets): ?array
    {
        $config = $layout->layout_config ?? [];
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

        $html = '<div class="page-layout" style="' . e($containerStyle) . '">' . $columnHtml . '</div>';

        return [
            'handle'       => 'page_layout',
            'instance_id'  => $layout->id,
            'html'         => $html,
            'css'          => '',
            'js'           => '',
            'inline_style' => '',
            'full_width'   => (bool) ($config['full_width'] ?? false),
        ];
    }
}
