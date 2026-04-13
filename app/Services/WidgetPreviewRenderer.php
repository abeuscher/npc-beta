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

    public function render(PageWidget $pw): string
    {
        $widgetType = $pw->widgetType;

        try {
            $fallbackData = $this->demoCollectionData($pw);
            $result = WidgetRenderer::render($pw, [], $fallbackData);

            if ($result['html'] === null) {
                return '<div class="widget-preview-notice">No preview available</div>';
            }

            $composed = $this->styleComposer->compose($pw);
            $inlineStyle = $composed['inline_style'];

            $configFullWidth = $pw->config['full_width'] ?? null;
            $isFullWidth = $configFullWidth !== null ? (bool) $configFullWidth : $composed['is_full_width'];

            $innerHtml = $isFullWidth
                ? $result['html']
                : '<div class="site-container">' . $result['html'] . '</div>';

            $innerHtml = preg_replace('#<script\b(?![^>]*type=["\']application/json["\'])[^>]*>.*?</script>#si', '', $innerHtml);

            $styles = $result['styles'] ? '<style>' . $result['styles'] . '</style>' : '';

            return $styles
                . '<div class="widget widget--' . e($widgetType->handle) . '"'
                . ' id="widget-' . e($pw->id) . '"'
                . ($inlineStyle ? ' style="' . e($inlineStyle) . '"' : '')
                . '>' . $innerHtml . '</div>';
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
