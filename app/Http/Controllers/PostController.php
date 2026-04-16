<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\PageBlockRenderer;
use App\Services\PageContext;
use App\Services\WidgetRenderer;
use Illuminate\Support\Facades\View;

class PostController extends Controller
{
    public function index()
    {
        $blogPrefix = config('site.blog_prefix', 'news');

        $page = Page::where('slug', $blogPrefix)
            ->published()
            ->firstOrFail();

        return $this->renderPage($page);
    }

    public function show(string $slug)
    {
        $blogPrefix = config('site.blog_prefix', 'news');

        $page = Page::where('type', 'post')
            ->where('slug', $blogPrefix . '/' . $slug)
            ->published()
            ->firstOrFail();

        return $this->renderPage($page);
    }

    private function renderPage(Page $page)
    {
        $pageContext = new PageContext($page);
        View::share('pageContext', $pageContext);

        $pageWidgets = $page->widgets()
            ->with('widgetType')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $blocks         = [];
        $inlineStyles   = '';
        $inlineScripts  = '';
        $widgetAssets   = ['css' => [], 'js' => [], 'scss' => []];

        $blockRenderer = app(PageBlockRenderer::class);

        foreach ($pageWidgets as $pw) {
            $blockData = $blockRenderer->renderWidgetBlock($pw);
            if ($blockData) {
                $blocks[]       = $blockData['block'];
                $inlineStyles  .= $blockData['styles'];
                $inlineScripts .= $blockData['scripts'];
            }
            WidgetRenderer::collectAssets($pw->widgetType, $widgetAssets);
        }

        $firstPw = $pageWidgets->first();
        $navOverlap = $firstPw
            && $firstPw->widgetType?->handle === 'hero'
            && (($firstPw->config['overlap_nav'] ?? false) == true);
        View::share('__navOverlap', $navOverlap);
        View::share('__navOverlayLinkColor', $navOverlap ? ($firstPw->config['nav_link_color'] ?? '') : '');
        View::share('__navOverlayHoverColor', $navOverlap ? ($firstPw->config['nav_hover_color'] ?? '') : '');

        return view('pages.show', compact('page', 'blocks', 'inlineStyles', 'inlineScripts', 'widgetAssets'));
    }
}
