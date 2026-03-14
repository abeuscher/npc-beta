<?php

namespace App\Livewire;

use App\Models\CmsTag;
use App\Models\PageWidget;
use App\Models\WidgetType;
use Livewire\Component;

class PageBuilder extends Component
{
    public string $pageId = '';

    /** @var array<int, array<string, mixed>> */
    public array $blocks = [];

    /** @var array<int, array<string, mixed>> */
    public array $widgetTypes = [];

    /** @var array<int, array<string, mixed>> */
    public array $cmsTags = [];

    // Add block modal
    public bool $showAddModal = false;
    public string $selectedWidgetTypeId = '';

    // Configure modal (widget blocks)
    public bool $showConfigModal = false;
    public ?int $configModalBlockIndex = null;
    public string $configModalLabel = '';

    /** @var array<string, mixed> */
    public array $configModalQueryConfig = [];

    // Delete confirmation
    public ?int $confirmDeleteIndex = null;

    public function mount(string $pageId = ''): void
    {
        $this->pageId = $pageId;

        $this->widgetTypes = WidgetType::orderBy('label')
            ->get(['id', 'handle', 'label', 'collections'])
            ->toArray();

        $this->cmsTags = CmsTag::orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->toArray();

        $this->loadBlocks();
    }

    public function loadBlocks(): void
    {
        $this->blocks = PageWidget::where('page_id', $this->pageId)
            ->with('widgetType')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($pw) => [
                'id'                      => $pw->id,
                'widget_type_id'          => $pw->widget_type_id,
                'widget_type_handle'      => $pw->widgetType?->handle ?? '',
                'widget_type_label'       => $pw->widgetType?->label ?? 'Unknown',
                'widget_type_collections' => $pw->widgetType?->collections ?? [],
                'label'                   => $pw->label ?? '',
                'query_config'            => $pw->query_config ?? [],
                'sort_order'              => $pw->sort_order ?? 0,
            ])
            ->values()
            ->toArray();
    }

    // -------------------------------------------------------------------------
    // Add block
    // -------------------------------------------------------------------------

    public function openAddModal(): void
    {
        $this->showAddModal = true;
        $this->selectedWidgetTypeId = '';
    }

    public function createBlock(): void
    {
        $this->validate(['selectedWidgetTypeId' => 'required|string']);

        $widgetType = WidgetType::find($this->selectedWidgetTypeId);

        if (! $widgetType) {
            return;
        }

        $defaultConfig = $widgetType->handle === 'text_block'
            ? ['content' => '']
            : [];

        PageWidget::create([
            'page_id'        => $this->pageId,
            'widget_type_id' => $widgetType->id,
            'label'          => $widgetType->label,
            'query_config'   => $defaultConfig,
            'sort_order'     => count($this->blocks),
            'is_active'      => true,
        ]);

        $this->showAddModal = false;
        $this->loadBlocks();

        // For widget blocks with collections, open config modal immediately.
        if ($widgetType->handle !== 'text_block' && ! empty($widgetType->collections)) {
            $this->openConfigModal(count($this->blocks) - 1);
        }
    }

    // -------------------------------------------------------------------------
    // Configure modal
    // -------------------------------------------------------------------------

    public function openConfigModal(int $index): void
    {
        $block = $this->blocks[$index] ?? null;

        if (! $block) {
            return;
        }

        $this->configModalBlockIndex = $index;
        $this->configModalLabel      = $block['label'] ?? '';
        $this->configModalQueryConfig = $block['query_config'] ?? [];
        $this->showConfigModal = true;
    }

    public function saveConfigModal(): void
    {
        $block = $this->blocks[$this->configModalBlockIndex] ?? null;

        if (! $block) {
            $this->showConfigModal = false;
            return;
        }

        PageWidget::where('id', $block['id'])->update([
            'query_config' => $this->configModalQueryConfig,
            'label'        => $this->configModalLabel ?: $block['widget_type_label'],
        ]);

        $this->showConfigModal = false;
        $this->loadBlocks();
    }

    public function closeConfigModal(): void
    {
        $this->showConfigModal = false;
        $this->configModalBlockIndex = null;
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    public function confirmDelete(int $index): void
    {
        $this->confirmDeleteIndex = $index;
    }

    public function cancelDelete(): void
    {
        $this->confirmDeleteIndex = null;
    }

    public function deleteBlock(): void
    {
        $block = $this->blocks[$this->confirmDeleteIndex] ?? null;

        if (! $block) {
            $this->confirmDeleteIndex = null;
            return;
        }

        PageWidget::where('id', $block['id'])->delete();
        $this->confirmDeleteIndex = null;
        $this->loadBlocks();
    }

    // -------------------------------------------------------------------------
    // Reordering
    // -------------------------------------------------------------------------

    public function moveUp(int $index): void
    {
        if ($index === 0) {
            return;
        }

        [$this->blocks[$index - 1], $this->blocks[$index]] =
            [$this->blocks[$index], $this->blocks[$index - 1]];

        $this->persistOrder();
    }

    public function moveDown(int $index): void
    {
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
    // Text block inline editing
    // -------------------------------------------------------------------------

    public function updateTextContent(string $blockId, string $content): void
    {
        foreach ($this->blocks as $i => $block) {
            if ($block['id'] === $blockId) {
                $qc            = $block['query_config'];
                $qc['content'] = $content;

                $this->blocks[$i]['query_config'] = $qc;

                PageWidget::where('id', $blockId)->update(['query_config' => $qc]);

                break;
            }
        }
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.page-builder');
    }
}
