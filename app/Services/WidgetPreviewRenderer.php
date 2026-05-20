<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\PageWidget;

class WidgetPreviewRenderer
{
    public function __construct(
        private AppearanceStyleComposer $styleComposer,
        private DemoDataService $demoService,
    ) {}

    public function render(PageWidget $pw, string $slotHandle = 'page_builder_canvas'): string
    {
        $widgetType = $pw->widgetType;

        try {
            $fallbackData = $this->demoCollectionData($pw);
            // The builder preview is the ONE caller that opts into inline
            // editing — every other render path (public site, chrome, demo
            // tool) leaves the default false so editing scaffolding stays
            // out of public output (session 305).
            $result = WidgetRenderer::render($pw, [], $fallbackData, $slotHandle, null, true);

            if ($result['html'] === null) {
                return '<div class="widget-preview-notice">No preview available</div>';
            }

            $composed = $this->styleComposer->compose($pw);
            $inlineStyle = $composed['inline_style'];
            $bgFullWidth = $composed['background_full_width'];
            $contentFullWidth = $composed['content_full_width'];

            $rawHtml = preg_replace('#<script\b(?![^>]*type=["\']application/json["\'])[^>]*>.*?</script>#si', '', $result['html']);

            $widgetDiv = '<div class="widget widget--' . e($widgetType->handle) . '"'
                . ' id="widget-' . e($pw->id) . '"'
                . ($inlineStyle ? ' style="' . e($inlineStyle) . '"' : '')
                . '>';

            if ($pw->layout_id !== null) {
                // Column children render without wrappers: the enclosing
                // .layout-column handles layout, matching PageController.
                $rendered = $widgetDiv . $rawHtml . '</div>';
            } else {
                $innerHtml = $contentFullWidth
                    ? $rawHtml
                    : '<div class="site-container">' . $rawHtml . '</div>';

                $rendered = $bgFullWidth
                    ? $widgetDiv . $innerHtml . '</div>'
                    : '<div class="site-container">' . $widgetDiv . $innerHtml . '</div></div>';
            }

            $styles = $result['styles'] ? '<style>' . $result['styles'] . '</style>' : '';

            return $styles . $rendered;
        } catch (\Throwable $e) {
            return '<div class="widget-preview-notice widget-preview-notice--error">Preview error: ' . e($e->getMessage()) . '</div>';
        }
    }

    public function demoCollectionData(PageWidget $pw): array
    {
        $widgetType = $pw->widgetType;
        if (! $widgetType || empty($widgetType->collections)) {
            return [];
        }

        $fallback = [];

        foreach ($widgetType->collections as $collSlot) {
            $collHandle = $pw->config['collection_handle'] ?? $collSlot;
            $collection = Collection::where('handle', $collHandle)->first();
            $sourceType = $collection?->source_type ?? $collSlot;
            $fallback[$collSlot] = $this->demoService->generateCollectionData($sourceType, 3, $collection);
        }

        return $fallback;
    }

    public function collectLibs(PageWidget $pw, array &$libs): void
    {
        $assets = $pw->widgetType?->assets ?? [];
        foreach ($assets['libs'] ?? [] as $lib) {
            $libs[] = $lib;
        }
    }
}
