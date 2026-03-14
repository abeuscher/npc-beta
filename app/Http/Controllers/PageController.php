<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\WidgetDataResolver;
use Illuminate\Support\Facades\Blade;

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

        return $this->renderPage($page);
    }

    private function renderPage(Page $page)
    {
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

            $queryConfig = $pw->query_config ?? [];

            // Resolve collection data for each declared collection handle.
            $collectionData = [];
            foreach ($widgetType->collections ?? [] as $handle) {
                $perHandleConfig = $queryConfig[$handle] ?? [];
                $collectionData[$handle] = WidgetDataResolver::resolve($handle, $perHandleConfig);
            }

            if ($widgetType->render_mode === 'server') {
                $html = $widgetType->template
                    ? Blade::render($widgetType->template, $collectionData)
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
                // Client mode: inject JSON data as window variables, then append code.
                $clientHtml = '';

                foreach ($collectionData as $handle => $data) {
                    $varName = $widgetType->variable_name ?? $handle;
                    $clientHtml .= '<script>window.' . e($varName) . ' = ' . json_encode($data) . ';</script>' . "\n";
                }

                if ($widgetType->code) {
                    $clientHtml .= '<script>' . $widgetType->code . '</script>';
                }

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
