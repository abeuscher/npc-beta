<?php

namespace App\Livewire;

use App\Models\PageWidget;
use App\Models\Tag;
use App\Services\PageBuilderDataSources;
use Livewire\Component;

class PageBuilderInspector extends Component
{
    public string $blockId = '';

    /** @var array<string, mixed> */
    public array $block = [];

    /** @var array<string, mixed> CMS tags for query settings. */
    public array $cmsTags = [];

    /** @var array<string, array<string, string>> Resolved options for select fields, keyed by field key. */
    public array $selectOptions = [];

    public function mount(string $blockId = ''): void
    {
        $this->blockId = $blockId;

        if ($blockId !== '') {
            $this->loadBlock();
        }
    }

    public function loadBlock(): void
    {
        $pw = PageWidget::with('widgetType')->find($this->blockId);

        if (! $pw) {
            return;
        }

        $this->block = [
            'id'                        => $pw->id,
            'page_id'                   => $pw->page_id,
            'widget_type_handle'        => $pw->widgetType?->handle ?? '',
            'widget_type_label'         => $pw->widgetType?->label ?? 'Unknown',
            'widget_type_collections'   => $pw->widgetType?->collections ?? [],
            'widget_type_config_schema' => $pw->widgetType?->config_schema ?? [],
            'widget_type_default_open'  => $pw->widgetType?->default_open ?? false,
            'label'                     => $pw->label ?? '',
            'config'                    => $pw->config ?? [],
            'query_config'              => $pw->query_config ?? [],
            'style_config'              => $pw->style_config ?? [],
        ];

        $this->cmsTags = Tag::where('type', 'collection')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->toArray();

        $this->resolveSelectOptions();
    }

    private function resolveSelectOptions(): void
    {
        $this->selectOptions = [];

        foreach ($this->block['widget_type_config_schema'] as $field) {
            if (($field['type'] ?? '') === 'select' && ! empty($field['options_from'])) {
                $this->selectOptions[$field['key']] = PageBuilderDataSources::resolve($field['options_from']);
            }
        }
    }

    /**
     * Explicitly save a richtext (Quill) value.
     */
    public function updateConfig(string $key, mixed $value): void
    {
        if (! $this->validateBlockOwnership()) {
            return;
        }

        $this->block['config'][$key] = $value;
        PageWidget::where('id', $this->blockId)->update(['config' => $this->block['config']]);
    }

    /**
     * Auto-persist wire:model-bound field changes.
     */
    public function updated(string $name): void
    {
        if (! $this->validateBlockOwnership()) {
            return;
        }

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
            return;
        }

        if (str_starts_with($name, 'block.style_config')) {
            PageWidget::where('id', $this->blockId)->update(['style_config' => $this->block['style_config']]);
        }
    }

    /**
     * Verify that the current blockId belongs to the page it was loaded for.
     * Prevents a block from another page being injected into the inspector.
     */
    private function validateBlockOwnership(): bool
    {
        if ($this->blockId === '' || empty($this->block['page_id'])) {
            return false;
        }

        return PageWidget::where('id', $this->blockId)
            ->where('page_id', $this->block['page_id'])
            ->exists();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.page-builder-inspector');
    }
}
