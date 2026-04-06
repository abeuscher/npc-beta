<?php

namespace App\Livewire;

use App\Models\Collection;
use App\Models\PageWidget;
use App\Models\WidgetType;
use App\Services\DemoDataService;
use App\Services\WidgetRenderer;
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

    /** Whether this block is currently selected/focused for live preview. */
    public bool $isSelected = false;

    /** Cached rendered widget HTML for the live preview. */
    public string $previewHtml = '';

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
            $this->widgetTypes = WidgetType::orderBy('label')
                ->with('media')
                ->get()
                ->filter(fn ($wt) => $wt->allowed_page_types === null || in_array($this->pageType, $wt->allowed_page_types, true))
                ->map(fn ($wt) => [
                    'id'              => $wt->id,
                    'handle'          => $wt->handle,
                    'label'           => $wt->label,
                    'description'     => $wt->description,
                    'category'        => $wt->category ?? ['content'],
                    'collections'     => $wt->collections,
                    'config_schema'   => $wt->config_schema,
                    'thumbnail'       => $wt->getFirstMediaUrl('thumbnail', 'picker') ?: null,
                    'thumbnail_hover' => $wt->getFirstMediaUrl('thumbnail_hover', 'picker') ?: null,
                ])
                ->values()
                ->toArray();
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
    // React to selection and inspector config changes
    // -------------------------------------------------------------------------

    #[On('block-selected')]
    public function onBlockSelected(string $blockId, string $parentBlockId = ''): void
    {
        $wasSelected = $this->isSelected;
        $this->isSelected = ($blockId === $this->blockId);

        if ($this->isSelected && ! $wasSelected) {
            $this->refreshPreviewHtml();
        } elseif (! $this->isSelected) {
            $this->previewHtml = '';
        }
    }

    #[On('widget-config-updated')]
    public function onWidgetConfigUpdated(string $blockId): void
    {
        if ($blockId !== $this->blockId) {
            return;
        }

        $this->loadBlock();

        if ($this->isSelected) {
            $this->refreshPreviewHtml();
        }
    }

    private function refreshPreviewHtml(): void
    {
        try {
            $this->previewHtml = $this->getRenderedWidgetHtml() ?? '';
        } catch (\Throwable $e) {
            $this->previewHtml = '<div style="padding: 1rem; color: #dc2626; font-size: 0.875rem;">Preview error: ' . e($e->getMessage()) . '</div>';
        }
    }

    // -------------------------------------------------------------------------
    // Live widget preview rendering
    // -------------------------------------------------------------------------

    /**
     * Render the widget HTML for the live preview in edit mode.
     *
     * Returns the rendered HTML string, or null if the widget can't be rendered.
     */
    public function getRenderedWidgetHtml(): ?string
    {
        $pw = PageWidget::with(['widgetType', 'children.widgetType', 'children.children.widgetType'])->find($this->blockId);

        if (! $pw || ! $pw->widgetType) {
            return null;
        }

        // For column widgets, render children recursively
        $columnChildren = [];
        if ($pw->widgetType->handle === 'column_widget') {
            $columnChildren = $this->renderColumnChildren($pw);
        }

        // Generate demo collection data as fallback for unbound widgets
        $fallbackData = $this->buildDemoCollectionData($pw);

        $result = WidgetRenderer::render($pw, $columnChildren, $fallbackData);

        if ($result['html'] === null) {
            return null;
        }

        // Wrap in the same structure as the public site
        $handle = $pw->widgetType->handle;
        $sc = $pw->style_config ?? [];
        $styleProps = [];
        $spacingKeys = [
            'padding_top' => 'padding-top', 'padding_right' => 'padding-right',
            'padding_bottom' => 'padding-bottom', 'padding_left' => 'padding-left',
            'margin_top' => 'margin-top', 'margin_right' => 'margin-right',
            'margin_bottom' => 'margin-bottom', 'margin_left' => 'margin-left',
        ];
        foreach ($spacingKeys as $key => $cssProp) {
            $val = isset($sc[$key]) && $sc[$key] !== '' ? (int) $sc[$key] : null;
            if ($val !== null) {
                $styleProps[] = $cssProp . ':' . $val . 'px';
            }
        }
        $inlineStyle = implode(';', $styleProps);

        $configFullWidth = $pw->config['full_width'] ?? null;
        $isFullWidth = $configFullWidth !== null ? (bool) $configFullWidth : ($pw->widgetType->full_width ?? false);

        $innerHtml = $isFullWidth
            ? $result['html']
            : '<div class="site-container">' . $result['html'] . '</div>';

        $styles = $result['styles'] ? '<style>' . $result['styles'] . '</style>' : '';

        return $styles
            . '<div class="widget widget--' . e($handle) . '"'
            . ' id="widget-' . e($pw->id) . '"'
            . ($inlineStyle ? ' style="' . e($inlineStyle) . '"' : '')
            . '>' . $innerHtml . '</div>';
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

            $childHandle = $child->widgetType->handle;
            $sc = $child->style_config ?? [];
            $styleProps = [];
            $spacingKeys = [
                'padding_top' => 'padding-top', 'padding_right' => 'padding-right',
                'padding_bottom' => 'padding-bottom', 'padding_left' => 'padding-left',
                'margin_top' => 'margin-top', 'margin_right' => 'margin-right',
                'margin_bottom' => 'margin-bottom', 'margin_left' => 'margin-left',
            ];
            foreach ($spacingKeys as $key => $cssProp) {
                $val = isset($sc[$key]) && $sc[$key] !== '' ? (int) $sc[$key] : null;
                if ($val !== null) {
                    $styleProps[] = $cssProp . ':' . $val . 'px';
                }
            }
            $childInlineStyle = implode(';', $styleProps);

            $configFullWidth = $child->config['full_width'] ?? null;
            $isFullWidth = $configFullWidth !== null ? (bool) $configFullWidth : ($child->widgetType->full_width ?? false);

            $idx = $child->column_index ?? 0;
            $children[$idx][] = [
                'handle'       => $childHandle,
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

    /**
     * Build demo collection data keyed by slot name, for widgets with
     * collection slots that may have no real data.
     *
     * @return array<string, array>
     */
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

    public function render(): \Illuminate\View\View
    {
        return view('livewire.page-builder-block');
    }
}
