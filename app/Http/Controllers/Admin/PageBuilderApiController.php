<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Event;
use App\Models\Page;
use App\Models\PageLayout;
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
            'widget_type_id'  => 'required|uuid|exists:widget_types,id',
            'label'           => 'nullable|string|max:255',
            'layout_id'       => 'nullable|uuid',
            'column_index'    => 'nullable|integer|min:0',
            'insert_position' => 'nullable|integer|min:0',
        ]);

        $widgetType = WidgetType::findOrFail($validated['widget_type_id']);

        // Validate layout belongs to this page
        $layoutId = $validated['layout_id'] ?? null;
        if ($layoutId) {
            abort_unless(
                PageLayout::where('id', $layoutId)->where('page_id', $page->id)->exists(),
                422,
                'Layout does not belong to this page.'
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

        if ($layoutId) {
            $columnIndex = $validated['column_index'] ?? 0;
            if ($position === null) {
                $position = (PageWidget::where('layout_id', $layoutId)
                    ->where('column_index', $columnIndex)
                    ->max('sort_order') ?? -1) + 1;
            } else {
                PageWidget::where('layout_id', $layoutId)
                    ->where('column_index', $columnIndex)
                    ->where('sort_order', '>=', $position)
                    ->increment('sort_order');
            }
        } else {
            if ($position === null) {
                $position = (PageWidget::where('page_id', $page->id)
                    ->whereNull('layout_id')
                    ->max('sort_order') ?? -1) + 1;
            } else {
                PageWidget::where('page_id', $page->id)
                    ->whereNull('layout_id')
                    ->where('sort_order', '>=', $position)
                    ->increment('sort_order');
            }
        }

        $newWidget = PageWidget::create([
            'page_id'           => $page->id,
            'widget_type_id'    => $widgetType->id,
            'layout_id'         => $layoutId,
            'column_index'      => $layoutId ? ($validated['column_index'] ?? 0) : null,
            'label'             => $label,
            'config'            => $widgetType->getDefaultConfig(),
            'query_config'      => [],
            'appearance_config' => [
                'background' => ['color' => '#ffffff'],
                'text'       => ['color' => '#000000'],
            ],
            'sort_order'        => $position,
            'is_active'         => true,
        ]);

        $tree = $this->buildTree($page);

        return response()->json([
            'widget'        => $this->formatWidget($newWidget->fresh(['widgetType']), $page),
            'tree'          => $tree['widgets'],
            'items'         => $tree['items'],
            'required_libs' => $tree['required_libs'],
        ], 201);
    }

    public function update(Request $request, PageWidget $widget): JsonResponse
    {
        abort_unless(auth()->user()?->can('update_page'), 403);

        $validated = $request->validate([
            'label'             => 'nullable|string|max:255',
            'config'            => 'nullable|array',
            'appearance_config' => 'nullable|array',
            'query_config'      => 'nullable|array',
        ]);

        $updates = [];
        if (array_key_exists('label', $validated)) {
            $updates['label'] = $validated['label'];
        }
        if (array_key_exists('config', $validated)) {
            $updates['config'] = $validated['config'];
        }
        if (array_key_exists('appearance_config', $validated)) {
            $updates['appearance_config'] = $validated['appearance_config'];
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

        $widget->delete();

        $tree = $this->buildTree($page);

        return response()->json([
            'deleted'       => true,
            'tree'          => $tree['widgets'],
            'items'         => $tree['items'],
            'required_libs' => $tree['required_libs'],
        ]);
    }

    public function copy(PageWidget $widget): JsonResponse
    {
        abort_unless(auth()->user()?->can('update_page'), 403);

        $page = $widget->page;

        // Insert copy after the source at the same nesting level
        $siblingQuery = PageWidget::where('page_id', $page->id);
        if ($widget->layout_id) {
            $siblingQuery->where('layout_id', $widget->layout_id)
                ->where('column_index', $widget->column_index);
        } else {
            $siblingQuery->whereNull('layout_id');
        }

        $newPosition = $widget->sort_order + 1;

        $siblingQuery->clone()->where('sort_order', '>=', $newPosition)->increment('sort_order');

        $copy = PageWidget::create([
            'page_id'           => $page->id,
            'widget_type_id'    => $widget->widget_type_id,
            'layout_id'         => $widget->layout_id,
            'column_index'      => $widget->column_index,
            'label'             => $widget->label,
            'config'            => $widget->config ?? [],
            'query_config'      => $widget->query_config ?? [],
            'appearance_config' => $widget->appearance_config ?? [],
            'sort_order'        => $newPosition,
            'is_active'         => $widget->is_active,
        ]);

        $tree = $this->buildTree($page);

        return response()->json([
            'widget'        => $this->formatWidget($copy->fresh(['widgetType']), $page),
            'tree'          => $tree['widgets'],
            'items'         => $tree['items'],
            'required_libs' => $tree['required_libs'],
        ], 201);
    }

    public function reorder(Request $request, Page $page): JsonResponse
    {
        abort_unless(auth()->user()?->can('update_page'), 403);

        $validated = $request->validate([
            'items'                => 'required|array|min:1',
            'items.*.id'           => 'required|uuid',
            'items.*.type'         => 'nullable|string|in:widget,layout',
            'items.*.layout_id'    => 'nullable|uuid',
            'items.*.column_index' => 'nullable|integer|min:0',
            'items.*.sort_order'   => 'required|integer|min:0',
        ]);

        // Default type to 'widget' for backward compatibility with the Phase 2 Vue store
        $items = array_map(function ($item) {
            $item['type'] = $item['type'] ?? 'widget';
            return $item;
        }, $validated['items']);

        $widgetIds = collect($items)->where('type', 'widget')->pluck('id')->all();
        $layoutIds = collect($items)->where('type', 'layout')->pluck('id')->all();

        // Validate widget ownership
        if (! empty($widgetIds)) {
            $validCount = PageWidget::where('page_id', $page->id)
                ->whereIn('id', $widgetIds)
                ->count();

            if ($validCount !== count(array_unique($widgetIds))) {
                return response()->json(['error' => 'Invalid widget IDs.'], 422);
            }
        }

        // Validate layout ownership
        if (! empty($layoutIds)) {
            $validCount = PageLayout::where('page_id', $page->id)
                ->whereIn('id', $layoutIds)
                ->count();

            if ($validCount !== count(array_unique($layoutIds))) {
                return response()->json(['error' => 'Invalid layout IDs.'], 422);
            }
        }

        // Validate referenced layout_id values belong to this page
        $referencedLayoutIds = collect($items)
            ->where('type', 'widget')
            ->pluck('layout_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! empty($referencedLayoutIds)) {
            $validCount = PageLayout::where('page_id', $page->id)
                ->whereIn('id', $referencedLayoutIds)
                ->count();

            if ($validCount !== count($referencedLayoutIds)) {
                return response()->json(['error' => 'Invalid referenced layout IDs.'], 422);
            }
        }

        foreach ($items as $item) {
            if ($item['type'] === 'widget') {
                PageWidget::where('id', $item['id'])
                    ->where('page_id', $page->id)
                    ->update([
                        'layout_id'    => ! empty($item['layout_id']) ? $item['layout_id'] : null,
                        'column_index' => $item['column_index'] ?? null,
                        'sort_order'   => (int) $item['sort_order'],
                    ]);
            } else {
                PageLayout::where('id', $item['id'])
                    ->where('page_id', $page->id)
                    ->update([
                        'sort_order' => (int) $item['sort_order'],
                    ]);
            }
        }

        $tree = $this->buildTree($page);

        return response()->json([
            'tree'          => $tree['widgets'],
            'items'         => $tree['items'],
            'required_libs' => $tree['required_libs'],
        ]);
    }

    // ── Preview ──────────────────────────────────────────────────────────────

    public function preview(PageWidget $widget): JsonResponse
    {
        abort_unless(auth()->user()?->can('view_page'), 403);

        $widget->load('widgetType');

        $previewData = $this->renderWidgetForPreview($widget);

        return response()->json([
            'id'            => $widget->id,
            'html'          => $previewData['html'],
            'required_libs' => [],
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

    // ── Layout CRUD ─────────────────────────────────────────────────────────

    public function storeLayout(Request $request, Page $page): JsonResponse
    {
        abort_unless(auth()->user()?->can('update_page'), 403);

        $validated = $request->validate([
            'label'   => 'nullable|string|max:255',
            'display' => 'nullable|string|in:flex,grid',
            'columns' => 'nullable|integer|min:1|max:12',
        ]);

        $maxSort = max(
            PageWidget::where('page_id', $page->id)->whereNull('layout_id')->max('sort_order') ?? -1,
            PageLayout::where('page_id', $page->id)->max('sort_order') ?? -1,
        );

        $columns = $validated['columns'] ?? 2;
        $display = $validated['display'] ?? 'grid';

        $layout = PageLayout::create([
            'page_id'       => $page->id,
            'label'         => $validated['label'] ?? 'Column Layout',
            'display'       => $display,
            'columns'       => $columns,
            'layout_config' => [
                'grid_template_columns' => implode(' ', array_fill(0, $columns, '1fr')),
                'gap'                   => '1.5rem',
            ],
            'sort_order'    => $maxSort + 1,
        ]);

        $tree = $this->buildTree($page);

        return response()->json([
            'layout'        => $this->formatLayout($layout),
            'items'         => $tree['items'],
            'required_libs' => $tree['required_libs'],
        ], 201);
    }

    public function updateLayout(Request $request, PageLayout $layout): JsonResponse
    {
        abort_unless(auth()->user()?->can('update_page'), 403);

        $validated = $request->validate([
            'label'         => 'nullable|string|max:255',
            'display'       => 'nullable|string|in:flex,grid',
            'columns'       => 'nullable|integer|min:1|max:12',
            'layout_config' => 'nullable|array',
        ]);

        $updates = [];
        if (array_key_exists('label', $validated)) {
            $updates['label'] = $validated['label'];
        }
        if (array_key_exists('display', $validated)) {
            $updates['display'] = $validated['display'];
        }
        if (array_key_exists('columns', $validated)) {
            $updates['columns'] = $validated['columns'];
        }
        if (array_key_exists('layout_config', $validated)) {
            // Sanitize: only allow known CSS property keys
            $allowed = [
                'grid_template_columns', 'gap', 'align_items', 'justify_items',
                'justify_content', 'grid_auto_rows', 'flex_wrap', 'flex_basis',
                'full_width', 'background_color',
                'padding_top', 'padding_right', 'padding_bottom', 'padding_left',
                'margin_top', 'margin_right', 'margin_bottom', 'margin_left',
            ];
            $sanitized = array_intersect_key(
                $validated['layout_config'],
                array_flip($allowed)
            );
            // Merge with existing layout_config so partial updates don't wipe other keys
            $updates['layout_config'] = array_merge($layout->layout_config ?? [], $sanitized);
        }

        if (! empty($updates)) {
            $layout->update($updates);
        }

        return response()->json([
            'layout' => $this->formatLayout($layout->fresh()),
        ]);
    }

    public function destroyLayout(PageLayout $layout): JsonResponse
    {
        abort_unless(auth()->user()?->can('update_page'), 403);

        $page = $layout->page;

        // Cascade delete is handled by FK, but we delete explicitly for clarity
        $layout->delete();

        $tree = $this->buildTree($page);

        return response()->json([
            'deleted'       => true,
            'items'         => $tree['items'],
            'required_libs' => $tree['required_libs'],
        ]);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function buildTree(Page $page): array
    {
        $requiredHandles = WidgetType::requiredForPage(
            $this->computeBareSlug($page)
        );

        // Root widgets (not in any layout)
        $rootWidgets = PageWidget::where('page_id', $page->id)
            ->whereNull('layout_id')
            ->where('is_active', true)
            ->with('widgetType')
            ->orderBy('sort_order')
            ->get();

        // Layouts with their child widgets
        $layouts = PageLayout::where('page_id', $page->id)
            ->with(['widgets' => fn ($q) => $q->where('is_active', true)->with('widgetType')->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        $allLibs = [];
        $items = [];

        foreach ($rootWidgets as $pw) {
            if (! $pw->widgetType) {
                continue;
            }

            $item = $this->formatWidgetWithPreview($pw, $requiredHandles);
            $item['type'] = 'widget';
            $items[] = $item;
            $this->collectLibs($pw, $allLibs);
        }

        foreach ($layouts as $layout) {
            $item = $this->formatLayout($layout);
            // Add preview HTML for each child widget in slots
            $slots = [];
            foreach ($layout->widgets as $child) {
                if (! $child->widgetType) {
                    continue;
                }
                $idx = $child->column_index ?? 0;
                $childData = $this->formatWidgetWithPreview($child, $requiredHandles);
                $childData['type'] = 'widget';
                $slots[$idx][] = $childData;
                $this->collectLibs($child, $allLibs);
            }
            $item['slots'] = (object) $slots;
            $items[] = $item;
        }

        // Sort by sort_order
        usort($items, fn ($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

        // Legacy 'widgets' key: root widgets only, kept for current Vue store compatibility
        // until Phase 3 migrates the store to use 'items'.
        $legacyWidgets = array_values(array_filter($items, fn ($i) => ($i['type'] ?? '') === 'widget'));

        return [
            'widgets'       => $legacyWidgets,
            'items'         => $items,
            'required_libs' => array_values(array_unique($allLibs)),
        ];
    }

    private function formatLayout(PageLayout $layout): array
    {
        return [
            'type'          => 'layout',
            'id'            => $layout->id,
            'page_id'       => $layout->page_id,
            'label'         => $layout->label ?? '',
            'display'       => $layout->display,
            'columns'       => $layout->columns,
            'layout_config' => $layout->layout_config ?? [],
            'sort_order'    => $layout->sort_order ?? 0,
            'slots'         => (object) [],
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
            'layout_id'                 => $pw->layout_id,
            'column_index'              => $pw->column_index,
            'label'                     => $pw->label ?? '',
            'config'                    => $pw->config ?? [],
            'query_config'              => $pw->query_config ?? [],
            'appearance_config'         => $pw->appearance_config ?? [],
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
            'layout_id'                 => $pw->layout_id,
            'column_index'              => $pw->column_index,
            'label'                     => $pw->label ?? '',
            'config'                    => $pw->config ?? [],
            'query_config'              => $pw->query_config ?? [],
            'appearance_config'         => $pw->appearance_config ?? [],
            'sort_order'                => $pw->sort_order ?? 0,
            'is_active'                 => $pw->is_active,
            'is_required'               => in_array($pw->widgetType?->handle ?? '', $requiredHandles, true),
            'image_urls'                => $this->resolveImageUrls($pw),
            'preview_html'              => $this->renderWidgetForPreview($pw)['html'],
        ];

        return $data;
    }

    private function renderWidgetForPreview(PageWidget $pw): array
    {
        $widgetType = $pw->widgetType;

        try {
            $fallbackData = $this->buildDemoCollectionData($pw);
            $result = WidgetRenderer::render($pw, [], $fallbackData);

            if ($result['html'] === null) {
                $html = '<div class="widget-preview-notice">No preview available</div>';
            } else {
                $handle = $widgetType->handle;
                $ac = $pw->appearance_config ?? [];
                $inlineStyle = self::buildInlineStyles($ac);

                $configFullWidth = $pw->config['full_width'] ?? null;
                $appearanceFullWidth = $ac['layout']['full_width'] ?? null;
                $isFullWidth = $configFullWidth !== null ? (bool) $configFullWidth
                    : ($appearanceFullWidth !== null ? (bool) $appearanceFullWidth : ($widgetType->full_width ?? false));

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

    private static function buildInlineStyles(array $appearanceConfig): string
    {
        $styleProps = [];

        $bgColor = $appearanceConfig['background']['color'] ?? null;
        if (! empty($bgColor) && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $bgColor)) {
            $styleProps[] = 'background-color:' . $bgColor;
        }
        $textColor = $appearanceConfig['text']['color'] ?? null;
        if (! empty($textColor) && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $textColor)) {
            $styleProps[] = 'color:' . $textColor;
        }

        $padding = $appearanceConfig['layout']['padding'] ?? [];
        $margin  = $appearanceConfig['layout']['margin'] ?? [];
        $sides = ['top', 'right', 'bottom', 'left'];

        foreach ($sides as $side) {
            $val = isset($padding[$side]) && $padding[$side] !== '' ? (int) $padding[$side] : null;
            if ($val !== null) {
                $styleProps[] = 'padding-' . $side . ':' . $val . 'px';
            }
        }
        foreach ($sides as $side) {
            $val = isset($margin[$side]) && $margin[$side] !== '' ? (int) $margin[$side] : null;
            if ($val !== null) {
                $styleProps[] = 'margin-' . $side . ':' . $val . 'px';
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
