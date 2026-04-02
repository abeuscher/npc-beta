<?php

namespace App\Livewire;

use App\Models\Collection;
use App\Models\PageWidget;
use App\Models\Tag;
use App\Services\PageBuilderDataSources;
use Livewire\Component;
use Livewire\WithFileUploads;

class PageBuilderInspector extends Component
{
    use WithFileUploads;

    public string $blockId = '';

    /** @var array<string, mixed> */
    public array $block = [];

    /** @var array<string, mixed> CMS tags for query settings. */
    public array $cmsTags = [];

    /** @var array<string, array<string, string>> Resolved options for select fields, keyed by field key. */
    public array $selectOptions = [];

    /** @var array<string, mixed> Temporary file uploads for image config fields, keyed by field key. */
    public array $imageUploads = [];

    /** @var array<string, string|null> Current image URLs for preview, keyed by field key. */
    public array $imageUrls = [];

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
        $this->resolveImageUrls();
    }

    private function resolveImageUrls(): void
    {
        $this->imageUrls = [];
        $this->imageUploads = [];

        $pw = PageWidget::find($this->blockId);
        if (!$pw) {
            return;
        }

        foreach ($this->block['widget_type_config_schema'] as $field) {
            if (($field['type'] ?? '') === 'image') {
                $media = $pw->getFirstMedia("config_{$field['key']}");
                $this->imageUrls[$field['key']] = $media?->getUrl();
            }
        }
    }

    public function updatedImageUploads(mixed $value, string $key): void
    {
        if (!$this->validateBlockOwnership()) {
            return;
        }

        $upload = $this->imageUploads[$key] ?? null;
        if (!$upload) {
            return;
        }

        $pw = PageWidget::find($this->blockId);
        if (!$pw) {
            return;
        }

        $collectionName = "config_{$key}";

        $pw->clearMediaCollection($collectionName);

        $pw->addMedia($upload->getRealPath())
            ->usingFileName($upload->hashName())
            ->toMediaCollection($collectionName, 'public');

        $media = $pw->getFirstMedia($collectionName);
        $this->imageUrls[$key] = $media?->getUrl();

        $this->block['config'][$key] = $media?->id;
        PageWidget::where('id', $this->blockId)->update(['config' => $this->block['config']]);

        $this->imageUploads[$key] = null;
    }

    public function removeImage(string $key): void
    {
        if (!$this->validateBlockOwnership()) {
            return;
        }

        $pw = PageWidget::find($this->blockId);
        if (!$pw) {
            return;
        }

        $pw->clearMediaCollection("config_{$key}");
        $this->imageUrls[$key] = null;

        $this->block['config'][$key] = null;
        PageWidget::where('id', $this->blockId)->update(['config' => $this->block['config']]);
    }

    private function resolveSelectOptions(): void
    {
        $this->selectOptions = [];

        foreach ($this->block['widget_type_config_schema'] as $field) {
            if (($field['type'] ?? '') !== 'select') {
                continue;
            }

            if (! empty($field['options_from'])) {
                $source = $field['options_from'];

                // Dynamic source: "collection_fields:type" resolves fields from the
                // collection currently selected in the config's depends_on field.
                if (str_starts_with($source, 'collection_fields:')) {
                    $this->selectOptions[$field['key']] = $this->resolveCollectionFieldOptions($field);
                } else {
                    $this->selectOptions[$field['key']] = PageBuilderDataSources::resolve($source);
                }
            } elseif (! empty($field['options'])) {
                $this->selectOptions[$field['key']] = $field['options'];
            }
        }
    }

    private function resolveCollectionFieldOptions(array $field): array
    {
        $dependsOn = $field['depends_on'] ?? 'collection_handle';
        $handle = $this->block['config'][$dependsOn] ?? '';

        if ($handle === '') {
            return [];
        }

        $collection = Collection::where('handle', $handle)->first();
        if (! $collection) {
            return [];
        }

        $filterType = str_replace('collection_fields:', '', $field['options_from']);
        $fields = collect($collection->fields ?? []);

        if ($filterType !== '') {
            $fields = $fields->where('type', $filterType);
        }

        return $fields->pluck('label', 'key')->all();
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
        if (str_starts_with($name, 'imageUploads')) {
            return;
        }

        if (! $this->validateBlockOwnership()) {
            return;
        }

        if ($name === 'block.label') {
            PageWidget::where('id', $this->blockId)->update(['label' => $this->block['label']]);
            return;
        }

        if (str_starts_with($name, 'block.config')) {
            PageWidget::where('id', $this->blockId)->update(['config' => $this->block['config']]);
            $this->resolveSelectOptions();
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
