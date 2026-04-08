<div
    class="page-builder"
    x-data="{
        selectedBlockId: @js($selectedBlockId),
    }"
    x-on:block-selected.window="selectedBlockId = $event.detail.blockId"
    x-on:keydown.escape.window="
        if ($wire.mode === 'edit' && selectedBlockId !== '') {
            $wire.selectBlock('');
        }
    "
>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Toolbar --}}
    {{-- ------------------------------------------------------------------ --}}
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <p class="text-sm text-gray-500">
                {{ count($blocks) }} block(s) on this page.
            </p>

            {{-- Edit / Handles mode toggle --}}
            <div class="inline-flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden">
                <button
                    type="button"
                    wire:click="switchToEdit"
                    class="px-3 py-1.5 text-xs font-medium transition-colors {{ $mode === 'edit' ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300' }}"
                >Edit</button>
                <button
                    type="button"
                    wire:click="switchToHandles"
                    class="px-3 py-1.5 text-xs font-medium transition-colors {{ $mode === 'handles' ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300' }}"
                >Handles</button>
            </div>
        </div>

        <div class="flex items-center gap-2">
            @if (count($blocks) > 0)
                <button
                    type="button"
                    wire:click="openSaveTemplateModal()"
                    class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                >
                    Save as Template
                </button>
            @endif
            <button
                type="button"
                wire:click="openAddModal()"
                class="inline-flex items-center gap-1 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus:outline-none"
            >
                + Add Block
            </button>
        </div>
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Edit mode: unified preview + inspector --}}
    {{-- ------------------------------------------------------------------ --}}
    @if ($mode === 'edit')
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">

        {{-- ── Left pane: stacked widget preview ─────────────────────── --}}
        <div
            class="min-w-0"
            x-data="previewManager(@js($requiredLibs))"
            x-on:resize.window.debounce.150ms="computeZoom()"
            x-on:preview-content-changed.window="handlePreviewContentChanged($event)"
        >
            {{-- Viewport width toggle --}}
            <div class="flex items-center justify-end gap-1 mb-2 px-1">
                <span class="mr-1 text-xs text-gray-400">Viewport:</span>
                <template x-for="vp in [{w: 1920, label: 'Desktop', icon: 'M4 5h16a1 1 0 011 1v8a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1zM7 18h10'}, {w: 1024, label: 'Tablet', icon: 'M7 4h10a1 1 0 011 1v14a1 1 0 01-1 1H7a1 1 0 01-1-1V5a1 1 0 011-1zm5 16v.01'}, {w: 375, label: 'Mobile', icon: 'M9 3h6a1 1 0 011 1v16a1 1 0 01-1 1H9a1 1 0 01-1-1V4a1 1 0 011-1zm3 18v.01'}]">
                    <button
                        type="button"
                        x-on:click="setViewport(vp.w)"
                        x-bind:class="presetViewport === vp.w ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:text-gray-300 dark:hover:bg-gray-700'"
                        x-bind:title="vp.label + ' (' + vp.w + 'px)'"
                        class="rounded p-1 transition-colors"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" x-bind:d="vp.icon"/>
                        </svg>
                    </button>
                </template>
                <span class="ml-1 text-xs text-gray-300 dark:text-gray-600 tabular-nums" x-text="presetViewport + 'px'"></span>
            </div>

            {{-- Preview container — wire:ignore prevents Livewire from morphing
                 the preview on selection changes. Content updates are pushed via
                 the preview-content-changed event and applied manually. --}}
            <div
                class="relative rounded-lg border border-gray-200 dark:border-gray-700 bg-white"
                x-bind:style="presetViewport < 1920
                    ? 'height: calc(100vh - 16rem); overflow-y: auto; display: flex; justify-content: center; background: #f3f4f6;'
                    : 'min-height: 400px; overflow: hidden;'"
                wire:ignore
            >
                <div
                    x-ref="previewScope"
                    class="widget-preview-scope np-site"
                    x-bind:style="'width: ' + presetViewport + 'px; zoom: ' + zoomFactor + '; transform-origin: top left;'
                        + (presetViewport < 1920 ? ' flex-shrink: 0;' : '')"
                    x-on:click="if ($event.target.closest('a')) $event.preventDefault()"
                    x-on:submit.prevent
                >
                    @forelse ($previewBlocks as $pBlock)
                        <div
                            class="widget-preview-region"
                            data-widget-id="{{ $pBlock['id'] }}"
                            x-on:click.stop="selectedBlockId = '{{ $pBlock['id'] }}'; $wire.selectBlock('{{ $pBlock['id'] }}')"
                            x-bind:class="{ 'widget-preview-region--selected': selectedBlockId === '{{ $pBlock['id'] }}' }"
                        >
                            <div x-ignore>{!! $pBlock['html'] !!}</div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-sm text-gray-400">
                            No blocks yet. Click <strong>+ Add Block</strong> to get started.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ── Right pane: inspector panel ───────────────────────────── --}}
        <div
            class="min-w-0"
            style="position: sticky; top: 1rem; max-height: calc(100vh - 6rem); overflow-y: auto; align-self: flex-start;"
        >
            @livewire('page-builder-inspector', ['blockId' => $selectedBlockId], key('inspector-' . $selectedBlockId))
        </div>

    </div>
    @endif {{-- end edit mode --}}

    {{-- ------------------------------------------------------------------ --}}
    {{-- Handles mode: block card list + inspector --}}
    {{-- ------------------------------------------------------------------ --}}
    @if ($mode === 'handles')
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">

        {{-- ── Left column: structural block list ───────────────────── --}}
        <div class="min-w-0 space-y-4">
            <div
                x-sort="() => {
                    const payload = [...$el.querySelectorAll(':scope > [data-block-id]')].map((el, i) => ({
                        id: el.dataset.blockId, parent_id: null, column_index: null, sort_order: i
                    }));
                    $wire.updateOrder(payload);
                }"
                class="space-y-2"
            >
                @php
                    $columnTargets = collect($blocks)
                        ->filter(fn ($b) => $b['widget_type_handle'] === 'column_widget')
                        ->map(fn ($b) => ['id' => $b['id'], 'label' => $b['label'], 'num_columns' => (int) ($b['config']['num_columns'] ?? 2)])
                        ->values()->toArray();
                @endphp
                @forelse ($blocks as $index => $block)
                    @livewire('page-builder-block', [
                        'blockId'    => $block['id'],
                        'isFirst'    => $loop->first,
                        'isLast'     => $loop->last,
                        'isRequired' => $block['is_required'],
                        'pageType'   => $pageType,
                        'columnTargets' => $block['widget_type_handle'] !== 'column_widget' ? $columnTargets : [],
                    ], key('block-' . $block['id']))
                @empty
                    <div class="rounded-lg border-2 border-dashed border-gray-200 p-8 text-center text-sm text-gray-400 dark:border-gray-700">
                        No blocks yet. Click <strong>+ Add Block</strong> to get started.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ── Right column: inspector panel ─────────────────────────── --}}
        <div
            class="min-w-0"
            style="position: sticky; top: 1rem; max-height: calc(100vh - 6rem); overflow-y: auto; align-self: flex-start;"
        >
            @livewire('page-builder-inspector', ['blockId' => $selectedBlockId], key('inspector-handles-' . $selectedBlockId))
        </div>

    </div>
    @endif {{-- end handles mode --}}

    {{-- ------------------------------------------------------------------ --}}
    {{-- Vue proof-of-concept (temporary — removed in session 151)            --}}
    {{-- ------------------------------------------------------------------ --}}
    <div id="page-builder-app" data-bootstrap='@json($bootstrapData)' wire:ignore></div>
    @vite('resources/js/page-builder-vue/main.ts')

    {{-- ------------------------------------------------------------------ --}}
    {{-- Add Block Modal                                                      --}}
    {{-- ------------------------------------------------------------------ --}}
    @if ($showAddModal)
        <x-widget-picker-modal
            :widget-types="$widgetTypes"
            title="Add Block"
            show-property="showAddModal"
            create-action="$wire.createBlock"
        />
    @endif

    {{-- ------------------------------------------------------------------ --}}
    {{-- Save as Template Modal                                               --}}
    {{-- ------------------------------------------------------------------ --}}
    @if ($showSaveTemplateModal)
    @teleport('body')
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            x-on:keydown.escape.window="$wire.set('showSaveTemplateModal', false)"
        >
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900">
                <h3 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">Save as Content Template</h3>

                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Template Name</label>
                        <input
                            type="text"
                            wire:model="saveTemplateName"
                            placeholder="e.g. Landing Page, About Us…"
                            autofocus
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                        />
                        @error('saveTemplateName') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Description (optional)</label>
                        <textarea
                            wire:model="saveTemplateDescription"
                            rows="3"
                            placeholder="Describe what this template is for…"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                        ></textarea>
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        wire:click="$set('showSaveTemplateModal', false)"
                        class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                    >Cancel</button>
                    <button
                        type="button"
                        wire:click="saveAsTemplate"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500"
                    >Save Template</button>
                </div>
            </div>
        </div>
    @endteleport
    @endif

</div>
