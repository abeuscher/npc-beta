<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\PageWidget;
use App\Services\PageContext;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;

class PageController extends Controller
{
    public function home()
    {
        $page = Page::where('slug', 'home')
            ->where('is_published', true)
            ->firstOrFail();

        return $this->renderPage($page);
    }

    public function show(string $slug)
    {
        $page = Page::where('slug', $slug)
            ->where('is_published', true)
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

        $pageWidgets = $page->pageWidgets()
            ->with(['widgetType', 'children.widgetType', 'children.children.widgetType'])
            ->where('is_active', true)
            ->whereNull('parent_widget_id')
            ->orderBy('sort_order')
            ->get();

        $blocks         = [];
        $inlineStyles   = '';
        $inlineScripts  = '';

        foreach ($pageWidgets as $pw) {
            [$blockData, $styles, $scripts] = $this->renderWidget($pw);
            $blocks[]        = $blockData;
            $inlineStyles   .= $styles;
            $inlineScripts  .= $scripts;
        }

        return view('pages.show', compact('page', 'blocks', 'inlineStyles', 'inlineScripts'));
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

        if ($widgetType->render_mode === 'server') {
            if ($widgetType->handle === 'column_widget') {
                $children = $this->renderColumnChildren($pw);
                $html = $widgetType->template
                    ? Blade::render($widgetType->template, ['config' => $config, 'children' => $children])
                    : '';
            } else {
                $html = $widgetType->template
                    ? Blade::render($widgetType->template, ['config' => $config])
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
