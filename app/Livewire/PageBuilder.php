<?php

namespace App\Livewire;

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\SiteSetting;
use App\Models\Template;
use App\Models\WidgetType;
use App\Services\DemoDataService;
use App\Services\WidgetRenderer;
use App\Models\Collection;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;
use Livewire\Component;

class PageBuilder extends Component
{
    public string $pageId = '';

    private function assertCanEdit(): void
    {
        abort_unless(auth()->user()?->can('update_page'), 403);
    }

    /** @var array<int, array<string, mixed>> */
    public array $blocks = [];

    /** @var array<int, array<string, mixed>> */
    public array $widgetTypes = [];

    /** @var array<int, string> Widget handles that are required on this page. */
    protected array $requiredHandles = [];

    public string $pageType = 'default';

    public string $selectedBlockId = '';

    /** Page builder mode: 'edit' (unified preview) or 'handles' (block card list with drag handles). */
    public string $mode = 'edit';

    /** @var array<int, array<string, mixed>> Rendered widget HTML for the unified preview pane. */
    public array $previewBlocks = [];

    /** @var string[] Union of all library identifiers required by widgets on this page. */
    public array $requiredLibs = [];

    // Add block modal
    public bool $showAddModal = false;
    public ?int $insertPosition = null;
    public string $addModalLabel = '';

    // Save as template modal
    public bool $showSaveTemplateModal = false;
    public string $saveTemplateName = '';
    public string $saveTemplateDescription = '';

    public function mount(string $pageId = ''): void
    {
        $this->pageId = $pageId;

        if ($pageId) {
            $page = Page::find($pageId);
            if ($page) {
                $this->pageType = $page->type;
                $this->requiredHandles = WidgetType::requiredForPage(
                    $this->computeBareSlug($page)
                );
            }
        }

        $this->widgetTypes = WidgetType::forPicker($this->pageType);

        $this->loadBlocks();
        $this->refreshAllPreviews();
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

    public function loadBlocks(): void
    {
        $this->blocks = PageWidget::where('page_id', $this->pageId)
            ->whereNull('parent_widget_id')
            ->with('widgetType')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($pw) => [
                'id'                        => $pw->id,
                'widget_type_id'            => $pw->widget_type_id,
                'widget_type_handle'        => $pw->widgetType?->handle ?? '',
                'widget_type_label'         => $pw->widgetType?->label ?? 'Unknown',
                'widget_type_collections'   => $pw->widgetType?->collections ?? [],
                'widget_type_config_schema' => $pw->widgetType?->config_schema ?? [],
                'label'                     => $pw->label ?? '',
                'config'                    => $pw->config ?? [],
                'query_config'              => $pw->query_config ?? [],
                'style_config'              => $pw->style_config ?? [],
                'sort_order'                => $pw->sort_order ?? 0,
                'is_required'               => in_array($pw->widgetType?->handle ?? '', $this->requiredHandles, true),
            ])
            ->values()
            ->toArray();
    }

    // -------------------------------------------------------------------------
    // Add block
    // -------------------------------------------------------------------------

    public function openAddModal(?int $position = null): void
    {
        $this->insertPosition = $position;
        $this->addModalLabel  = '';
        $this->showAddModal   = true;
    }

    public function createBlock(string $widgetTypeId): void
    {
        $this->assertCanEdit();

        $this->validate([
            'addModalLabel' => 'nullable|string|max:255',
        ]);

        $widgetType = WidgetType::find($widgetTypeId);

        if (! $widgetType) {
            return;
        }

        // Auto-generate label if not provided: "Widget Type Label N"
        if (blank($this->addModalLabel)) {
            $count = PageWidget::where('page_id', $this->pageId)
                ->whereHas('widgetType', fn ($q) => $q->where('id', $widgetTypeId))
                ->count();
            $this->addModalLabel = $widgetType->label . ' ' . ($count + 1);
        }

        $defaultConfig = $widgetType->getDefaultConfig();

        $position = $this->insertPosition ?? count($this->blocks);

        // Shift sort_order for blocks at or after the insert position
        PageWidget::where('page_id', $this->pageId)
            ->where('sort_order', '>=', $position)
            ->increment('sort_order');

        $newBlock = PageWidget::create([
            'page_id'        => $this->pageId,
            'widget_type_id' => $widgetType->id,
            'label'          => $this->addModalLabel,
            'config'         => $defaultConfig,
            'query_config'   => [],
            'sort_order'     => $position,
            'is_active'      => true,
        ]);

        $this->showAddModal   = false;
        $this->insertPosition = null;
        $this->addModalLabel  = '';
        $this->loadBlocks();
        $this->refreshAllPreviews();
        $this->selectBlock($newBlock->id);
    }

    // -------------------------------------------------------------------------
    // Delete — Alpine handles the confirm UI; Livewire executes the delete
    // -------------------------------------------------------------------------

    public function deleteBlock(string $blockId): void
    {
        $this->assertCanEdit();

        $pw = PageWidget::with('widgetType')->find($blockId);

        if ($pw && in_array($pw->widgetType?->handle ?? '', $this->requiredHandles, true)) {
            return;
        }

        PageWidget::where('id', $blockId)->delete();

        if ($this->selectedBlockId === $blockId) {
            $this->selectedBlockId = '';
            $this->dispatch('block-selected', blockId: '', parentBlockId: '');
        }

        $this->loadBlocks();
        $this->refreshAllPreviews();
    }

    // -------------------------------------------------------------------------
    // Copy block
    // -------------------------------------------------------------------------

    public function copyBlock(int $index): void
    {
        $this->assertCanEdit();

        $block = $this->blocks[$index] ?? null;

        if (! $block) {
            return;
        }

        $source = PageWidget::find($block['id']);

        if (! $source) {
            return;
        }

        $newPosition = $index + 1;

        PageWidget::where('page_id', $this->pageId)
            ->where('sort_order', '>=', $newPosition)
            ->increment('sort_order');

        $copy = PageWidget::create([
            'page_id'        => $this->pageId,
            'widget_type_id' => $source->widget_type_id,
            'label'          => $source->label,
            'config'         => $source->config ?? [],
            'query_config'   => $source->query_config ?? [],
            'style_config'   => $source->style_config ?? [],
            'sort_order'     => $newPosition,
            'is_active'      => $source->is_active,
        ]);

        // Recursively copy children (for column widgets)
        if ($source->children()->exists()) {
            PageWidget::copyBetweenPages(
                $this->pageId,
                $this->pageId,
                $source->id,
                $copy->id,
            );
        }

        $this->loadBlocks();
        $this->refreshAllPreviews();
    }

    // -------------------------------------------------------------------------
    // Reorder — drag-to-drop via @alpinejs/sort with connected groups
    // -------------------------------------------------------------------------

    /**
     * Accept a full placement payload from the drag-and-drop UI.
     *
     * Each item: { id, parent_id, column_index, sort_order }
     */
    public function updateOrder(array $items): void
    {
        $this->assertCanEdit();

        if (empty($items)) {
            return;
        }

        $itemIds = collect($items)->pluck('id')->all();

        // All widget IDs must belong to this page
        $validIds = PageWidget::where('page_id', $this->pageId)
            ->whereIn('id', $itemIds)
            ->pluck('id')
            ->all();

        if (count($validIds) !== count(array_unique($itemIds))) {
            return;
        }

        // Prevent column widget nesting — column widgets cannot be children
        $columnWidgetIds = PageWidget::where('page_id', $this->pageId)
            ->whereIn('id', $itemIds)
            ->whereHas('widgetType', fn ($q) => $q->where('handle', 'column_widget'))
            ->pluck('id')
            ->all();

        foreach ($items as $item) {
            if (in_array($item['id'], $columnWidgetIds) && ! empty($item['parent_id'])) {
                return;
            }
        }

        // Persist all placements
        foreach ($items as $item) {
            PageWidget::where('id', $item['id'])
                ->where('page_id', $this->pageId)
                ->update([
                    'parent_widget_id' => ! empty($item['parent_id']) ? $item['parent_id'] : null,
                    'column_index'     => $item['column_index'] ?? null,
                    'sort_order'       => (int) ($item['sort_order'] ?? 0),
                ]);
        }

        $this->loadBlocks();
        $this->refreshAllPreviews();
        $this->dispatch('blocks-reordered');
    }

    // -------------------------------------------------------------------------
    // Up / Down fallback
    // -------------------------------------------------------------------------

    public function moveUp(int $index): void
    {
        $this->assertCanEdit();

        if ($index === 0) {
            return;
        }

        [$this->blocks[$index - 1], $this->blocks[$index]] =
            [$this->blocks[$index], $this->blocks[$index - 1]];

        $this->persistOrder();
    }

    public function moveDown(int $index): void
    {
        $this->assertCanEdit();

        if ($index >= count($this->blocks) - 1) {
            return;
        }

        [$this->blocks[$index], $this->blocks[$index + 1]] =
            [$this->blocks[$index + 1], $this->blocks[$index]];

        $this->persistOrder();
    }

    private function persistOrder(): void
    {
        foreach ($this->blocks as $i => $block) {
            PageWidget::where('id', $block['id'])->update(['sort_order' => $i]);
            $this->blocks[$i]['sort_order'] = $i;
        }

        $this->refreshAllPreviews();
    }

    // -------------------------------------------------------------------------
    // Child component event listeners
    // -------------------------------------------------------------------------

    #[On('block-delete-requested')]
    public function onDeleteBlock(string $blockId): void
    {
        $this->deleteBlock($blockId);
    }

    #[On('block-copy-requested')]
    public function onCopyBlock(string $blockId): void
    {
        $index = collect($this->blocks)->search(fn ($b) => $b['id'] === $blockId);
        if ($index !== false) {
            $this->copyBlock($index);
        }
    }

    #[On('block-move-up-requested')]
    public function onMoveUp(string $blockId): void
    {
        $index = collect($this->blocks)->search(fn ($b) => $b['id'] === $blockId);
        if ($index !== false) {
            $this->moveUp($index);
        }
    }

    #[On('block-move-down-requested')]
    public function onMoveDown(string $blockId): void
    {
        $index = collect($this->blocks)->search(fn ($b) => $b['id'] === $blockId);
        if ($index !== false) {
            $this->moveDown($index);
        }
    }

    #[On('block-move-to-main-requested')]
    public function onMoveToMain(string $blockId): void
    {
        $this->assertCanEdit();

        $widget = PageWidget::where('id', $blockId)
            ->where('page_id', $this->pageId)
            ->whereNotNull('parent_widget_id')
            ->first();

        if (! $widget) {
            return;
        }

        $maxSort = PageWidget::where('page_id', $this->pageId)
            ->whereNull('parent_widget_id')
            ->max('sort_order') ?? -1;

        $widget->update([
            'parent_widget_id' => null,
            'column_index'     => null,
            'sort_order'       => $maxSort + 1,
        ]);

        $this->loadBlocks();
        $this->refreshAllPreviews();
        $this->dispatch('blocks-reordered');
    }

    #[On('block-move-to-column-requested')]
    public function onMoveToColumn(string $blockId, string $columnWidgetId, int $columnIndex): void
    {
        $this->assertCanEdit();

        $widget = PageWidget::where('id', $blockId)
            ->where('page_id', $this->pageId)
            ->whereNull('parent_widget_id')
            ->first();

        if (! $widget) {
            return;
        }

        // Prevent nesting column widgets
        $isColumn = $widget->widgetType?->handle === 'column_widget';
        if ($isColumn) {
            return;
        }

        // Verify the target column widget exists on this page
        $columnWidget = PageWidget::where('id', $columnWidgetId)
            ->where('page_id', $this->pageId)
            ->whereNull('parent_widget_id')
            ->first();

        if (! $columnWidget) {
            return;
        }

        $maxSort = PageWidget::where('parent_widget_id', $columnWidgetId)
            ->where('column_index', $columnIndex)
            ->max('sort_order') ?? -1;

        $widget->update([
            'parent_widget_id' => $columnWidgetId,
            'column_index'     => $columnIndex,
            'sort_order'       => $maxSort + 1,
        ]);

        $this->loadBlocks();
        $this->refreshAllPreviews();
        $this->dispatch('blocks-reordered');
    }

    #[On('block-add-modal-requested')]
    public function onAddModalRequested(string $blockId, bool $below = false): void
    {
        $index = collect($this->blocks)->search(fn ($b) => $b['id'] === $blockId);
        if ($index !== false) {
            $this->openAddModal($below ? $index + 1 : $index);
        }
    }

    // -------------------------------------------------------------------------
    // Block selection — drives the inspector panel
    // -------------------------------------------------------------------------

    #[On('block-select-requested')]
    public function onSelectBlock(string $blockId): void
    {
        $this->selectBlock($blockId);
    }

    public function selectBlock(string $blockId): void
    {
        // Validate the block belongs to this page before selecting it.
        if ($blockId !== '' && ! \App\Models\PageWidget::where('id', $blockId)->where('page_id', $this->pageId)->exists()) {
            return;
        }

        $this->selectedBlockId = $blockId;

        // Look up the parent widget ID so column blocks can exempt themselves from blur
        // when one of their children is focused.
        $parentId = '';
        if ($blockId !== '') {
            $parentId = \App\Models\PageWidget::where('id', $blockId)->value('parent_widget_id') ?? '';
        }

        // Dispatch a browser event so each block component can update its selected highlight.
        $this->dispatch('block-selected', blockId: $blockId, parentBlockId: $parentId);
    }

    // -------------------------------------------------------------------------
    // Save as Content Template
    // -------------------------------------------------------------------------

    public function openSaveTemplateModal(): void
    {
        $this->saveTemplateName = '';
        $this->saveTemplateDescription = '';
        $this->showSaveTemplateModal = true;
    }

    public function saveAsTemplate(): void
    {
        $this->assertCanEdit();

        $this->validate([
            'saveTemplateName' => 'required|string|max:255',
            'saveTemplateDescription' => 'nullable|string|max:1000',
        ]);

        $definition = PageWidget::serializeStack($this->pageId);

        $name = $this->saveTemplateName;

        Template::create([
            'name'        => $name,
            'type'        => 'content',
            'description' => $this->saveTemplateDescription ?: null,
            'definition'  => $definition,
            'is_default'  => false,
            'created_by'  => auth()->id(),
        ]);

        $this->showSaveTemplateModal = false;
        $this->saveTemplateName = '';
        $this->saveTemplateDescription = '';

        Notification::make()
            ->title("Template saved: {$name}")
            ->success()
            ->send();
    }

    // -------------------------------------------------------------------------
    // Edit / Handles mode toggle
    // -------------------------------------------------------------------------

    public function switchToHandles(): void
    {
        $this->mode = 'handles';
    }

    public function switchToEdit(): void
    {
        $this->mode = 'edit';
    }

    // -------------------------------------------------------------------------
    // Unified preview rendering
    // -------------------------------------------------------------------------

    public function refreshAllPreviews(): void
    {
        $this->previewBlocks = [];

        $widgets = PageWidget::where('page_id', $this->pageId)
            ->whereNull('parent_widget_id')
            ->where('is_active', true)
            ->with(['widgetType', 'children.widgetType', 'children.children.widgetType'])
            ->orderBy('sort_order')
            ->get();

        $allLibs = [];

        foreach ($widgets as $pw) {
            if (! $pw->widgetType) {
                continue;
            }

            $this->previewBlocks[] = $this->renderWidgetForPreview($pw);
            $this->collectLibs($pw, $allLibs);
        }

        $this->requiredLibs = array_values(array_unique($allLibs));

        // Push the new preview HTML to the browser since the preview container
        // uses wire:ignore and won't be morphed by Livewire.
        $this->dispatch('preview-content-changed', blocks: $this->previewBlocks);
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
                $html = '<div style="padding: 1rem; color: #9ca3af; font-size: 0.875rem; text-align: center;">No preview available</div>';
            } else {
                $handle = $widgetType->handle;
                $sc = $pw->style_config ?? [];
                $inlineStyle = self::buildInlineStyles($sc);

                $configFullWidth = $pw->config['full_width'] ?? null;
                $isFullWidth = $configFullWidth !== null ? (bool) $configFullWidth : ($widgetType->full_width ?? false);

                $innerHtml = $isFullWidth
                    ? $result['html']
                    : '<div class="site-container">' . $result['html'] . '</div>';

                $styles = $result['styles'] ? '<style>' . $result['styles'] . '</style>' : '';

                // Strip <script> tags — preview uses x-ignore + Alpine initTree,
                // and raw scripts in the Livewire render can corrupt the output.
                $innerHtml = preg_replace('#<script\b[^>]*>.*?</script>#si', '', $innerHtml);

                $html = $styles
                    . '<div class="widget widget--' . e($handle) . '"'
                    . ' id="widget-' . e($pw->id) . '"'
                    . ($inlineStyle ? ' style="' . e($inlineStyle) . '"' : '')
                    . '>' . $innerHtml . '</div>';
            }
        } catch (\Throwable $e) {
            $html = '<div style="padding: 1rem; color: #dc2626; font-size: 0.875rem;">Preview error: ' . e($e->getMessage()) . '</div>';
        }

        return [
            'id'               => $pw->id,
            'handle'           => $widgetType->handle,
            'label'            => $pw->label ?? '',
            'widget_type_label' => $widgetType->label,
            'html'             => $html,
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

            $childHandle = $child->widgetType->handle;
            $sc = $child->style_config ?? [];
            $childInlineStyle = self::buildInlineStyles($sc);

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

    private static function buildInlineStyles(array $styleConfig): string
    {
        $styleProps = [];
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

    #[On('preview-refresh-requested')]
    public function onPreviewRefreshRequested(string $blockId = ''): void
    {
        $this->refreshAllPreviews();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.page-builder', ['selectedBlockId' => $this->selectedBlockId]);
    }
}
