<?php

namespace App\Livewire;

use App\Http\Resources\WidgetPreviewResource;
use App\Models\PageLayout;
use App\Models\PageWidget;
use App\Models\SiteSetting;
use App\Models\WidgetType;
use App\Services\WidgetPreviewRenderer;
use App\WidgetPrimitive\Slots\RecordDetailSidebarSlot;
use App\WidgetPrimitive\Views\RecordDetailView;
use Livewire\Component;

class RecordDetailViewBuilder extends Component
{
    public string $viewId = '';

    public bool $showAddModal = false;
    public ?int $insertPosition = null;
    public ?string $addModalLayoutId = null;
    public ?int $addModalColumnIndex = null;
    public string $addModalLabel = '';

    /** @var array<int, array<string, mixed>> */
    public array $widgetTypes = [];

    public function mount(string $viewId): void
    {
        abort_unless(auth()->user()?->can('manage_record_detail_views'), 403);

        $this->viewId = $viewId;
        $this->widgetTypes = WidgetType::forPicker(null, 'record_detail_sidebar');
    }

    public function openAddModal(?int $position = null, ?string $layoutId = null, ?int $columnIndex = null): void
    {
        $this->insertPosition = $position;
        $this->addModalLayoutId = $layoutId;
        $this->addModalColumnIndex = $columnIndex;
        $this->addModalLabel = '';
        $this->showAddModal = true;
    }

    public function createBlock(string $widgetTypeId): void
    {
        abort_unless(auth()->user()?->can('manage_record_detail_views'), 403);

        $this->validate([
            'addModalLabel' => 'nullable|string|max:255',
        ]);

        $view = RecordDetailView::find($this->viewId);
        $widgetType = WidgetType::find($widgetTypeId);

        if (! $view || ! $widgetType) {
            return;
        }

        $def = app(\App\Services\WidgetRegistry::class)->find($widgetType->handle);
        if (! $def || ! in_array('record_detail_sidebar', $def->allowedSlots(), true)) {
            return;
        }

        $layoutId = $this->addModalLayoutId;
        if ($layoutId) {
            $layoutBelongs = PageLayout::where('id', $layoutId)
                ->where('owner_type', RecordDetailView::class)
                ->where('owner_id', $view->getKey())
                ->exists();
            if (! $layoutBelongs) {
                return;
            }
        }

        $columnIndex = $layoutId ? ($this->addModalColumnIndex ?? 0) : null;

        $label = blank($this->addModalLabel) ? $widgetType->label : $this->addModalLabel;
        $position = $this->insertPosition;

        if ($position === null) {
            $position = (PageWidget::inSlot($view, $layoutId, $columnIndex)->max('sort_order') ?? -1) + 1;
        } else {
            PageWidget::inSlot($view, $layoutId, $columnIndex)
                ->where('sort_order', '>=', $position)
                ->increment('sort_order');
        }

        $newBlock = PageWidget::create([
            'owner_type'        => $view->getMorphClass(),
            'owner_id'          => $view->getKey(),
            'layout_id'         => $layoutId,
            'column_index'      => $columnIndex,
            'widget_type_id'    => $widgetType->id,
            'label'             => $label,
            'config'            => [],
            'query_config'      => [],
            'appearance_config' => $def->defaultAppearanceConfig(),
            'sort_order'        => $position,
            'is_active'         => true,
        ]);

        $this->showAddModal = false;
        $this->insertPosition = null;
        $this->addModalLayoutId = null;
        $this->addModalColumnIndex = null;
        $this->addModalLabel = '';
        $this->js("window.dispatchEvent(new CustomEvent('widget-created', { detail: { widgetId: '" . $newBlock->id . "', ownerId: '" . $this->viewId . "', ownerType: 'record_detail_view' } }))");
    }

    public function getBootstrapData(): array
    {
        $view = RecordDetailView::find($this->viewId);
        if (! $view) {
            abort(404);
        }

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
        $previewRenderer = app(WidgetPreviewRenderer::class);

        foreach ($rootWidgets as $pw) {
            if (! $pw->widgetType) {
                continue;
            }

            $previewRenderer->collectLibs($pw, $allLibs);

            $items[] = ['type' => 'widget'] + (new WidgetPreviewResource($pw))
                ->withSlotHandle('record_detail_sidebar')
                ->resolve();
        }

        foreach ($layouts as $layout) {
            $slots = [];
            foreach ($layout->widgets as $child) {
                if (! $child->widgetType) {
                    continue;
                }
                $idx = $child->column_index ?? 0;
                $slots[$idx][] = ['type' => 'widget'] + (new WidgetPreviewResource($child))
                    ->withSlotHandle('record_detail_sidebar')
                    ->resolve();
                $previewRenderer->collectLibs($child, $allLibs);
            }

            $items[] = [
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
                'slots'             => (object) $slots,
            ];
        }

        usort($items, fn ($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

        $adminPath = config('filament.path', env('ADMIN_PATH', 'admin'));
        $apiBaseUrl = '/' . $adminPath . '/api/record-detail-view-builder/views/' . $this->viewId;

        $colorSwatches = json_decode(SiteSetting::get('editor_color_swatches', '[]'), true) ?: [];

        $constraints = (new RecordDetailSidebarSlot())->layoutConstraints();

        return [
            'mode'                      => 'record_detail',
            'owner_id'                  => $this->viewId,
            'owner_type'                => 'record_detail_view',
            'page_id'                   => '',
            'page_type'                 => '',
            'page_title'                => '',
            'page_author'               => '',
            'page_status'               => '',
            'page_url'                  => '',
            'page_tags'                 => [],
            'details_url'               => null,
            'widgets'                   => array_values(array_filter($items, fn ($i) => ($i['type'] ?? '') === 'widget')),
            'items'                     => $items,
            'required_libs'             => array_values(array_unique($allLibs)),
            'widget_types'              => $this->widgetTypes,
            'required_handles'          => [],
            'collections'               => [],
            'tags'                      => [],
            'pages'                     => [],
            'events'                    => [],
            'csrf_token'                => csrf_token(),
            'api_base_url'              => $apiBaseUrl,
            'api_lookup_url'            => $apiBaseUrl,
            'inline_image_upload_url'   => '',
            'color_swatches'            => $colorSwatches,
            'theme_palette'             => [],
            'theme_editor_url'          => '',
            'allowed_appearance_fields' => $constraints['allowed_appearance_fields'] ?? [],
            'allowed_widget_handles'    => array_map(fn ($wt) => $wt['handle'], $this->widgetTypes),
            'view_label'                => $view->label ?? '',
            'record_type_label'         => class_basename($view->record_type),
        ];
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.record-detail-view-builder', [
            'bootstrapData' => $this->getBootstrapData(),
        ]);
    }
}
