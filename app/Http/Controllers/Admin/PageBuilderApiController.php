<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderWidgetsRequest;
use App\Http\Resources\WidgetPreviewResource;
use App\Http\Resources\WidgetResource;
use App\Models\Collection;
use App\Models\Event;
use App\Models\Page;
use App\Models\PageLayout;
use App\Models\PageWidget;
use App\Models\SiteSetting;
use App\Models\Tag;
use App\Models\Template;
use App\Models\WidgetType;
use App\Services\PageBuilderDataSources;
use App\Services\WidgetConfigResolver;
use App\Services\WidgetPreviewRenderer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageBuilderApiController extends Controller
{
    // ── Widget CRUD ──────────────────────────────────────────────────────────

    public function index($owner): JsonResponse
    {
        abort_unless(auth()->user()?->can('view_page'), 403);

        return response()->json($this->buildTree($owner));
    }

    public function store(Request $request, $owner): JsonResponse
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

        $layoutId = $validated['layout_id'] ?? null;
        if ($layoutId) {
            abort_unless(
                PageLayout::where('id', $layoutId)->forOwner($owner)->exists(),
                422,
                'Layout does not belong to this owner.'
            );
        }

        $label = $validated['label'] ?? '';
        if (blank($label)) {
            $count = PageWidget::forOwner($owner)
                ->where('widget_type_id', $widgetType->id)
                ->count();
            $label = $widgetType->label . ' ' . ($count + 1);
        }

        $position = $validated['insert_position'] ?? null;
        $columnIndex = $layoutId ? ($validated['column_index'] ?? 0) : null;

        if ($position === null) {
            $position = (PageWidget::inSlot($owner, $layoutId, $columnIndex)->max('sort_order') ?? -1) + 1;
        } else {
            PageWidget::inSlot($owner, $layoutId, $columnIndex)
                ->where('sort_order', '>=', $position)
                ->increment('sort_order');
        }

        $newWidget = PageWidget::create([
            'owner_type'        => $owner->getMorphClass(),
            'owner_id'          => $owner->getKey(),
            'widget_type_id'    => $widgetType->id,
            'layout_id'         => $layoutId,
            'column_index'      => $columnIndex,
            'label'             => $label,
            'config'            => [],
            'query_config'      => [],
            'appearance_config' => [
                'background' => ['color' => '#ffffff'],
                'text'       => ['color' => '#000000'],
            ],
            'sort_order'        => $position,
            'is_active'         => true,
        ]);

        $tree = $this->buildTree($owner);

        return response()->json([
            'widget'        => $this->formatWidget($newWidget->fresh(['widgetType']), $owner),
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
            $filtered = $this->filterConfigToSchema($widget, $validated['config'] ?? []);
            $updates['config'] = $this->stripDefaults($widget, $filtered);
        }
        if (array_key_exists('appearance_config', $validated)) {
            $updates['appearance_config'] = $this->filterAppearanceConfig($validated['appearance_config'] ?? []);
        }
        if (array_key_exists('query_config', $validated)) {
            $updates['query_config'] = $this->filterQueryConfig($widget, $validated['query_config'] ?? []);
        }

        if (! empty($updates)) {
            $widget->update($updates);
        }

        return response()->json([
            'widget' => $this->formatWidget($widget->fresh(['widgetType']), $widget->owner),
        ]);
    }

    public function destroy(PageWidget $widget): JsonResponse
    {
        abort_unless(auth()->user()?->can('update_page'), 403);

        $owner = $widget->owner;

        // Required-widgets rule only applies to Pages.
        if ($owner instanceof Page) {
            $requiredHandles = WidgetType::requiredForPage($owner->bareSlug());
            if (in_array($widget->widgetType?->handle ?? '', $requiredHandles, true)) {
                return response()->json(['error' => 'This widget is required and cannot be deleted.'], 403);
            }
        }

        $widget->delete();

        $tree = $this->buildTree($owner);

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

        $owner = $widget->owner;

        $newPosition = $widget->sort_order + 1;

        PageWidget::inSlot($owner, $widget->layout_id, $widget->column_index)
            ->where('sort_order', '>=', $newPosition)
            ->increment('sort_order');

        $copy = PageWidget::create([
            'owner_type'        => $owner->getMorphClass(),
            'owner_id'          => $owner->getKey(),
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

        $tree = $this->buildTree($owner);

        return response()->json([
            'widget'        => $this->formatWidget($copy->fresh(['widgetType']), $owner),
            'tree'          => $tree['widgets'],
            'items'         => $tree['items'],
            'required_libs' => $tree['required_libs'],
        ], 201);
    }

    public function reorder(ReorderWidgetsRequest $request, $owner): JsonResponse
    {
        $items = $request->normalizedItems();

        foreach ($items as $item) {
            if ($item['type'] === 'widget') {
                PageWidget::where('id', $item['id'])
                    ->forOwner($owner)
                    ->update([
                        'layout_id'    => ! empty($item['layout_id']) ? $item['layout_id'] : null,
                        'column_index' => $item['column_index'] ?? null,
                        'sort_order'   => (int) $item['sort_order'],
                    ]);
            } else {
                PageLayout::where('id', $item['id'])
                    ->forOwner($owner)
                    ->update([
                        'sort_order' => (int) $item['sort_order'],
                    ]);
            }
        }

        $tree = $this->buildTree($owner);

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

        return response()->json([
            'id'            => $widget->id,
            'html'          => app(WidgetPreviewRenderer::class)->render($widget),
            'required_libs' => [],
        ]);
    }

    // ── Lookups ──────────────────────────────────────────────────────────────

    public function widgetTypes(Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->can('view_page'), 403);

        $pageType = $request->query('page_type', 'default');

        return response()->json(['widget_types' => WidgetType::forPicker($pageType)]);
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

        $config = $widget->config ?? [];
        $config[$key] = $media->id;
        $widget->update(['config' => $this->stripDefaults($widget, $config)]);

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
        $widget->update(['config' => $this->stripDefaults($widget, $config)]);

        return response()->json(['removed' => true]);
    }

    // ── Appearance background image ────────────────────────────────────────

    public function uploadAppearanceImage(Request $request, PageWidget $widget): JsonResponse
    {
        abort_unless(auth()->user()?->can('update_page'), 403);

        $request->validate([
            'file' => 'required|file|mimes:png,jpg,jpeg,gif,webp,svg|max:51200',
        ]);

        $widget->clearMediaCollection('appearance_background_image');

        $media = $widget->addMedia($request->file('file'))
            ->usingFileName($request->file('file')->hashName())
            ->toMediaCollection('appearance_background_image', 'public');

        return response()->json([
            'url' => $media->hasGeneratedConversion('webp') ? $media->getUrl('webp') : $media->getUrl(),
        ]);
    }

    public function removeAppearanceImage(PageWidget $widget): JsonResponse
    {
        abort_unless(auth()->user()?->can('update_page'), 403);

        $widget->clearMediaCollection('appearance_background_image');

        return response()->json(['removed' => true]);
    }

    // ── Layout CRUD ─────────────────────────────────────────────────────────

    public function storeLayout(Request $request, $owner): JsonResponse
    {
        abort_unless(auth()->user()?->can('update_page'), 403);

        $validated = $request->validate([
            'label'   => 'nullable|string|max:255',
            'display' => 'nullable|string|in:flex,grid',
            'columns' => 'nullable|integer|min:1|max:12',
        ]);

        $maxSort = max(
            PageWidget::forOwner($owner)->whereNull('layout_id')->max('sort_order') ?? -1,
            PageLayout::forOwner($owner)->max('sort_order') ?? -1,
        );

        $columns = $validated['columns'] ?? 2;
        $display = $validated['display'] ?? 'grid';

        $layout = PageLayout::create([
            'owner_type'    => $owner->getMorphClass(),
            'owner_id'      => $owner->getKey(),
            'label'         => $validated['label'] ?? 'Column Layout',
            'display'       => $display,
            'columns'       => $columns,
            'layout_config' => [
                'grid_template_columns' => implode(' ', array_fill(0, $columns, '1fr')),
                'gap'                   => '1.5rem',
            ],
            'sort_order'    => $maxSort + 1,
        ]);

        $tree = $this->buildTree($owner);

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

        $owner = $layout->owner;

        $layout->delete();

        $tree = $this->buildTree($owner);

        return response()->json([
            'deleted'       => true,
            'items'         => $tree['items'],
            'required_libs' => $tree['required_libs'],
        ]);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function buildTree(Model $owner): array
    {
        $requiredHandles = $owner instanceof Page
            ? WidgetType::requiredForPage($owner->bareSlug())
            : [];

        $rootWidgets = PageWidget::forOwner($owner)
            ->whereNull('layout_id')
            ->where('is_active', true)
            ->with('widgetType')
            ->orderBy('sort_order')
            ->get();

        $layouts = PageLayout::forOwner($owner)
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
            app(WidgetPreviewRenderer::class)->collectLibs($pw, $allLibs);
        }

        foreach ($layouts as $layout) {
            $item = $this->formatLayout($layout);
            $slots = [];
            foreach ($layout->widgets as $child) {
                if (! $child->widgetType) {
                    continue;
                }
                $idx = $child->column_index ?? 0;
                $childData = $this->formatWidgetWithPreview($child, $requiredHandles);
                $childData['type'] = 'widget';
                $slots[$idx][] = $childData;
                app(WidgetPreviewRenderer::class)->collectLibs($child, $allLibs);
            }
            $item['slots'] = (object) $slots;
            $items[] = $item;
        }

        usort($items, fn ($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

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
            'owner_type'    => $layout->owner_type,
            'owner_id'      => $layout->owner_id,
            'label'         => $layout->label ?? '',
            'display'       => $layout->display,
            'columns'       => $layout->columns,
            'layout_config' => $layout->layout_config ?? [],
            'sort_order'    => $layout->sort_order ?? 0,
            'slots'         => (object) [],
        ];
    }

    private function formatWidget(PageWidget $pw, Model $owner): array
    {
        $requiredHandles = $owner instanceof Page
            ? WidgetType::requiredForPage($owner->bareSlug())
            : [];

        return (new WidgetResource($pw))
            ->withRequiredHandles($requiredHandles)
            ->resolve();
    }

    private function formatWidgetWithPreview(PageWidget $pw, array $requiredHandles): array
    {
        return (new WidgetPreviewResource($pw))
            ->withRequiredHandles($requiredHandles)
            ->resolve();
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

    private function stripDefaults(PageWidget $widget, array $config): array
    {
        $defaults = app(WidgetConfigResolver::class)->resolvedDefaults($widget);

        foreach ($config as $key => $value) {
            if (array_key_exists($key, $defaults) && $defaults[$key] === $value) {
                unset($config[$key]);
            }
        }

        return $config;
    }

    private function filterConfigToSchema(PageWidget $widget, array $config): array
    {
        $allowed = collect($widget->widgetType?->config_schema ?? [])
            ->pluck('key')
            ->filter()
            ->all();

        return array_intersect_key($config, array_flip($allowed));
    }

    private function filterAppearanceConfig(array $appearance): array
    {
        return array_intersect_key($appearance, array_flip(['background', 'text', 'layout']));
    }

    private function filterQueryConfig(PageWidget $widget, array $query): array
    {
        $slots = $widget->widgetType?->collections ?? [];

        if (empty($slots)) {
            return [];
        }

        return array_intersect_key($query, array_flip($slots));
    }
}
