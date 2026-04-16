<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\PageLayout;
use App\Models\Template;
use App\Services\PageBlockRenderer;
use App\Services\PageContext;
use App\Services\WidgetRenderer;
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

        // Load root widgets and layouts, merge into page flow by sort_order
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

        // Merge into ordered page flow
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
        $widgetAssets    = ['css' => [], 'js' => [], 'scss' => []];

        $blockRenderer = app(PageBlockRenderer::class);

        foreach ($pageItems as $item) {
            if ($item['type'] === 'widget') {
                $pw = $item['data'];
                $blockData = $blockRenderer->renderWidgetBlock($pw);
                if ($blockData) {
                    $blocks[] = $blockData['block'];
                    $inlineStyles  .= $blockData['styles'];
                    $inlineScripts .= $blockData['scripts'];
                }
                WidgetRenderer::collectAssets($pw->widgetType, $widgetAssets);
            } else {
                $layout = $item['data'];
                $layoutBlock = $blockRenderer->renderLayoutBlock($layout, $inlineStyles, $inlineScripts, $widgetAssets);
                if ($layoutBlock) {
                    $blocks[] = $layoutBlock;
                }
            }
        }

        // Check if the first widget is a hero with overlap_nav enabled
        $firstItem = $pageItems->first();
        $firstPw = ($firstItem && $firstItem['type'] === 'widget') ? $firstItem['data'] : null;
        $navOverlap = $firstPw
            && $firstPw->widgetType?->handle === 'hero'
            && (($firstPw->config['overlap_nav'] ?? false) == true);
        View::share('__navOverlap', $navOverlap);
        View::share('__navOverlayLinkColor', $navOverlap ? ($firstPw->config['nav_link_color'] ?? '') : '');
        View::share('__navOverlayHoverColor', $navOverlap ? ($firstPw->config['nav_hover_color'] ?? '') : '');

        return view('pages.show', compact('page', 'blocks', 'inlineStyles', 'inlineScripts', 'widgetAssets'));
    }

}
