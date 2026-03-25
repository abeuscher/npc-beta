<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Product;
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

            $config      = $pw->config ?? [];
            $queryConfig = $pw->query_config ?? [];

            // Resolve collection data for each declared collection handle.
            $collectionData = [];
            foreach ($widgetType->collections ?? [] as $handle) {
                $perHandleConfig = $queryConfig[$handle] ?? [];
                $collectionData[$handle] = WidgetDataResolver::resolve($handle, $perHandleConfig);
            }

            // Inject event data for event-aware widget types
            $eventData = [];
            if (isset($config['event_id'])) {
                $resolvedEvent = \App\Models\Event::find($config['event_id']);
                if ($resolvedEvent) {
                    $eventData = [
                        'event' => $resolvedEvent,
                    ];
                }
            }

            // Inject product data for product_display widget type
            $productData = [];
            if (isset($config['product_slug'])) {
                $resolvedProduct = Product::with('prices')
                    ->where('slug', $config['product_slug'])
                    ->where('status', 'published')
                    ->first();
                if ($resolvedProduct) {
                    $productData = ['product' => $resolvedProduct];
                }
            }

            if ($widgetType->render_mode === 'server') {
                $html = $widgetType->template
                    ? Blade::render(
                        $widgetType->template,
                        array_merge($collectionData, $eventData, $productData, ['config' => $config])
                    )
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
