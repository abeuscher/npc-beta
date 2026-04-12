<?php

namespace App\Services\ImportExport;

use App\Models\Page;
use App\Models\PageLayout;
use App\Models\PageWidget;
use App\Models\Template;
use Illuminate\Support\Collection;

class ContentExporter
{
    public const FORMAT_VERSION = '1.1.0';

    /**
     * Export one or more pages (by id) into a bundle envelope.
     *
     * @param  array<int, string>  $pageIds
     * @return array<string, mixed>
     */
    public function exportPages(array $pageIds): array
    {
        return $this->envelope([
            'templates' => [],
            'pages'     => $this->serializePages($pageIds),
        ]);
    }

    /**
     * Export one or more templates (by id) into a bundle envelope.
     * Page templates pull in their associated header/footer system pages.
     *
     * @param  array<int, string>  $templateIds
     * @return array<string, mixed>
     */
    public function exportTemplates(array $templateIds): array
    {
        $templates     = Template::whereIn('id', $templateIds)->get();
        $nestedPageIds = $this->collectChromePageIds($templates);

        return $this->envelope([
            'templates' => $this->serializeTemplates($templates),
            'pages'     => $this->serializePages($nestedPageIds),
        ]);
    }

    /**
     * Export a combined bundle of pages and templates.
     *
     * @param  array<int, string>  $pageIds
     * @param  array<int, string>  $templateIds
     * @return array<string, mixed>
     */
    public function exportBundle(array $pageIds, array $templateIds): array
    {
        $templates     = Template::whereIn('id', $templateIds)->get();
        $nestedPageIds = $this->collectChromePageIds($templates);
        $allPageIds    = array_values(array_unique(array_merge($pageIds, $nestedPageIds)));

        return $this->envelope([
            'templates' => $this->serializeTemplates($templates),
            'pages'     => $this->serializePages($allPageIds),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function envelope(array $payload): array
    {
        return [
            'format_version' => self::FORMAT_VERSION,
            'exported_at'    => now()->toIso8601String(),
            'payload'        => $payload,
        ];
    }

    /**
     * @param  Collection<int, Template>  $templates
     * @return array<int, string>
     */
    protected function collectChromePageIds(Collection $templates): array
    {
        $ids = [];
        foreach ($templates as $template) {
            if ($template->type !== 'page') {
                continue;
            }
            if ($template->header_page_id) {
                $ids[] = $template->header_page_id;
            }
            if ($template->footer_page_id) {
                $ids[] = $template->footer_page_id;
            }
        }

        return array_values(array_unique($ids));
    }

    // ── Page serialization ──────────────────────────────────────────────────

    /**
     * @param  array<int, string>  $pageIds
     * @return array<int, array<string, mixed>>
     */
    protected function serializePages(array $pageIds): array
    {
        if (empty($pageIds)) {
            return [];
        }

        return Page::whereIn('id', $pageIds)
            ->with('media')
            ->get()
            ->map(fn (Page $page) => $this->serializePage($page))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializePage(Page $page): array
    {
        $template = $page->template_id ? Template::find($page->template_id) : null;

        return [
            'id'               => $page->id,
            'title'            => $page->title,
            'slug'             => $page->slug,
            'type'             => $page->type,
            'template_name'    => $template?->name,
            'status'           => $page->status,
            'meta_title'       => $page->meta_title,
            'meta_description' => $page->meta_description,
            'noindex'          => $page->noindex,
            'head_snippet'     => $page->head_snippet,
            'body_snippet'     => $page->body_snippet,
            'custom_fields'    => $page->custom_fields ?? [],
            'published_at'     => $page->published_at?->toIso8601String(),
            'media'            => $this->serializePageMedia($page),
            'widgets'          => $this->serializeWidgetTree($page->id),
        ];
    }

    /**
     * Build media descriptors for any single-file collection registered on the
     * Page model that has an attached file.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function serializePageMedia(Page $page): array
    {
        $descriptors = [];

        foreach (['post_thumbnail', 'post_header', 'og_image'] as $collection) {
            $media = $page->getFirstMedia($collection);
            if (! $media) {
                continue;
            }

            $descriptors[] = [
                'collection_name' => $collection,
                'file_name'       => $media->file_name,
                'disk'            => $media->disk,
                'path'            => $media->id . '/' . $media->file_name,
                'mime_type'       => $media->mime_type,
                'size'            => $media->size,
            ];
        }

        return $descriptors;
    }

    /**
     * Walk the page's widget+layout tree, mirroring PageWidget::serializeStack()
     * but injecting media descriptors per widget.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function serializeWidgetTree(string $pageId): array
    {
        $roots = PageWidget::where('page_id', $pageId)
            ->whereNull('layout_id')
            ->with(['widgetType', 'media'])
            ->orderBy('sort_order')
            ->get();

        $layouts = PageLayout::where('page_id', $pageId)
            ->with(['widgets.widgetType', 'widgets.media'])
            ->orderBy('sort_order')
            ->get();

        $items = [];

        foreach ($roots as $pw) {
            $items[] = ['sort' => $pw->sort_order, 'data' => $this->serializeWidget($pw)];
        }

        foreach ($layouts as $layout) {
            $items[] = ['sort' => $layout->sort_order, 'data' => $this->serializeLayout($layout)];
        }

        usort($items, fn ($a, $b) => $a['sort'] <=> $b['sort']);

        return array_column($items, 'data');
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeWidget(PageWidget $pw): array
    {
        $entry = [
            'type'              => 'widget',
            'handle'            => $pw->widgetType?->handle,
            'label'             => $pw->label,
            'config'            => $pw->config ?? [],
            'query_config'      => $pw->query_config ?? [],
            'appearance_config' => $pw->appearance_config ?? [],
            'sort_order'        => $pw->sort_order,
            'is_active'         => $pw->is_active,
            'media'             => $this->serializeWidgetMedia($pw),
        ];

        if ($pw->column_index !== null) {
            $entry['column_index'] = $pw->column_index;
        }

        return $entry;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeLayout(PageLayout $layout): array
    {
        $slots = [];
        foreach ($layout->widgets as $widget) {
            $idx = $widget->column_index ?? 0;
            $slots[$idx][] = $this->serializeWidget($widget);
        }

        return [
            'type'          => 'layout',
            'label'         => $layout->label,
            'display'       => $layout->display,
            'columns'       => $layout->columns,
            'layout_config' => $layout->layout_config ?? [],
            'sort_order'    => $layout->sort_order,
            'slots'         => $slots,
        ];
    }

    /**
     * Build media descriptors for any image/video field on the widget's config_schema
     * that has an attached Spatie media row.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function serializeWidgetMedia(PageWidget $pw): array
    {
        $widgetType = $pw->widgetType;
        if (! $widgetType) {
            return [];
        }

        $descriptors = [];

        foreach ($widgetType->config_schema ?? [] as $field) {
            if (! in_array($field['type'] ?? '', ['image', 'video'], true)) {
                continue;
            }

            $key = $field['key'] ?? null;
            if (! $key) {
                continue;
            }

            $collectionName = "config_{$key}";
            $media = $pw->getFirstMedia($collectionName);
            if (! $media) {
                continue;
            }

            $descriptors[] = [
                'key'             => $key,
                'collection_name' => $collectionName,
                'file_name'       => $media->file_name,
                'disk'            => $media->disk,
                'path'            => $media->id . '/' . $media->file_name,
                'mime_type'       => $media->mime_type,
                'size'            => $media->size,
            ];
        }

        return $descriptors;
    }

    // ── Template serialization ──────────────────────────────────────────────

    /**
     * @param  Collection<int, Template>  $templates
     * @return array<int, array<string, mixed>>
     */
    protected function serializeTemplates(Collection $templates): array
    {
        return $templates->map(fn (Template $t) => $this->serializeTemplate($t))->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeTemplate(Template $template): array
    {
        $data = [
            'name'        => $template->name,
            'type'        => $template->type,
            'description' => $template->description,
            'is_default'  => $template->is_default,
        ];

        if ($template->type === 'content') {
            $data['definition'] = $template->definition ?? [];

            return $data;
        }

        // Page template — chrome fields and chrome page slug references.
        $data['primary_color']    = $template->primary_color;
        $data['heading_font']     = $template->heading_font;
        $data['body_font']        = $template->body_font;
        $data['header_bg_color']  = $template->header_bg_color;
        $data['footer_bg_color']  = $template->footer_bg_color;
        $data['nav_link_color']   = $template->nav_link_color;
        $data['nav_hover_color']  = $template->nav_hover_color;
        $data['nav_active_color'] = $template->nav_active_color;
        $data['custom_scss']      = $template->custom_scss;
        $data['header_page_slug'] = $template->headerPage?->slug;
        $data['footer_page_slug'] = $template->footerPage?->slug;

        return $data;
    }
}
