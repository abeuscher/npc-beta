<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\WidgetPreviewResource;
use App\Http\Resources\WidgetResource;
use App\Models\PageWidget;
use App\Models\WidgetType;
use App\Services\WidgetConfigResolver;
use App\Services\WidgetPreviewRenderer;
use App\Services\WidgetRegistry;
use App\WidgetPrimitive\Slots\DashboardGridSlot;
use App\WidgetPrimitive\Views\DashboardView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardBuilderApiController extends Controller
{
    private const SLOT_HANDLE = 'dashboard_grid';

    private function assertCanManage(): void
    {
        abort_unless(auth()->user()?->can('manage_dashboard_config'), 403);
    }

    private function assertOwnsWidget(DashboardView $config, PageWidget $widget): void
    {
        abort_unless(
            $widget->owner_type === DashboardView::class
                && $widget->owner_id === $config->getKey(),
            404,
        );
    }

    // ── Widget CRUD ──────────────────────────────────────────────────────────

    public function index(DashboardView $dashboardConfig): JsonResponse
    {
        $this->assertCanManage();

        return response()->json($this->buildTree($dashboardConfig));
    }

    public function store(Request $request, DashboardView $dashboardConfig): JsonResponse
    {
        $this->assertCanManage();

        $validated = $request->validate([
            'widget_type_id'  => 'required|uuid|exists:widget_types,id',
            'label'           => 'nullable|string|max:255',
            'insert_position' => 'nullable|integer|min:0',
        ]);

        $widgetType = WidgetType::findOrFail($validated['widget_type_id']);

        $def = app(WidgetRegistry::class)->find($widgetType->handle);
        if (! $def || ! in_array(self::SLOT_HANDLE, $def->allowedSlots(), true)) {
            return response()->json([
                'error' => "Widget [{$widgetType->handle}] is not allowed in the dashboard grid slot.",
            ], 422);
        }

        $label = $validated['label'] ?? '';
        if (blank($label)) {
            $label = $widgetType->label;
        }

        $position = $validated['insert_position'] ?? null;

        if ($position === null) {
            $position = ($dashboardConfig->pageWidgets()->max('sort_order') ?? -1) + 1;
        } else {
            $dashboardConfig->pageWidgets()
                ->where('sort_order', '>=', $position)
                ->increment('sort_order');
        }

        $newWidget = PageWidget::create([
            'owner_type'        => $dashboardConfig->getMorphClass(),
            'owner_id'          => $dashboardConfig->getKey(),
            'widget_type_id'    => $widgetType->id,
            'layout_id'         => null,
            'column_index'      => null,
            'label'             => $label,
            'config'            => [],
            'query_config'      => [],
            'appearance_config' => $def->defaultAppearanceConfig(),
            'sort_order'        => $position,
            'is_active'         => true,
        ]);

        $tree = $this->buildTree($dashboardConfig);

        return response()->json([
            'widget'        => $this->formatWidget($newWidget->fresh(['widgetType'])),
            'items'         => $tree['items'],
            'required_libs' => $tree['required_libs'],
        ], 201);
    }

    public function update(Request $request, DashboardView $dashboardConfig, PageWidget $widget): JsonResponse
    {
        $this->assertCanManage();
        $this->assertOwnsWidget($dashboardConfig, $widget);

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

    public function destroy(DashboardView $dashboardConfig, PageWidget $widget): JsonResponse
    {
        $this->assertCanManage();
        $this->assertOwnsWidget($dashboardConfig, $widget);

        $widget->delete();

        $tree = $this->buildTree($dashboardConfig);

        return response()->json([
            'deleted'       => true,
            'items'         => $tree['items'],
            'required_libs' => $tree['required_libs'],
        ]);
    }

    public function reorder(Request $request, DashboardView $dashboardConfig): JsonResponse
    {
        $this->assertCanManage();

        $validated = $request->validate([
            'items'             => 'required|array',
            'items.*.id'        => 'required|uuid',
            'items.*.sort_order'=> 'required|integer|min:0',
        ]);

        foreach ($validated['items'] as $item) {
            $dashboardConfig->pageWidgets()
                ->where('id', $item['id'])
                ->update(['sort_order' => (int) $item['sort_order']]);
        }

        $tree = $this->buildTree($dashboardConfig);

        return response()->json([
            'items'         => $tree['items'],
            'required_libs' => $tree['required_libs'],
        ]);
    }

    // ── Preview ──────────────────────────────────────────────────────────────

    public function preview(DashboardView $dashboardConfig, PageWidget $widget): JsonResponse
    {
        $this->assertCanManage();
        $this->assertOwnsWidget($dashboardConfig, $widget);

        $widget->load('widgetType');

        return response()->json([
            'id'            => $widget->id,
            'html'          => app(WidgetPreviewRenderer::class)->render($widget, self::SLOT_HANDLE),
            'required_libs' => [],
        ]);
    }

    // ── Lookups ──────────────────────────────────────────────────────────────

    public function widgetTypes(DashboardView $dashboardConfig): JsonResponse
    {
        $this->assertCanManage();

        return response()->json([
            'widget_types' => WidgetType::forPicker(null, self::SLOT_HANDLE),
        ]);
    }

    // ── Appearance background image ────────────────────────────────────────

    public function uploadAppearanceImage(Request $request, DashboardView $dashboardConfig, PageWidget $widget): JsonResponse
    {
        $this->assertCanManage();
        $this->assertOwnsWidget($dashboardConfig, $widget);

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

    public function removeAppearanceImage(DashboardView $dashboardConfig, PageWidget $widget): JsonResponse
    {
        $this->assertCanManage();
        $this->assertOwnsWidget($dashboardConfig, $widget);

        $widget->clearMediaCollection('appearance_background_image');

        return response()->json(['removed' => true]);
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    private function buildTree(DashboardView $dashboardConfig): array
    {
        $widgets = $dashboardConfig->pageWidgets()
            ->where('is_active', true)
            ->with(['widgetType', 'owner'])
            ->orderBy('sort_order')
            ->get();

        $allLibs = [];
        $items = [];

        foreach ($widgets as $pw) {
            if (! $pw->widgetType) {
                continue;
            }

            $item = $this->formatWidgetWithPreview($pw);
            $item['type'] = 'widget';
            $items[] = $item;
            app(WidgetPreviewRenderer::class)->collectLibs($pw, $allLibs);
        }

        return [
            'widgets'       => $items,
            'items'         => $items,
            'required_libs' => array_values(array_unique($allLibs)),
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
        $allowed = (new DashboardGridSlot())->layoutConstraints()['allowed_appearance_fields'] ?? [];

        return array_intersect_key($appearance, array_flip($allowed));
    }

    private function filterQueryConfig(array $query): array
    {
        return array_intersect_key($query, array_flip([
            'limit', 'order_by', 'direction', 'include_tags', 'exclude_tags',
        ]));
    }
}
