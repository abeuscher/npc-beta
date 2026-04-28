<?php

namespace App\Livewire;

use App\Http\Resources\WidgetPreviewResource;
use App\Models\PageWidget;
use App\Models\SiteSetting;
use App\Models\WidgetType;
use App\Services\WidgetPreviewRenderer;
use App\WidgetPrimitive\Slots\DashboardGridSlot;
use App\WidgetPrimitive\Views\DashboardView;
use Livewire\Component;

class DashboardBuilder extends Component
{
    public string $dashboardConfigId = '';

    public bool $showAddModal = false;
    public ?int $insertPosition = null;
    public string $addModalLabel = '';

    /** @var array<int, array<string, mixed>> */
    public array $widgetTypes = [];

    public function mount(string $dashboardConfigId): void
    {
        abort_unless(auth()->user()?->can('manage_dashboard_config'), 403);

        $this->dashboardConfigId = $dashboardConfigId;
        $this->widgetTypes = WidgetType::forPicker(null, 'dashboard_grid');
    }

    public function openAddModal(?int $position = null): void
    {
        $this->insertPosition = $position;
        $this->addModalLabel = '';
        $this->showAddModal = true;
    }

    public function createBlock(string $widgetTypeId): void
    {
        abort_unless(auth()->user()?->can('manage_dashboard_config'), 403);

        $this->validate([
            'addModalLabel' => 'nullable|string|max:255',
        ]);

        $config = DashboardView::find($this->dashboardConfigId);
        $widgetType = WidgetType::find($widgetTypeId);

        if (! $config || ! $widgetType) {
            return;
        }

        $def = app(\App\Services\WidgetRegistry::class)->find($widgetType->handle);
        if (! $def || ! in_array('dashboard_grid', $def->allowedSlots(), true)) {
            return;
        }

        $label = blank($this->addModalLabel) ? $widgetType->label : $this->addModalLabel;
        $position = $this->insertPosition;

        if ($position === null) {
            $position = ($config->pageWidgets()->max('sort_order') ?? -1) + 1;
        } else {
            $config->pageWidgets()
                ->where('sort_order', '>=', $position)
                ->increment('sort_order');
        }

        $newBlock = PageWidget::create([
            'owner_type'        => $config->getMorphClass(),
            'owner_id'          => $config->getKey(),
            'layout_id'         => null,
            'column_index'      => null,
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
        $this->addModalLabel = '';
        $this->js("window.dispatchEvent(new CustomEvent('widget-created', { detail: { widgetId: '" . $newBlock->id . "', ownerId: '" . $this->dashboardConfigId . "', ownerType: 'dashboard_config' } }))");
    }

    public function getBootstrapData(): array
    {
        $config = DashboardView::with('role')->find($this->dashboardConfigId);
        if (! $config) {
            abort(404);
        }

        $rootWidgets = $config->pageWidgets()
            ->where('is_active', true)
            ->with('widgetType')
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
                ->withSlotHandle('dashboard_grid')
                ->resolve();
        }

        $adminPath = config('filament.path', env('ADMIN_PATH', 'admin'));
        $apiBaseUrl = '/' . $adminPath . '/api/dashboard-builder/configs/' . $this->dashboardConfigId;

        $colorSwatches = json_decode(SiteSetting::get('editor_color_swatches', '[]'), true) ?: [];

        $constraints = (new DashboardGridSlot())->layoutConstraints();

        return [
            'mode'                      => 'dashboard',
            'owner_id'                  => $this->dashboardConfigId,
            'owner_type'                => 'dashboard_config',
            'page_id'                   => '',
            'page_type'                 => '',
            'page_title'                => '',
            'page_author'               => '',
            'page_status'               => '',
            'page_url'                  => '',
            'page_tags'                 => [],
            'details_url'               => null,
            'widgets'                   => $items,
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
            'role_label'                => $config->role?->label ?? $config->role?->name ?? '',
        ];
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.dashboard-builder', [
            'bootstrapData' => $this->getBootstrapData(),
        ]);
    }
}
