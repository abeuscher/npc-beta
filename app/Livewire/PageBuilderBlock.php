<?php

namespace App\Livewire;

use App\Models\CmsTag;
use App\Models\PageWidget;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class PageBuilderBlock extends Component
{
    public string $blockId;

    #[Reactive]
    public bool $isFirst = false;

    #[Reactive]
    public bool $isLast = false;

    /** @var array<string, mixed> */
    public array $block = [];

    /** @var array<int, array<string, mixed>> */
    public array $cmsTags = [];

    public function mount(string $blockId, bool $isFirst = false, bool $isLast = false): void
    {
        $this->blockId = $blockId;
        $this->isFirst = $isFirst;
        $this->isLast  = $isLast;

        $this->loadBlock();

        $this->cmsTags = CmsTag::orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->toArray();
    }

    public function loadBlock(): void
    {
        $pw = PageWidget::with('widgetType')->find($this->blockId);

        if (! $pw) {
            return;
        }

        $this->block = [
            'id'                        => $pw->id,
            'widget_type_handle'        => $pw->widgetType?->handle ?? '',
            'widget_type_label'         => $pw->widgetType?->label ?? 'Unknown',
            'widget_type_collections'   => $pw->widgetType?->collections ?? [],
            'widget_type_config_schema' => $pw->widgetType?->config_schema ?? [],
            'label'                     => $pw->label ?? '',
            'config'                    => $pw->config ?? [],
            'query_config'              => $pw->query_config ?? [],
            'sort_order'                => $pw->sort_order ?? 0,
        ];
    }

    /**
     * Explicitly save a richtext (Quill) value — cannot use wire:model due to wire:ignore.
     */
    public function updateConfig(string $key, mixed $value): void
    {
        $this->block['config'][$key] = $value;
        PageWidget::where('id', $this->blockId)->update(['config' => $this->block['config']]);
    }

    /**
     * Auto-persist wire:model-bound field changes.
     */
    public function updated(string $name): void
    {
        if ($name === 'block.label') {
            PageWidget::where('id', $this->blockId)->update(['label' => $this->block['label']]);
            return;
        }

        if (str_starts_with($name, 'block.config')) {
            PageWidget::where('id', $this->blockId)->update(['config' => $this->block['config']]);
            return;
        }

        if (str_starts_with($name, 'block.query_config')) {
            PageWidget::where('id', $this->blockId)->update(['query_config' => $this->block['query_config']]);
        }
    }

    // -------------------------------------------------------------------------
    // Dispatch events to parent for list-level operations
    // -------------------------------------------------------------------------

    public function requestDelete(): void
    {
        $this->dispatch('block-delete-requested', blockId: $this->blockId);
    }

    public function requestCopy(): void
    {
        $this->dispatch('block-copy-requested', blockId: $this->blockId);
    }

    public function requestMoveUp(): void
    {
        $this->dispatch('block-move-up-requested', blockId: $this->blockId);
    }

    public function requestMoveDown(): void
    {
        $this->dispatch('block-move-down-requested', blockId: $this->blockId);
    }

    public function requestAddAbove(): void
    {
        $this->dispatch('block-add-modal-requested', blockId: $this->blockId, below: false);
    }

    public function requestAddBelow(): void
    {
        $this->dispatch('block-add-modal-requested', blockId: $this->blockId, below: true);
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.page-builder-block');
    }
}
