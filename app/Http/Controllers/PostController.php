<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\InlineImageRenderer;
use App\Services\PageContext;
use Illuminate\Support\Facades\Blade;
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

        $pageWidgets = $page->pageWidgets()
            ->with('widgetType')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $blocks        = [];
        $inlineStyles  = '';
        $inlineScripts = '';

        foreach ($pageWidgets as $pw) {
            $widgetType = $pw->widgetType;

            if (! $widgetType) {
                continue;
            }

            $config = $pw->config ?? [];

            // Process inline images in richtext config fields
            foreach ($widgetType->config_schema ?? [] as $field) {
                if (($field['type'] ?? '') === 'richtext' && ! empty($config[$field['key']])) {
                    $config[$field['key']] = InlineImageRenderer::process($config[$field['key']]);
                }
            }

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
