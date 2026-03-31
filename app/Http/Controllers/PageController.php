<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\Template;
use App\Services\InlineImageRenderer;
use App\Services\PageContext;
use App\Services\WidgetDataResolver;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;

class PageController extends Controller
{
    public function home()
    {
        $page = Page::where('slug', 'home')
            ->published()
            ->firstOrFail();

        return $this->renderPage($page);
    }

    public function show(string $slug)
    {
        abort_if(str_starts_with($slug, '_'), 404);

        $page = Page::where('slug', $slug)
            ->published()
            ->firstOrFail();

        if ($page->type === 'member') {
            $portalUser = auth('portal')->user();

            if (! $portalUser) {
                return redirect()->route('portal.login');
            }

            if (! $portalUser->hasVerifiedEmail()) {
                return redirect()->route('portal.verification.notice');
            }
        }

        return $this->renderPage($page);
    }

    private function renderPage(Page $page)
    {
        $pageContext = new PageContext($page);
        View::share('pageContext', $pageContext);

        // Resolve the page's template (explicit or default)
        $template = $page->template_id
            ? Template::find($page->template_id)
            : null;

        if (! $template) {
            $template = Template::query()->default()->first();
        }

        View::share('__template', $template);

        $pageWidgets = $page->pageWidgets()
            ->with(['widgetType', 'children.widgetType', 'children.children.widgetType'])
            ->where('is_active', true)
            ->whereNull('parent_widget_id')
            ->orderBy('sort_order')
            ->get();

        $blocks         = [];
        $inlineStyles   = '';
        $inlineScripts  = '';
        $widgetAssets    = ['css' => [], 'js' => [], 'scss' => []];

        foreach ($pageWidgets as $pw) {
            [$blockData, $styles, $scripts] = $this->renderWidget($pw);
            $blocks[]        = $blockData;
            $inlineStyles   .= $styles;
            $inlineScripts  .= $scripts;
            $this->collectAssets($pw->widgetType, $widgetAssets);
        }

        return view('pages.show', compact('page', 'blocks', 'inlineStyles', 'inlineScripts', 'widgetAssets'));
    }

    private function renderWidget(PageWidget $pw): array
    {
        $widgetType = $pw->widgetType;

        if (! $widgetType) {
            return [null, '', ''];
        }

        $config      = $pw->config ?? [];
        $styleConfig = $pw->style_config ?? [];
        $styles      = '';
        $scripts     = '';
        $html        = '';

        // Resolve image config fields to media objects for the picture component
        $configMedia = [];
        foreach ($widgetType->config_schema ?? [] as $field) {
            if (($field['type'] ?? '') === 'image' && !empty($config[$field['key']])) {
                $configMedia[$field['key']] = $pw->getFirstMedia("config_{$field['key']}");
            }
        }

        // Resolve collection data for widgets that declare collections
        $collectionData = [];
        foreach ($widgetType->collections ?? [] as $collSlot) {
            $collHandle = $config['collection_handle'] ?? $collSlot;
            $queryConfig = $pw->query_config[$collSlot] ?? [];
            $collectionData[$collSlot] = WidgetDataResolver::resolve($collHandle, $queryConfig);
        }

        // Process inline images in richtext config fields
        foreach ($widgetType->config_schema ?? [] as $field) {
            if (($field['type'] ?? '') === 'richtext' && ! empty($config[$field['key']])) {
                $config[$field['key']] = InlineImageRenderer::process($config[$field['key']]);
            }
        }

        if ($widgetType->render_mode === 'server') {
            $templateVars = ['config' => $config, 'configMedia' => $configMedia, 'collectionData' => $collectionData];

            if ($widgetType->handle === 'column_widget') {
                $children = $this->renderColumnChildren($pw);
                $templateVars['children'] = $children;
                $html = $widgetType->template
                    ? Blade::render($widgetType->template, $templateVars)
                    : '';
            } else {
                $html = $widgetType->template
                    ? Blade::render($widgetType->template, $templateVars)
                    : '';
            }

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

        $blockData = [
            'handle'       => $widgetType->handle,
            'instance_id'  => $pw->id,
            'html'         => $html,
            'css'          => $widgetType->css ?? '',
            'js'           => $widgetType->js ?? '',
            'style_config' => $styleConfig,
        ];

        return [$blockData, $styles, $scripts];
    }

    private function collectAssets(?\App\Models\WidgetType $widgetType, array &$widgetAssets): void
    {
        if (! $widgetType) {
            return;
        }

        $assets = $widgetType->assets ?? [];

        foreach (['css', 'js', 'scss'] as $type) {
            foreach ($assets[$type] ?? [] as $path) {
                if (! in_array($path, $widgetAssets[$type], true)) {
                    $widgetAssets[$type][] = $path;
                }
            }
        }
    }

    private function renderColumnChildren(PageWidget $pw): array
    {
        $children = [];

        foreach ($pw->children as $child) {
            if (! $child->is_active) {
                continue;
            }

            [$blockData, , ] = $this->renderWidget($child);

            if ($blockData === null) {
                continue;
            }

            $idx = $child->column_index ?? 0;
            $children[$idx][] = $blockData;
        }

        return $children;
    }
}
