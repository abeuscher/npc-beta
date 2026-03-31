<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\Template;
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
            $blockData = $this->renderWidgetBlock($pw);
            if ($blockData) {
                $blocks[] = $blockData['block'];
                $inlineStyles  .= $blockData['styles'];
                $inlineScripts .= $blockData['scripts'];
            }
            WidgetRenderer::collectAssets($pw->widgetType, $widgetAssets);
        }

        return view('pages.show', compact('page', 'blocks', 'inlineStyles', 'inlineScripts', 'widgetAssets'));
    }

    private function renderWidgetBlock(PageWidget $pw): ?array
    {
        $widgetType = $pw->widgetType;

        if (! $widgetType) {
            return null;
        }

        // For column widgets, render children first
        $columnChildren = [];
        if ($widgetType->handle === 'column_widget') {
            $columnChildren = $this->renderColumnChildren($pw);
        }

        $result = WidgetRenderer::render($pw, $columnChildren);

        if ($result['html'] === null) {
            return null;
        }

        $block = [
            'handle'       => $widgetType->handle,
            'instance_id'  => $pw->id,
            'html'         => $result['html'],
            'css'          => $widgetType->css ?? '',
            'js'           => $widgetType->js ?? '',
            'style_config' => $pw->style_config ?? [],
        ];

        return ['block' => $block, 'styles' => $result['styles'], 'scripts' => $result['scripts']];
    }

    private function renderColumnChildren(PageWidget $pw): array
    {
        $children = [];

        foreach ($pw->children as $child) {
            if (! $child->is_active) {
                continue;
            }

            $blockData = $this->renderWidgetBlock($child);

            if ($blockData === null) {
                continue;
            }

            $idx = $child->column_index ?? 0;
            $children[$idx][] = $blockData['block'];
        }

        return $children;
    }
}
