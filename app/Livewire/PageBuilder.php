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
    public ?int $insertPosition = null;
    public string $addModalLabel = '';

    public function mount(string $pageId = ''): void
    {
        $this->pageId = $pageId;

        $this->widgetTypes = WidgetType::orderBy('label')
            ->get(['id', 'handle', 'label', 'collections', 'config_schema'])
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
                'id'                        => $pw->id,
                'widget_type_id'            => $pw->widget_type_id,
                'widget_type_handle'        => $pw->widgetType?->handle ?? '',
                'widget_type_label'         => $pw->widgetType?->label ?? 'Unknown',
                'widget_type_collections'   => $pw->widgetType?->collections ?? [],
                'widget_type_config_schema' => $pw->widgetType?->config_schema ?? [],
                'label'                     => $pw->label ?? '',
                'config'                    => $pw->config ?? [],
                'query_config'              => $pw->query_config ?? [],
                'sort_order'                => $pw->sort_order ?? 0,
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
        $this->validate([
            'addModalLabel' => 'required|string|max:255',
        ], [
            'addModalLabel.required' => 'Please enter a label for this block.',
        ]);

        $widgetType = WidgetType::find($widgetTypeId);

        if (! $widgetType) {
            return;
        }

        // Build default config from config_schema
        $defaultConfig = [];
        foreach ($widgetType->config_schema ?? [] as $field) {
            $defaultConfig[$field['key']] = match ($field['type'] ?? 'text') {
                'toggle' => false,
                'number' => null,
                default  => '',
            };
        }

        $position = $this->insertPosition ?? count($this->blocks);

        // Shift sort_order for blocks at or after the insert position
        PageWidget::where('page_id', $this->pageId)
            ->where('sort_order', '>=', $position)
            ->increment('sort_order');

        PageWidget::create([
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
    }

    // -------------------------------------------------------------------------
    // Delete — Alpine handles the confirm UI; Livewire executes the delete
    // -------------------------------------------------------------------------

    public function deleteBlock(string $blockId): void
    {
        PageWidget::where('id', $blockId)->delete();
        $this->loadBlocks();
    }

    // -------------------------------------------------------------------------
    // Copy block
    // -------------------------------------------------------------------------

    public function copyBlock(int $index): void
    {
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
        foreach ($orderedIds as $i => $id) {
            PageWidget::where('id', $id)->update(['sort_order' => $i]);
        }

        $this->loadBlocks();
    }

    // -------------------------------------------------------------------------
    // Up / Down fallback
    // Used when @alpinejs/sort is blocked by CSP (eval() issue from Session 007).
    // TODO: Remove these once CSP is resolved and @alpinejs/sort is confirmed working.
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
    // Config auto-save
    // -------------------------------------------------------------------------

    /**
     * Explicitly save a singleton config value — used by richtext (Trix) fields
     * which cannot use wire:model due to wire:ignore.
     */
    public function updateConfig(string $blockId, string $key, mixed $value): void
    {
        foreach ($this->blocks as $i => $block) {
            if ($block['id'] === $blockId) {
                $this->blocks[$i]['config'][$key] = $value;
                PageWidget::where('id', $blockId)->update(['config' => $this->blocks[$i]['config']]);
                break;
            }
        }
    }

    /**
     * Explicitly save a query config value — used for scalar fields that prefer
     * a direct call over the wire:model + updated() hook approach.
     */
    public function updateQueryConfig(string $blockId, string $collHandle, string $key, mixed $value): void
    {
        foreach ($this->blocks as $i => $block) {
            if ($block['id'] === $blockId) {
                $qc                   = $this->blocks[$i]['query_config'];
                $qc[$collHandle][$key] = $value;
                $this->blocks[$i]['query_config'] = $qc;
                PageWidget::where('id', $blockId)->update(['query_config' => $qc]);
                break;
            }
        }
    }

    /**
     * Livewire lifecycle hook — auto-persists wire:model-bound field changes to DB.
     * Handles: blocks.N.label, blocks.N.config.*, blocks.N.query_config.*
     */
    public function updated(string $name): void
    {
        if (preg_match('/^blocks\.(\d+)\.label$/', $name, $m)) {
            $index = (int) $m[1];
            $block = $this->blocks[$index] ?? null;
            if ($block) {
                PageWidget::where('id', $block['id'])->update(['label' => $block['label']]);
            }
            return;
        }

        if (preg_match('/^blocks\.(\d+)\.config/', $name, $m)) {
            $index = (int) $m[1];
            $block = $this->blocks[$index] ?? null;
            if ($block) {
                PageWidget::where('id', $block['id'])->update(['config' => $block['config']]);
            }
            return;
        }

        if (preg_match('/^blocks\.(\d+)\.query_config/', $name, $m)) {
            $index = (int) $m[1];
            $block = $this->blocks[$index] ?? null;
            if ($block) {
                PageWidget::where('id', $block['id'])->update(['query_config' => $block['query_config']]);
            }
        }
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.page-builder');
    }
}
