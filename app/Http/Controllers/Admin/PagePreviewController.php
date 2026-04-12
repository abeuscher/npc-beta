<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageLayout;
use App\Models\PageWidget;
use App\Models\Template;
use App\Services\PageContext;
use App\Services\WidgetRenderer;
use Illuminate\Support\Facades\View;

class PagePreviewController extends Controller
{
    public function show(Page $page)
    {
        abort_unless(auth()->user()?->can('update_page'), 403);

        $pageContext = new PageContext($page);
        View::share('pageContext', $pageContext);

        $template = $page->template_id
            ? Template::find($page->template_id)
            : null;

        if (! $template) {
            $template = Template::query()->default()->first();
        }

        View::share('__template', $template);

        // Load root widgets and layouts, merge into page flow by sort_order
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

        $pageItems = collect();

        foreach ($rootWidgets as $pw) {
            $pageItems->push(['type' => 'widget', 'sort_order' => $pw->sort_order, 'data' => $pw]);
        }

        foreach ($layouts as $layout) {
            $pageItems->push(['type' => 'layout', 'sort_order' => $layout->sort_order, 'data' => $layout]);
        }

        $pageItems = $pageItems->sortBy('sort_order')->values();

        $blocks         = [];
        $inlineStyles   = '';
        $inlineScripts  = '';
        $widgetAssets   = ['css' => [], 'js' => [], 'scss' => []];

        foreach ($pageItems as $item) {
            if ($item['type'] === 'widget') {
                $pw = $item['data'];
                $blockData = $this->renderWidgetBlock($pw);
                if ($blockData) {
                    $blocks[] = $blockData['block'];
                    $inlineStyles  .= $blockData['styles'];
                    $inlineScripts .= $blockData['scripts'];
                }
                WidgetRenderer::collectAssets($pw->widgetType, $widgetAssets);
            } else {
                $layout = $item['data'];
                $layoutBlock = $this->renderLayoutBlock($layout, $inlineStyles, $inlineScripts, $widgetAssets);
                if ($layoutBlock) {
                    $blocks[] = $layoutBlock;
                }
            }
        }

        // Hero nav overlap
        $firstItem = $pageItems->first();
        $firstPw = ($firstItem && $firstItem['type'] === 'widget') ? $firstItem['data'] : null;
        $navOverlap = $firstPw
            && $firstPw->widgetType?->handle === 'hero'
            && (($firstPw->config['overlap_nav'] ?? false) == true);
        View::share('__navOverlap', $navOverlap);
        View::share('__navOverlayLinkColor', $navOverlap ? ($firstPw->config['nav_link_color'] ?? '') : '');
        View::share('__navOverlayHoverColor', $navOverlap ? ($firstPw->config['nav_hover_color'] ?? '') : '');

        return view('admin.page-preview', compact('page', 'blocks', 'inlineStyles', 'inlineScripts', 'widgetAssets'));
    }

    private function renderWidgetBlock(PageWidget $pw): ?array
    {
        $widgetType = $pw->widgetType;

        if (! $widgetType) {
            return null;
        }

        $result = WidgetRenderer::render($pw);

        if ($result['html'] === null) {
            return null;
        }

        $configFullWidth = $pw->config['full_width'] ?? null;
        $fullWidth = $configFullWidth !== null ? (bool) $configFullWidth : ($widgetType->full_width ?? false);

        $block = [
            'handle'            => $widgetType->handle,
            'instance_id'       => $pw->id,
            'html'              => $result['html'],
            'css'               => $widgetType->css ?? '',
            'js'                => $widgetType->js ?? '',
            'appearance_config' => $pw->appearance_config ?? [],
            'full_width'        => $fullWidth,
            'label'             => $pw->label ?? $widgetType->label,
        ];

        return ['block' => $block, 'styles' => $result['styles'], 'scripts' => $result['scripts']];
    }

    private function renderLayoutBlock(PageLayout $layout, string &$inlineStyles, string &$inlineScripts, array &$widgetAssets): ?array
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
                    $ac = $pw->appearance_config ?? [];
                    $styleProps = [];

                    $bgColor = $ac['background']['color'] ?? null;
                    if (! empty($bgColor) && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $bgColor)) {
                        $styleProps[] = 'background-color:' . $bgColor;
                    }
                    $textColor = $ac['text']['color'] ?? null;
                    if (! empty($textColor) && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $textColor)) {
                        $styleProps[] = 'color:' . $textColor;
                    }

                    $padding = $ac['layout']['padding'] ?? [];
                    $margin  = $ac['layout']['margin'] ?? [];
                    foreach (['top', 'right', 'bottom', 'left'] as $side) {
                        $val = isset($padding[$side]) && $padding[$side] !== '' ? (int) $padding[$side] : null;
                        if ($val !== null) {
                            $styleProps[] = 'padding-' . $side . ':' . $val . 'px';
                        }
                    }
                    foreach (['top', 'right', 'bottom', 'left'] as $side) {
                        $val = isset($margin[$side]) && $margin[$side] !== '' ? (int) $margin[$side] : null;
                        if ($val !== null) {
                            $styleProps[] = 'margin-' . $side . ':' . $val . 'px';
                        }
                    }

                    $inlineStyle = implode(';', $styleProps);

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
            'handle'            => 'page_layout',
            'instance_id'       => $layout->id,
            'html'              => $html,
            'css'               => '',
            'js'                => '',
            'appearance_config' => [],
            'full_width'        => false,
            'label'             => $layout->label ?? 'Column Layout',
        ];
    }
}
