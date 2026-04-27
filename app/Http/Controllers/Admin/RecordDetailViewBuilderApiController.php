<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\WidgetPreviewResource;
use App\Http\Resources\WidgetResource;
use App\Models\PageLayout;
use App\Models\PageWidget;
use App\Models\WidgetType;
use App\Services\WidgetConfigResolver;
use App\Services\WidgetPreviewRenderer;
use App\Services\WidgetRegistry;
use App\WidgetPrimitive\Slots\RecordDetailSidebarSlot;
use App\WidgetPrimitive\Views\RecordDetailView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecordDetailViewBuilderApiController extends Controller
{
    private const SLOT_HANDLE = 'record_detail_sidebar';

    private function assertCanManage(): void
    {
        abort_unless(auth()->user()?->can('manage_record_detail_views'), 403);
    }

    private function assertOwnsWidget(RecordDetailView $view, PageWidget $widget): void
    {
        abort_unless(
            $widget->owner_type === RecordDetailView::class
                && $widget->owner_id === $view->getKey(),
            404,
        );
    }

    private function assertOwnsLayout(RecordDetailView $view, PageLayout $layout): void
    {
        abort_unless(
            $layout->owner_type === RecordDetailView::class
                && $layout->owner_id === $view->getKey(),
            404,
        );
    }

    // ── Widget CRUD ──────────────────────────────────────────────────────────

    public function index(RecordDetailView $view): JsonResponse
    {
        $this->assertCanManage();

        return response()->json($this->buildTree($view));
    }

    public function store(Request $request, RecordDetailView $view): JsonResponse
    {
        $this->assertCanManage();

        $validated = $request->validate([
            'widget_type_id'  => 'required|uuid|exists:widget_types,id',
            'label'           => 'nullable|string|max:255',
            'layout_id'       => 'nullable|uuid',
            'column_index'    => 'nullable|integer|min:0',
            'insert_position' => 'nullable|integer|min:0',
        ]);

        $widgetType = WidgetType::findOrFail($validated['widget_type_id']);

        $def = app(WidgetRegistry::class)->find($widgetType->handle);
        if (! $def || ! in_array(self::SLOT_HANDLE, $def->allowedSlots(), true)) {
            return response()->json([
                'error' => "Widget [{$widgetType->handle}] is not allowed in the record detail sidebar slot.",
            ], 422);
        }

        $layoutId = $validated['layout_id'] ?? null;
        if ($layoutId) {
            abort_unless(
                PageLayout::where('id', $layoutId)
                    ->where('owner_type', RecordDetailView::class)
                    ->where('owner_id', $view->getKey())
                    ->exists(),
                422,
                'Layout does not belong to this view.'
            );
        }

        $label = $validated['label'] ?? '';
        if (blank($label)) {
            $label = $widgetType->label;
        }

        $position = $validated['insert_position'] ?? null;
        $columnIndex = $layoutId ? ($validated['column_index'] ?? 0) : null;

        if ($position === null) {
            $position = (PageWidget::inSlot($view, $layoutId, $columnIndex)->max('sort_order') ?? -1) + 1;
        } else {
            PageWidget::inSlot($view, $layoutId, $columnIndex)
                ->where('sort_order', '>=', $position)
                ->increment('sort_order');
        }

        $newWidget = PageWidget::create([
            'owner_type'        => $view->getMorphClass(),
            'owner_id'          => $view->getKey(),
            'widget_type_id'    => $widgetType->id,
            'layout_id'         => $layoutId,
            'column_index'      => $columnIndex,
            'label'             => $label,
            'config'            => [],
            'query_config'      => [],
            'appearance_config' => $def->defaultAppearanceConfig(),
            'sort_order'        => $position,
            'is_active'         => true,
        ]);

        $tree = $this->buildTree($view);

        return response()->json([
            'widget'        => $this->formatWidget($newWidget->fresh(['widgetType'])),
            'items'         => $tree['items'],
            'required_libs' => $tree['required_libs'],
        ], 201);
    }

    public function update(Request $request, RecordDetailView $view, PageWidget $widget): JsonResponse
    {
        $this->assertCanManage();
        $this->assertOwnsWidget($view, $widget);

        $validated = $request->validate([
            'label'                       => 'nullable|string|max:255',
            'config'                      => 'nullable|array',
            'appearance_config'           => 'nullable|array',
            'query_config'                => 'nullable|array',
            'query_config.limit'          => 'nullable|integer|min:1|max:200',
            'query_config.order_by'       => 'nullable|string|max:64',
            'query_config.direction'      => 'nullable|in:asc,desc',
            'query_config.include_tags'   => 'nullable|array',
            'query_config.include_tags.*' => 'string|max:255',
            'query_config.exclude_tags'   => 'nullable|array',
            'query_config.exclude_tags.*' => 'string|max:255',
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
            $updates['appearance_config'] = $this->filterAppearanceToSlot($validated['appearance_config'] ?? []);
        }
        if ($request->has('query_config')) {
            $raw = $request->input('query_config', []);
            $updates['query_config'] = $this->filterQueryConfig(is_array($raw) ? $raw : []);
        }

        if (! empty($updates)) {
            $widget->update($updates);
        }

        return response()->json([
            'widget' => $this->formatWidget($widget->fresh(['widgetType'])),
        ]);
    }

    public function destroy(RecordDetailView $view, PageWidget $widget): JsonResponse
    {
        $this->assertCanManage();
        $this->assertOwnsWidget($view, $widget);

        $widget->delete();

        $tree = $this->buildTree($view);

        return response()->json([
            'deleted'       => true,
            'items'         => $tree['items'],
            'required_libs' => $tree['required_libs'],
        ]);
    }

    public function reorder(Request $request, RecordDetailView $view): JsonResponse
    {
        $this->assertCanManage();

        $validated = $request->validate([
            'items'                  => 'required|array',
            'items.*.id'             => 'required|uuid',
            'items.*.type'           => 'required|in:widget,layout',
            'items.*.sort_order'     => 'required|integer|min:0',
            'items.*.layout_id'      => 'nullable|uuid',
            'items.*.column_index'   => 'nullable|integer|min:0',
        ]);

        foreach ($validated['items'] as $item) {
            if ($item['type'] === 'widget') {
                PageWidget::where('id', $item['id'])
                    ->where('owner_type', RecordDetailView::class)
                    ->where('owner_id', $view->getKey())
                    ->update([
                        'layout_id'    => ! empty($item['layout_id']) ? $item['layout_id'] : null,
                        'column_index' => $item['column_index'] ?? null,
                        'sort_order'   => (int) $item['sort_order'],
                    ]);
            } else {
                PageLayout::where('id', $item['id'])
                    ->where('owner_type', RecordDetailView::class)
                    ->where('owner_id', $view->getKey())
                    ->update([
                        'sort_order' => (int) $item['sort_order'],
                    ]);
            }
        }

        $tree = $this->buildTree($view);

        return response()->json([
            'items'         => $tree['items'],
            'required_libs' => $tree['required_libs'],
        ]);
    }

    // ── Layout CRUD ─────────────────────────────────────────────────────────

    public function storeLayout(Request $request, RecordDetailView $view): JsonResponse
    {
        $this->assertCanManage();

        $validated = $request->validate([
            'label'   => 'nullable|string|max:255',
            'display' => 'nullable|string|in:flex,grid',
            'columns' => 'nullable|integer|min:1|max:12',
        ]);

        $maxSort = max(
            PageWidget::where('owner_type', RecordDetailView::class)
                ->where('owner_id', $view->getKey())
                ->whereNull('layout_id')
                ->max('sort_order') ?? -1,
            PageLayout::where('owner_type', RecordDetailView::class)
                ->where('owner_id', $view->getKey())
                ->max('sort_order') ?? -1,
        );

        $columns = $validated['columns'] ?? 2;
        $display = $validated['display'] ?? 'grid';

        $layout = PageLayout::create([
            'owner_type'    => $view->getMorphClass(),
            'owner_id'      => $view->getKey(),
            'label'         => $validated['label'] ?? 'Column Layout',
            'display'       => $display,
            'columns'       => $columns,
            'layout_config' => [
                'grid_template_columns' => implode(' ', array_fill(0, $columns, '1fr')),
                'gap'                   => '1.5rem',
            ],
            'sort_order'    => $maxSort + 1,
        ]);

        $tree = $this->buildTree($view);

        return response()->json([
            'layout'        => $this->formatLayout($layout),
            'items'         => $tree['items'],
            'required_libs' => $tree['required_libs'],
        ], 201);
    }

    public function updateLayout(Request $request, RecordDetailView $view, PageLayout $layout): JsonResponse
    {
        $this->assertCanManage();
        $this->assertOwnsLayout($view, $layout);

        $validated = $request->validate([
            'label'             => 'nullable|string|max:255',
            'display'           => 'nullable|string|in:flex,grid',
            'columns'           => 'nullable|integer|min:1|max:12',
            'layout_config'     => 'nullable|array',
            'appearance_config' => 'nullable|array',
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
                'full_width',
            ];
            $sanitized = array_intersect_key(
                $validated['layout_config'],
                array_flip($allowed)
            );
            $updates['layout_config'] = array_merge($layout->layout_config ?? [], $sanitized);
        }
        if (array_key_exists('appearance_config', $validated)) {
            $updates['appearance_config'] = $this->filterLayoutAppearanceToSlot($validated['appearance_config'] ?? []);
        }

        if (! empty($updates)) {
            $layout->update($updates);
        }

        return response()->json([
            'layout' => $this->formatLayout($layout->fresh()),
        ]);
    }

    public function destroyLayout(RecordDetailView $view, PageLayout $layout): JsonResponse
    {
        $this->assertCanManage();
        $this->assertOwnsLayout($view, $layout);

        $layout->delete();

        $tree = $this->buildTree($view);

        return response()->json([
            'deleted'       => true,
            'items'         => $tree['items'],
            'required_libs' => $tree['required_libs'],
        ]);
    }

    // ── Preview ──────────────────────────────────────────────────────────────

    public function preview(RecordDetailView $view, PageWidget $widget): JsonResponse
    {
        $this->assertCanManage();
        $this->assertOwnsWidget($view, $widget);

        $widget->load('widgetType');

        return response()->json([
            'id'            => $widget->id,
            'html'          => app(WidgetPreviewRenderer::class)->render($widget, self::SLOT_HANDLE),
            'required_libs' => [],
        ]);
    }

    // ── Lookups ──────────────────────────────────────────────────────────────

    public function widgetTypes(RecordDetailView $view): JsonResponse
    {
        $this->assertCanManage();

        return response()->json([
            'widget_types' => WidgetType::forPicker(null, self::SLOT_HANDLE),
        ]);
    }

    // ── Appearance background image ────────────────────────────────────────

    public function uploadAppearanceImage(Request $request, RecordDetailView $view, PageWidget $widget): JsonResponse
    {
        $this->assertCanManage();
        $this->assertOwnsWidget($view, $widget);

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

    public function removeAppearanceImage(RecordDetailView $view, PageWidget $widget): JsonResponse
    {
        $this->assertCanManage();
        $this->assertOwnsWidget($view, $widget);

        $widget->clearMediaCollection('appearance_background_image');

        return response()->json(['removed' => true]);
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    private function buildTree(RecordDetailView $view): array
    {
        $rootWidgets = PageWidget::where('owner_type', RecordDetailView::class)
            ->where('owner_id', $view->getKey())
            ->whereNull('layout_id')
            ->where('is_active', true)
            ->with(['widgetType', 'owner'])
            ->orderBy('sort_order')
            ->get();

        $layouts = PageLayout::where('owner_type', RecordDetailView::class)
            ->where('owner_id', $view->getKey())
            ->with(['widgets' => fn ($q) => $q->where('is_active', true)->with(['widgetType', 'owner'])->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        $allLibs = [];
        $items = [];

        foreach ($rootWidgets as $pw) {
            if (! $pw->widgetType) {
                continue;
            }

            $item = $this->formatWidgetWithPreview($pw);
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
                $childData = $this->formatWidgetWithPreview($child);
                $childData['type'] = 'widget';
                $slots[$idx][] = $childData;
                app(WidgetPreviewRenderer::class)->collectLibs($child, $allLibs);
            }
            $item['slots'] = (object) $slots;
            $items[] = $item;
        }

        usort($items, fn ($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

        return [
            'widgets'       => array_values(array_filter($items, fn ($i) => ($i['type'] ?? '') === 'widget')),
            'items'         => $items,
            'required_libs' => array_values(array_unique($allLibs)),
        ];
    }

    private function formatLayout(PageLayout $layout): array
    {
        return [
            'type'              => 'layout',
            'id'                => $layout->id,
            'owner_type'        => $layout->owner_type,
            'owner_id'          => $layout->owner_id,
            'label'             => $layout->label ?? '',
            'display'           => $layout->display,
            'columns'           => $layout->columns,
            'layout_config'     => $layout->layout_config ?? [],
            'appearance_config' => (object) ($layout->appearance_config ?? []),
            'sort_order'        => $layout->sort_order ?? 0,
            'slots'             => (object) [],
        ];
    }

    private function formatWidget(PageWidget $pw): array
    {
        return (new WidgetResource($pw))->resolve();
    }

    private function formatWidgetWithPreview(PageWidget $pw): array
    {
        return (new WidgetPreviewResource($pw))
            ->withSlotHandle(self::SLOT_HANDLE)
            ->resolve();
    }

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

    private function filterAppearanceToSlot(array $appearance): array
    {
        $allowed = (new RecordDetailSidebarSlot())->layoutConstraints()['allowed_appearance_fields'] ?? [];

        return array_intersect_key($appearance, array_flip($allowed));
    }

    private function filterLayoutAppearanceToSlot(array $appearance): array
    {
        $allowed = (new RecordDetailSidebarSlot())->layoutConstraints()['allowed_appearance_fields'] ?? [];

        return array_intersect_key($appearance, array_flip($allowed));
    }

    private function filterQueryConfig(array $query): array
    {
        return array_intersect_key($query, array_flip([
            'limit', 'order_by', 'direction', 'include_tags', 'exclude_tags',
        ]));
    }
}
