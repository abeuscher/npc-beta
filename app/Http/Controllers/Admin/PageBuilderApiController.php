<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Event;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\SiteSetting;
use App\Models\Tag;
use App\Models\WidgetType;
use App\Services\DemoDataService;
use App\Services\PageBuilderDataSources;
use App\Services\WidgetRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageBuilderApiController extends Controller
{
    // ── Widget CRUD ──────────────────────────────────────────────────────────

    public function index(Page $page): JsonResponse
    {
        abort_unless(auth()->user()?->can('view_page'), 403);

        return response()->json($this->buildTree($page));
    }

    public function store(Request $request, Page $page): JsonResponse
    {
        abort_unless(auth()->user()?->can('update_page'), 403);

        $validated = $request->validate([
            'widget_type_id'   => 'required|uuid|exists:widget_types,id',
            'label'            => 'nullable|string|max:255',
            'parent_widget_id' => 'nullable|uuid',
            'column_index'     => 'nullable|integer|min:0',
            'insert_position'  => 'nullable|integer|min:0',
        ]);

        $widgetType = WidgetType::findOrFail($validated['widget_type_id']);

        // Validate parent belongs to this page
        $parentId = $validated['parent_widget_id'] ?? null;
        if ($parentId) {
            abort_unless(
                PageWidget::where('id', $parentId)->where('page_id', $page->id)->exists(),
                422,
                'Parent widget does not belong to this page.'
            );
        }

        // Auto-generate label if not provided
        $label = $validated['label'] ?? '';
        if (blank($label)) {
            $count = PageWidget::where('page_id', $page->id)
                ->where('widget_type_id', $widgetType->id)
                ->count();
            $label = $widgetType->label . ' ' . ($count + 1);
        }

        $position = $validated['insert_position'] ?? null;

        if ($parentId) {
            $columnIndex = $validated['column_index'] ?? 0;
            if ($position === null) {
                $position = (PageWidget::where('parent_widget_id', $parentId)
                    ->where('column_index', $columnIndex)
                    ->max('sort_order') ?? -1) + 1;
            } else {
                PageWidget::where('parent_widget_id', $parentId)
                    ->where('column_index', $columnIndex)
                    ->where('sort_order', '>=', $position)
                    ->increment('sort_order');
            }
        } else {
            if ($position === null) {
                $position = (PageWidget::where('page_id', $page->id)
                    ->whereNull('parent_widget_id')
                    ->max('sort_order') ?? -1) + 1;
            } else {
                PageWidget::where('page_id', $page->id)
                    ->whereNull('parent_widget_id')
                    ->where('sort_order', '>=', $position)
                    ->increment('sort_order');
            }
        }

        $newWidget = PageWidget::create([
            'page_id'          => $page->id,
            'widget_type_id'   => $widgetType->id,
            'parent_widget_id' => $parentId,
            'column_index'     => $parentId ? ($validated['column_index'] ?? 0) : null,
            'label'            => $label,
            'config'           => $widgetType->getDefaultConfig(),
            'query_config'     => [],
            'style_config'     => [
                'background_color' => '#ffffff',
                'text_color'       => '#000000',
            ],
            'sort_order'       => $position,
            'is_active'        => true,
        ]);

        $tree = $this->buildTree($page);

        return response()->json([
            'widget' => $this->formatWidget($newWidget->fresh(['widgetType']), $page),
            'tree'   => $tree['widgets'],
            'required_libs' => $tree['required_libs'],
        ], 201);
    }

    public function update(Request $request, PageWidget $widget): JsonResponse
    {
        abort_unless(auth()->user()?->can('update_page'), 403);

        $validated = $request->validate([
            'label'        => 'nullable|string|max:255',
            'config'       => 'nullable|array',
            'style_config' => 'nullable|array',
            'query_config' => 'nullable|array',
        ]);

        $updates = [];
        if (array_key_exists('label', $validated)) {
            $updates['label'] = $validated['label'];
        }
        if (array_key_exists('config', $validated)) {
            $updates['config'] = $validated['config'];
        }
        if (array_key_exists('style_config', $validated)) {
            $updates['style_config'] = $validated['style_config'];
        }
        if (array_key_exists('query_config', $validated)) {
            $updates['query_config'] = $validated['query_config'];
        }

        if (! empty($updates)) {
            $widget->update($updates);
        }

        return response()->json([
            'widget' => $this->formatWidget($widget->fresh(['widgetType']), $widget->page),
        ]);
    }

    public function destroy(PageWidget $widget): JsonResponse
    {
        abort_unless(auth()->user()?->can('update_page'), 403);

        $page = $widget->page;

        // Prevent deleting required widgets
        $requiredHandles = WidgetType::requiredForPage(
            $this->computeBareSlug($page)
        );

        if (in_array($widget->widgetType?->handle ?? '', $requiredHandles, true)) {
            return response()->json(['error' => 'This widget is required and cannot be deleted.'], 403);
        }

        // Delete children first, then the widget itself
        PageWidget::where('parent_widget_id', $widget->id)->delete();
        $widget->delete();

        $tree = $this->buildTree($page);

        return response()->json([
            'deleted'       => true,
            'tree'          => $tree['widgets'],
            'required_libs' => $tree['required_libs'],
        ]);
    }

    public function copy(PageWidget $widget): JsonResponse
    {
        abort_unless(auth()->user()?->can('update_page'), 403);

        $page = $widget->page;

        // Insert copy after the source at the same nesting level
        $siblingQuery = PageWidget::where('page_id', $page->id);
        if ($widget->parent_widget_id) {
            $siblingQuery->where('parent_widget_id', $widget->parent_widget_id)
                ->where('column_index', $widget->column_index);
        } else {
            $siblingQuery->whereNull('parent_widget_id');
        }

        $newPosition = $widget->sort_order + 1;

        $siblingQuery->clone()->where('sort_order', '>=', $newPosition)->increment('sort_order');

        $copy = PageWidget::create([
            'page_id'          => $page->id,
            'widget_type_id'   => $widget->widget_type_id,
            'parent_widget_id' => $widget->parent_widget_id,
            'column_index'     => $widget->column_index,
            'label'            => $widget->label,
            'config'           => $widget->config ?? [],
            'query_config'     => $widget->query_config ?? [],
            'style_config'     => $widget->style_config ?? [],
            'sort_order'       => $newPosition,
            'is_active'        => $widget->is_active,
        ]);

        // Recursively copy children
        if ($widget->children()->exists()) {
            PageWidget::copyBetweenPages($page->id, $page->id, $widget->id, $copy->id);
        }

        $tree = $this->buildTree($page);

        return response()->json([
            'widget'        => $this->formatWidget($copy->fresh(['widgetType']), $page),
            'tree'          => $tree['widgets'],
            'required_libs' => $tree['required_libs'],
        ], 201);
    }

    public function reorder(Request $request, Page $page): JsonResponse
    {
        abort_unless(auth()->user()?->can('update_page'), 403);

        $validated = $request->validate([
            'items'                    => 'required|array|min:1',
            'items.*.id'              => 'required|uuid',
            'items.*.parent_widget_id' => 'nullable|uuid',
            'items.*.column_index'     => 'nullable|integer|min:0',
            'items.*.sort_order'       => 'required|integer|min:0',
        ]);

        $items = $validated['items'];
        $itemIds = collect($items)->pluck('id')->all();

        // All widget IDs must belong to this page
        $validCount = PageWidget::where('page_id', $page->id)
            ->whereIn('id', $itemIds)
            ->count();

        if ($validCount !== count(array_unique($itemIds))) {
            return response()->json(['error' => 'Invalid widget IDs.'], 422);
        }

        // Prevent column widget nesting
        $columnWidgetIds = PageWidget::where('page_id', $page->id)
            ->whereIn('id', $itemIds)
            ->whereHas('widgetType', fn ($q) => $q->where('handle', 'column_widget'))
            ->pluck('id')
            ->all();

        foreach ($items as $item) {
            if (in_array($item['id'], $columnWidgetIds) && ! empty($item['parent_widget_id'])) {
                return response()->json(['error' => 'Column widgets cannot be nested.'], 422);
            }
        }

        foreach ($items as $item) {
            PageWidget::where('id', $item['id'])
                ->where('page_id', $page->id)
                ->update([
                    'parent_widget_id' => ! empty($item['parent_widget_id']) ? $item['parent_widget_id'] : null,
                    'column_index'     => $item['column_index'] ?? null,
                    'sort_order'       => (int) $item['sort_order'],
                ]);
        }

        $tree = $this->buildTree($page);

        return response()->json([
            'tree'          => $tree['widgets'],
            'required_libs' => $tree['required_libs'],
        ]);
    }

    // ── Preview ──────────────────────────────────────────────────────────────

    public function preview(PageWidget $widget): JsonResponse
    {
        abort_unless(auth()->user()?->can('view_page'), 403);

        $widget->load(['widgetType', 'children.widgetType', 'children.children.widgetType']);

        $previewData = $this->renderWidgetForPreview($widget);

        $libs = [];
        $this->collectLibs($widget, $libs);

        return response()->json([
            'id'            => $widget->id,
            'html'          => $previewData['html'],
            'required_libs' => array_values(array_unique($libs)),
        ]);
    }

    // ── Lookups ──────────────────────────────────────────────────────────────

    public function widgetTypes(Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->can('view_page'), 403);

        $pageType = $request->query('page_type', 'default');

        $types = WidgetType::orderBy('label')
            ->with('media')
            ->get()
            ->filter(fn ($wt) => $wt->allowed_page_types === null || in_array($pageType, $wt->allowed_page_types, true))
            ->map(fn ($wt) => [
                'id'              => $wt->id,
                'handle'          => $wt->handle,
                'label'           => $wt->label,
                'description'     => $wt->description,
                'category'        => $wt->category ?? ['content'],
                'config_schema'   => $wt->config_schema,
                'collections'     => $wt->collections,
                'assets'          => $wt->assets ?? [],
                'full_width'      => $wt->full_width,
                'default_open'    => $wt->default_open,
                'required_config' => $wt->required_config,
                'thumbnail'       => $wt->getFirstMediaUrl('thumbnail', 'picker') ?: null,
                'thumbnail_hover' => $wt->getFirstMediaUrl('thumbnail_hover', 'picker') ?: null,
            ])
            ->values();

        return response()->json(['widget_types' => $types]);
    }

    public function collections(): JsonResponse
    {
        abort_unless(auth()->user()?->can('view_page'), 403);

        $collections = Collection::where('is_active', true)
            ->orderBy('name')
            ->get(['handle', 'name', 'source_type'])
            ->map(fn ($c) => [
                'handle'      => $c->handle,
                'name'        => $c->name,
                'source_type' => $c->source_type,
            ]);

        return response()->json(['collections' => $collections]);
    }

    public function collectionFields(string $handle): JsonResponse
    {
        abort_unless(auth()->user()?->can('view_page'), 403);

        $collection = Collection::where('handle', $handle)->firstOrFail();

        $fields = collect($collection->fields ?? [])->map(fn ($f) => [
            'key'   => $f['key'] ?? '',
            'label' => $f['label'] ?? '',
            'type'  => $f['type'] ?? 'text',
        ]);

        return response()->json(['fields' => $fields]);
    }

    public function tags(): JsonResponse
    {
        abort_unless(auth()->user()?->can('view_page'), 403);

        $tags = Tag::where('type', 'collection')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return response()->json(['tags' => $tags]);
    }

    public function pages(): JsonResponse
    {
        abort_unless(auth()->user()?->can('view_page'), 403);

        $pages = Page::published()
            ->where('type', 'default')
            ->orderBy('title')
            ->get(['slug', 'title'])
            ->map(fn ($p) => ['slug' => $p->slug, 'title' => $p->title]);

        return response()->json(['pages' => $pages]);
    }

    public function events(): JsonResponse
    {
        abort_unless(auth()->user()?->can('view_page'), 403);

        $events = Event::published()
            ->orderBy('starts_at')
            ->get(['slug', 'title'])
            ->map(fn ($e) => ['slug' => $e->slug, 'title' => $e->title]);

        return response()->json(['events' => $events]);
    }

    public function dataSources(string $source): JsonResponse
    {
        abort_unless(auth()->user()?->can('view_page'), 403);

        return response()->json([
            'options' => PageBuilderDataSources::resolve($source),
        ]);
    }

    // ── Image upload ─────────────────────────────────────────────────────────

    public function uploadImage(Request $request, PageWidget $widget): JsonResponse
    {
        abort_unless(auth()->user()?->can('update_page'), 403);

        $request->validate([
            'key'  => 'required|string|max:255',
            'file' => 'required|file|mimes:png,jpg,jpeg,gif,webp,svg,mp4,webm|max:51200',
        ]);

        $key = $request->input('key');
        $collectionName = "config_{$key}";

        $widget->clearMediaCollection($collectionName);

        $media = $widget->addMedia($request->file('file'))
            ->usingFileName($request->file('file')->hashName())
            ->toMediaCollection($collectionName, 'public');

        // Update config with media ID
        $config = $widget->config ?? [];
        $config[$key] = $media->id;
        $widget->update(['config' => $config]);

        return response()->json([
            'media_id' => $media->id,
            'url'      => $media->getUrl(),
        ]);
    }

    public function removeImage(PageWidget $widget, string $key): JsonResponse
    {
        abort_unless(auth()->user()?->can('update_page'), 403);

        $widget->clearMediaCollection("config_{$key}");

        $config = $widget->config ?? [];
        $config[$key] = null;
        $widget->update(['config' => $config]);

        return response()->json(['removed' => true]);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function buildTree(Page $page): array
    {
        $requiredHandles = WidgetType::requiredForPage(
            $this->computeBareSlug($page)
        );

        $widgets = PageWidget::where('page_id', $page->id)
            ->whereNull('parent_widget_id')
            ->where('is_active', true)
            ->with(['widgetType', 'children.widgetType', 'children.children.widgetType'])
            ->orderBy('sort_order')
            ->get();

        $allLibs = [];
        $formatted = [];

        foreach ($widgets as $pw) {
            if (! $pw->widgetType) {
                continue;
            }

            $formatted[] = $this->formatWidgetWithPreview($pw, $requiredHandles);
            $this->collectLibs($pw, $allLibs);
        }

        return [
            'widgets'       => $formatted,
            'required_libs' => array_values(array_unique($allLibs)),
        ];
    }

    private function formatWidget(PageWidget $pw, Page $page): array
    {
        $requiredHandles = WidgetType::requiredForPage(
            $this->computeBareSlug($page)
        );

        return [
            'id'                        => $pw->id,
            'widget_type_id'            => $pw->widget_type_id,
            'widget_type_handle'        => $pw->widgetType?->handle ?? '',
            'widget_type_label'         => $pw->widgetType?->label ?? 'Unknown',
            'widget_type_collections'   => $pw->widgetType?->collections ?? [],
            'widget_type_config_schema' => $pw->widgetType?->config_schema ?? [],
            'widget_type_assets'        => $pw->widgetType?->assets ?? [],
            'widget_type_default_open'  => $pw->widgetType?->default_open ?? false,
            'widget_type_required_config' => $pw->widgetType?->required_config,
            'parent_widget_id'          => $pw->parent_widget_id,
            'column_index'              => $pw->column_index,
            'label'                     => $pw->label ?? '',
            'config'                    => $pw->config ?? [],
            'query_config'              => $pw->query_config ?? [],
            'style_config'              => $pw->style_config ?? [],
            'sort_order'                => $pw->sort_order ?? 0,
            'is_active'                 => $pw->is_active,
            'is_required'               => in_array($pw->widgetType?->handle ?? '', $requiredHandles, true),
            'image_urls'                => $this->resolveImageUrls($pw),
        ];
    }

    private function formatWidgetWithPreview(PageWidget $pw, array $requiredHandles): array
    {
        $data = [
            'id'                        => $pw->id,
            'widget_type_id'            => $pw->widget_type_id,
            'widget_type_handle'        => $pw->widgetType?->handle ?? '',
            'widget_type_label'         => $pw->widgetType?->label ?? 'Unknown',
            'widget_type_collections'   => $pw->widgetType?->collections ?? [],
            'widget_type_config_schema' => $pw->widgetType?->config_schema ?? [],
            'widget_type_assets'        => $pw->widgetType?->assets ?? [],
            'widget_type_default_open'  => $pw->widgetType?->default_open ?? false,
            'widget_type_required_config' => $pw->widgetType?->required_config,
            'parent_widget_id'          => $pw->parent_widget_id,
            'column_index'              => $pw->column_index,
            'label'                     => $pw->label ?? '',
            'config'                    => $pw->config ?? [],
            'query_config'              => $pw->query_config ?? [],
            'style_config'              => $pw->style_config ?? [],
            'sort_order'                => $pw->sort_order ?? 0,
            'is_active'                 => $pw->is_active,
            'is_required'               => in_array($pw->widgetType?->handle ?? '', $requiredHandles, true),
            'image_urls'                => $this->resolveImageUrls($pw),
            'preview_html'              => $this->renderWidgetForPreview($pw)['html'],
            'children'                  => $this->formatChildren($pw, $requiredHandles),
        ];

        return $data;
    }

    private function formatChildren(PageWidget $pw, array $requiredHandles): array
    {
        $grouped = [];

        foreach ($pw->children as $child) {
            if (! $child->is_active || ! $child->widgetType) {
                continue;
            }

            $idx = $child->column_index ?? 0;
            $grouped[$idx][] = [
                'id'                        => $child->id,
                'widget_type_id'            => $child->widget_type_id,
                'widget_type_handle'        => $child->widgetType?->handle ?? '',
                'widget_type_label'         => $child->widgetType?->label ?? 'Unknown',
                'widget_type_collections'   => $child->widgetType?->collections ?? [],
                'widget_type_config_schema' => $child->widgetType?->config_schema ?? [],
                'widget_type_assets'        => $child->widgetType?->assets ?? [],
                'widget_type_default_open'  => $child->widgetType?->default_open ?? false,
                'widget_type_required_config' => $child->widgetType?->required_config,
                'parent_widget_id'          => $child->parent_widget_id,
                'column_index'              => $child->column_index,
                'label'                     => $child->label ?? '',
                'config'                    => $child->config ?? [],
                'query_config'              => $child->query_config ?? [],
                'style_config'              => $child->style_config ?? [],
                'sort_order'                => $child->sort_order ?? 0,
                'is_active'                 => $child->is_active,
                'is_required'               => in_array($child->widgetType?->handle ?? '', $requiredHandles, true),
                'image_urls'                => $this->resolveImageUrls($child),
            ];
        }

        return $grouped;
    }

    private function renderWidgetForPreview(PageWidget $pw): array
    {
        $widgetType = $pw->widgetType;

        try {
            $columnChildren = [];
            if ($widgetType->handle === 'column_widget') {
                $columnChildren = $this->renderColumnChildren($pw);
            }

            $fallbackData = $this->buildDemoCollectionData($pw);
            $result = WidgetRenderer::render($pw, $columnChildren, $fallbackData);

            if ($result['html'] === null) {
                $html = '<div class="widget-preview-notice">No preview available</div>';
            } else {
                $handle = $widgetType->handle;
                $sc = $pw->style_config ?? [];
                $inlineStyle = self::buildInlineStyles($sc);

                $configFullWidth = $pw->config['full_width'] ?? null;
                $styleFullWidth = $sc['full_width'] ?? null;
                $isFullWidth = $configFullWidth !== null ? (bool) $configFullWidth
                    : ($styleFullWidth !== null ? (bool) $styleFullWidth : ($widgetType->full_width ?? false));

                $innerHtml = $isFullWidth
                    ? $result['html']
                    : '<div class="site-container">' . $result['html'] . '</div>';

                $styles = $result['styles'] ? '<style>' . $result['styles'] . '</style>' : '';
                $innerHtml = preg_replace('#<script\b(?![^>]*type=["\']application/json["\'])[^>]*>.*?</script>#si', '', $innerHtml);

                $html = $styles
                    . '<div class="widget widget--' . e($handle) . '"'
                    . ' id="widget-' . e($pw->id) . '"'
                    . ($inlineStyle ? ' style="' . e($inlineStyle) . '"' : '')
                    . '>' . $innerHtml . '</div>';
            }
        } catch (\Throwable $e) {
            $html = '<div class="widget-preview-notice widget-preview-notice--error">Preview error: ' . e($e->getMessage()) . '</div>';
        }

        return [
            'id'   => $pw->id,
            'html' => $html,
        ];
    }

    private function renderColumnChildren(PageWidget $pw): array
    {
        $children = [];

        foreach ($pw->children as $child) {
            if (! $child->is_active) {
                continue;
            }

            $childColumnChildren = [];
            if ($child->widgetType?->handle === 'column_widget') {
                $childColumnChildren = $this->renderColumnChildren($child);
            }

            $childFallback = $this->buildDemoCollectionData($child);
            $result = WidgetRenderer::render($child, $childColumnChildren, $childFallback);

            if ($result['html'] === null) {
                continue;
            }

            $sc = $child->style_config ?? [];

            $configFullWidth = $child->config['full_width'] ?? null;
            $styleFullWidth = $sc['full_width'] ?? null;
            $isFullWidth = $configFullWidth !== null ? (bool) $configFullWidth
                : ($styleFullWidth !== null ? (bool) $styleFullWidth : ($child->widgetType->full_width ?? false));

            $idx = $child->column_index ?? 0;
            $children[$idx][] = [
                'handle'       => $child->widgetType->handle,
                'instance_id'  => $child->id,
                'html'         => $result['html'],
                'css'          => $child->widgetType->css ?? '',
                'js'           => $child->widgetType->js ?? '',
                'style_config' => $sc,
                'full_width'   => $isFullWidth,
            ];
        }

        return $children;
    }

    private static function buildInlineStyles(array $styleConfig): string
    {
        $styleProps = [];

        if (! empty($styleConfig['background_color'])) {
            $styleProps[] = 'background-color:' . $styleConfig['background_color'];
        }
        if (! empty($styleConfig['text_color'])) {
            $styleProps[] = 'color:' . $styleConfig['text_color'];
        }

        $spacingKeys = [
            'padding_top' => 'padding-top', 'padding_right' => 'padding-right',
            'padding_bottom' => 'padding-bottom', 'padding_left' => 'padding-left',
            'margin_top' => 'margin-top', 'margin_right' => 'margin-right',
            'margin_bottom' => 'margin-bottom', 'margin_left' => 'margin-left',
        ];
        foreach ($spacingKeys as $key => $cssProp) {
            $val = isset($styleConfig[$key]) && $styleConfig[$key] !== '' ? (int) $styleConfig[$key] : null;
            if ($val !== null) {
                $styleProps[] = $cssProp . ':' . $val . 'px';
            }
        }

        return implode(';', $styleProps);
    }

    private function buildDemoCollectionData(PageWidget $pw): array
    {
        $widgetType = $pw->widgetType;
        if (! $widgetType || empty($widgetType->collections)) {
            return [];
        }

        $demoService = app(DemoDataService::class);
        $fallback = [];

        foreach ($widgetType->collections as $collSlot) {
            $collHandle = $pw->config['collection_handle'] ?? $collSlot;
            $collection = Collection::where('handle', $collHandle)->first();
            $sourceType = $collection?->source_type ?? $collSlot;
            $fallback[$collSlot] = $demoService->generateCollectionData($sourceType, 3, $collection);
        }

        return $fallback;
    }

    private function collectLibs(PageWidget $pw, array &$libs): void
    {
        $assets = $pw->widgetType?->assets ?? [];
        foreach ($assets['libs'] ?? [] as $lib) {
            $libs[] = $lib;
        }

        foreach ($pw->children as $child) {
            if ($child->is_active) {
                $this->collectLibs($child, $libs);
            }
        }
    }

    // ── Color Swatches ────────────────────────────────────────────────────

    public function updateColorSwatches(Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->can('update_page'), 403);

        $validated = $request->validate([
            'swatches'   => 'required|array',
            'swatches.*' => 'string|max:30',
        ]);

        SiteSetting::set('editor_color_swatches', json_encode($validated['swatches']));

        return response()->json(['swatches' => $validated['swatches']]);
    }

    // ── Private helpers ─────────────────────────────────────────────────

    private function resolveImageUrls(PageWidget $pw): array
    {
        $urls = [];
        $schema = $pw->widgetType?->config_schema ?? [];

        foreach ($schema as $field) {
            if (in_array($field['type'] ?? '', ['image', 'video'])) {
                $media = $pw->getFirstMedia("config_{$field['key']}");
                $urls[$field['key']] = $media?->getUrl();
            }
        }

        return $urls;
    }

    private function computeBareSlug(Page $page): string
    {
        $prefix = match ($page->type) {
            'system' => SiteSetting::get('system_prefix', 'system'),
            'member' => SiteSetting::get('portal_prefix', 'members'),
            default  => '',
        };

        if ($prefix !== '' && str_starts_with($page->slug, $prefix . '/')) {
            return substr($page->slug, strlen($prefix) + 1);
        }

        return $page->slug;
    }
}
