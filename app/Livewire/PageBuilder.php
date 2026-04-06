<?php

namespace App\Livewire;

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\SiteSetting;
use App\Models\Template;
use App\Models\WidgetType;
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

    /** Page builder mode: 'edit' (block list + focused widget) or 'preview' (full-page iframe). */
    public string $mode = 'preview';

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

        $this->loadBlocks();
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
            $this->dispatch('block-selected', blockId: '');
        }

        $this->loadBlocks();
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

        PageWidget::create([
            'page_id'        => $this->pageId,
            'widget_type_id' => $source->widget_type_id,
            'label'          => $source->label,
            'config'         => $source->config ?? [],
            'query_config'   => $source->query_config ?? [],
            'sort_order'     => $newPosition,
            'is_active'      => $source->is_active,
        ]);

        $this->loadBlocks();
    }

    // -------------------------------------------------------------------------
    // Reorder — drag-to-drop via @alpinejs/sort
    // -------------------------------------------------------------------------

    public function updateOrder(array $orderedIds): void
    {
        $this->assertCanEdit();

        foreach ($orderedIds as $i => $id) {
            PageWidget::where('id', $id)->update(['sort_order' => $i]);
        }

        $this->loadBlocks();
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

        // Deselecting in edit mode returns to preview mode.
        if ($blockId === '' && $this->mode === 'edit') {
            $this->mode = 'preview';
        }
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
    // Edit / Preview mode toggle
    // -------------------------------------------------------------------------

    public function switchToPreview(): void
    {
        $this->selectedBlockId = '';
        $this->dispatch('block-selected', blockId: '', parentBlockId: '');
        $this->mode = 'preview';
    }

    public function switchToEdit(string $blockId = ''): void
    {
        $this->mode = 'edit';

        if ($blockId !== '') {
            $this->selectBlock($blockId);
        }
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.page-builder', ['selectedBlockId' => $this->selectedBlockId]);
    }
}
