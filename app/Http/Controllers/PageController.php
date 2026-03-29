<?php

namespace App\Http\Controllers;

use App\Models\Page;
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
            ->with('widgetType')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $blocks         = [];
        $inlineStyles   = '';
        $inlineScripts  = '';

        foreach ($pageWidgets as $pw) {
            $widgetType = $pw->widgetType;

            if (! $widgetType) {
                continue;
            }

            $config = $pw->config ?? [];

            if ($widgetType->render_mode === 'server') {
                $html = $widgetType->template
                    ? Blade::render($widgetType->template, ['config' => $config])
                    : '';

                $blocks[] = [
                    'handle'      => $widgetType->handle,
                    'instance_id' => $pw->id,
                    'html'        => $html,
                    'css'         => $widgetType->css ?? '',
                    'js'          => $widgetType->js ?? '',
                ];

                if ($widgetType->css) {
                    $inlineStyles .= "\n" . $widgetType->css;
                }

                if ($widgetType->js) {
                    $inlineScripts .= "\n" . $widgetType->js;
                }
            } else {
                // Client mode: append code.
                $clientHtml = $widgetType->code
                    ? '<script>' . $widgetType->code . '</script>'
                    : '';

                $blocks[] = [
                    'handle'      => $widgetType->handle,
                    'instance_id' => $pw->id,
                    'html'        => $clientHtml,
                    'css'         => $widgetType->css ?? '',
                    'js'          => '',
                ];

                if ($widgetType->css) {
                    $inlineStyles .= "\n" . $widgetType->css;
                }
            }
        }

        return view('pages.show', compact('page', 'blocks', 'inlineStyles', 'inlineScripts'));
    }
}
