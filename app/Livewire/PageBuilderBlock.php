<?php

namespace App\Livewire;

use App\Models\PageWidget;
use App\Models\WidgetType;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class PageBuilderBlock extends Component
{
    public string $blockId;

    private function assertCanEdit(): void
    {
        abort_unless(auth()->user()?->can('update_page'), 403);
    }

    public string $pageType = 'default';

    /** Set when this block is a child inside a column slot. */
    public string $parentBlockId = '';
    public int $parentColumnIndex = 0;

    #[Reactive]
    public bool $isFirst = false;

    #[Reactive]
    public bool $isLast = false;

    #[Reactive]
    public bool $isRequired = false;

    /** @var array<string, mixed> */
    public array $block = [];

    public function mount(
        string $blockId,
        bool $isFirst = false,
        bool $isLast = false,
        bool $isRequired = false,
        string $parentBlockId = '',
        int $parentColumnIndex = 0,
        string $pageType = 'default',
    ): void {
        $this->blockId            = $blockId;
        $this->isFirst            = $isFirst;
        $this->isLast             = $isLast;
        $this->isRequired         = $isRequired;
        $this->parentBlockId      = $parentBlockId;
        $this->parentColumnIndex  = $parentColumnIndex;
        $this->pageType           = $pageType;

        $this->loadBlock();

        if ($this->block['widget_type_handle'] === 'column_widget') {
            $this->loadChildSlots();
        }
    }

    public function loadBlock(): void
    {
        $pw = PageWidget::with('widgetType')->find($this->blockId);

        if (! $pw) {
            return;
        }

        $this->block = [
            'id'                          => $pw->id,
            'widget_type_handle'          => $pw->widgetType?->handle ?? '',
            'widget_type_label'           => $pw->widgetType?->label ?? 'Unknown',
            'widget_type_collections'     => $pw->widgetType?->collections ?? [],
            'widget_type_config_schema'   => $pw->widgetType?->config_schema ?? [],
            'widget_type_assets'          => $pw->widgetType?->assets ?? [],
            'widget_type_default_open'    => $pw->widgetType?->default_open ?? false,
            'label'                       => $pw->label ?? '',
            'config'                      => $pw->config ?? [],
            'sort_order'                  => $pw->sort_order ?? 0,
        ];
    }

    // -------------------------------------------------------------------------
    // Column widget — child slot management
    // -------------------------------------------------------------------------

    /** @var array<int, array<int, array<string, mixed>>> columnIndex → ordered child block maps */
    public array $childSlots = [];

    /** @var array<int, array<string, mixed>> */
    public array $widgetTypes = [];

    public bool $showChildAddModal = false;
    public ?int $childAddColumn = null;
    public string $childAddLabel = '';

    public function loadChildSlots(): void
    {
        $this->childSlots = [];

        $children = PageWidget::where('parent_widget_id', $this->blockId)
            ->with('widgetType')
            ->orderBy('column_index')
            ->orderBy('sort_order')
            ->get();

        foreach ($children as $child) {
            $idx = $child->column_index ?? 0;
            $this->childSlots[$idx][] = [
                'id'                     => $child->id,
                'widget_type_handle'     => $child->widgetType?->handle ?? '',
                'widget_type_label'      => $child->widgetType?->label ?? 'Unknown',
                'widget_type_default_open' => $child->widgetType?->default_open ?? false,
                'label'                  => $child->label ?? '',
                'sort_order'             => $child->sort_order ?? 0,
            ];
        }
    }

    public function openChildAddModal(int $columnIndex): void
    {
        if (empty($this->widgetTypes)) {
            $this->widgetTypes = WidgetType::forPicker($this->pageType);
        }

        $this->childAddColumn = $columnIndex;
        $this->childAddLabel  = '';
        $this->showChildAddModal = true;
    }

    public function createChildBlock(string $widgetTypeId): void
    {
        $this->assertCanEdit();

        $this->validate(['childAddLabel' => 'nullable|string|max:255']);

        $widgetType = WidgetType::find($widgetTypeId);

        if (! $widgetType) {
            return;
        }

        $columnIndex = $this->childAddColumn ?? 0;

        // Validate parent belongs to same page (security check)
        $parentWidget = PageWidget::find($this->blockId);
        if (! $parentWidget) {
            return;
        }

        if (blank($this->childAddLabel)) {
            $count = PageWidget::where('parent_widget_id', $this->blockId)
                ->where('column_index', $columnIndex)
                ->whereHas('widgetType', fn ($q) => $q->where('id', $widgetTypeId))
                ->count();
            $this->childAddLabel = $widgetType->label . ' ' . ($count + 1);
        }

        $defaultConfig = $widgetType->getDefaultConfig();

        $sortOrder = PageWidget::where('parent_widget_id', $this->blockId)
            ->where('column_index', $columnIndex)
            ->max('sort_order') ?? -1;

        PageWidget::create([
            'page_id'          => $parentWidget->page_id,
            'parent_widget_id' => $this->blockId,
            'column_index'     => $columnIndex,
            'widget_type_id'   => $widgetType->id,
            'label'            => $this->childAddLabel,
            'config'           => $defaultConfig,
            'query_config'     => [],
            'style_config'     => [],
            'sort_order'       => $sortOrder + 1,
            'is_active'        => true,
        ]);

        $this->showChildAddModal = false;
        $this->childAddColumn    = null;
        $this->childAddLabel     = '';
        $this->loadChildSlots();
    }

    #[On('child-delete-requested')]
    public function onChildDelete(string $childId, string $parentId): void
    {
        $this->assertCanEdit();

        if ($parentId !== $this->blockId) {
            return;
        }

        PageWidget::where('id', $childId)->delete();
        $this->loadChildSlots();
    }

    #[On('child-move-up-requested')]
    public function onChildMoveUp(string $childId, string $parentId, int $columnIndex): void
    {
        $this->assertCanEdit();

        if ($parentId !== $this->blockId) {
            return;
        }

        $siblings = PageWidget::where('parent_widget_id', $this->blockId)
            ->where('column_index', $columnIndex)
            ->orderBy('sort_order')
            ->get();

        $idx = $siblings->search(fn ($s) => $s->id === $childId);

        if ($idx === false || $idx === 0) {
            return;
        }

        $current  = $siblings[$idx];
        $previous = $siblings[$idx - 1];

        [$current->sort_order, $previous->sort_order] = [$previous->sort_order, $current->sort_order];
        $current->save();
        $previous->save();

        $this->loadChildSlots();
    }

    #[On('child-move-down-requested')]
    public function onChildMoveDown(string $childId, string $parentId, int $columnIndex): void
    {
        $this->assertCanEdit();

        if ($parentId !== $this->blockId) {
            return;
        }

        $siblings = PageWidget::where('parent_widget_id', $this->blockId)
            ->where('column_index', $columnIndex)
            ->orderBy('sort_order')
            ->get();

        $idx = $siblings->search(fn ($s) => $s->id === $childId);

        if ($idx === false || $idx >= $siblings->count() - 1) {
            return;
        }

        $current = $siblings[$idx];
        $next    = $siblings[$idx + 1];

        [$current->sort_order, $next->sort_order] = [$next->sort_order, $current->sort_order];
        $current->save();
        $next->save();

        $this->loadChildSlots();
    }

    // -------------------------------------------------------------------------
    // Dispatch events to parent for list-level operations
    // -------------------------------------------------------------------------

    public function requestDelete(): void
    {
        if ($this->parentBlockId !== '') {
            $this->dispatch('child-delete-requested', childId: $this->blockId, parentId: $this->parentBlockId);
            return;
        }
        $this->dispatch('block-delete-requested', blockId: $this->blockId);
    }

    public function requestCopy(): void
    {
        $this->dispatch('block-copy-requested', blockId: $this->blockId);
    }

    public function requestMoveUp(): void
    {
        if ($this->parentBlockId !== '') {
            $this->dispatch('child-move-up-requested', childId: $this->blockId, parentId: $this->parentBlockId, columnIndex: $this->parentColumnIndex);
            return;
        }
        $this->dispatch('block-move-up-requested', blockId: $this->blockId);
    }

    public function requestMoveDown(): void
    {
        if ($this->parentBlockId !== '') {
            $this->dispatch('child-move-down-requested', childId: $this->blockId, parentId: $this->parentBlockId, columnIndex: $this->parentColumnIndex);
            return;
        }
        $this->dispatch('block-move-down-requested', blockId: $this->blockId);
    }

    public function selectSelf(): void
    {
        $this->dispatch('block-select-requested', blockId: $this->blockId);
    }

    public function requestAddAbove(): void
    {
        $this->dispatch('block-add-modal-requested', blockId: $this->blockId, below: false);
    }

    public function requestAddBelow(): void
    {
        $this->dispatch('block-add-modal-requested', blockId: $this->blockId, below: true);
    }

    // -------------------------------------------------------------------------
    // React to inspector config changes
    // -------------------------------------------------------------------------

    #[On('widget-config-updated')]
    public function onWidgetConfigUpdated(string $blockId): void
    {
        if ($blockId !== $this->blockId) {
            return;
        }

        $this->loadBlock();

        if ($this->block['widget_type_handle'] === 'column_widget') {
            $this->loadChildSlots();
        }
    }

    // -------------------------------------------------------------------------
    // Inline text editing — save config without re-rendering the preview
    // -------------------------------------------------------------------------

    /**
     * Persist an inline text edit from the contenteditable preview.
     *
     * Unlike the inspector's updateConfig, this does NOT re-render the
     * preview HTML — the user is editing live in the DOM.
     */
    public function updateInlineConfig(string $key, mixed $value): void
    {
        $this->assertCanEdit();

        $pw = PageWidget::where('id', $this->blockId)
            ->where('page_id', PageWidget::where('id', $this->blockId)->value('page_id'))
            ->first();

        if (! $pw) {
            return;
        }

        $config = $pw->config ?? [];
        $config[$key] = $value;
        $pw->update(['config' => $config]);

        $this->block['config'] = $config;

        // Notify the inspector to refresh its fields (but skip preview re-render).
        $this->dispatch('inline-config-updated', blockId: $this->blockId, key: $key, value: $value);
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.page-builder-block');
    }
}
