<div class="page-builder space-y-4">

    {{-- ------------------------------------------------------------------ --}}
    {{-- Header                                                               --}}
    {{-- ------------------------------------------------------------------ --}}
    <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">
            {{ count($blocks) }} block(s) on this page.
        </p>
        <button
            type="button"
            wire:click="openAddModal"
            class="inline-flex items-center gap-1 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus:outline-none"
        >
            + Add Block
        </button>
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Block list                                                           --}}
    {{-- ------------------------------------------------------------------ --}}
    @forelse ($blocks as $index => $block)
        <div
            wire:key="block-{{ $block['id'] }}"
            class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
        >
            {{-- Block header bar --}}
            <div class="flex items-center gap-2 border-b border-gray-100 px-4 py-2 dark:border-gray-700">

                {{-- Type badge --}}
                <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                    {{ $block['widget_type_label'] }}
                </span>

                {{-- Optional admin label --}}
                @if ($block['label'] && $block['label'] !== $block['widget_type_label'])
                    <span class="text-sm text-gray-500">— {{ $block['label'] }}</span>
                @endif

                <div class="ml-auto flex items-center gap-1">

                    {{-- Move Up --}}
                    <button
                        type="button"
                        wire:click="moveUp({{ $index }})"
                        @class(['opacity-30 cursor-not-allowed' => $index === 0])
                        @disabled($index === 0)
                        title="Move up"
                        class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-700"
                    >↑</button>

                    {{-- Move Down --}}
                    <button
                        type="button"
                        wire:click="moveDown({{ $index }})"
                        @class(['opacity-30 cursor-not-allowed' => $index === count($blocks) - 1])
                        @disabled($index === count($blocks) - 1)
                        title="Move down"
                        class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-700"
                    >↓</button>

                    {{-- Configure (widget blocks only) --}}
                    @if ($block['widget_type_handle'] !== 'text_block')
                        <button
                            type="button"
                            wire:click="openConfigModal({{ $index }})"
                            title="Configure"
                            class="rounded px-2 py-1 text-xs font-medium text-primary-600 hover:bg-primary-50 dark:hover:bg-gray-700"
                        >Configure</button>
                    @endif

                    {{-- Delete / confirm --}}
                    @if ($confirmDeleteIndex === $index)
                        <span class="text-xs text-danger-600 font-medium">Delete?</span>
                        <button
                            type="button"
                            wire:click="deleteBlock"
                            class="rounded px-2 py-1 text-xs font-semibold text-white bg-danger-600 hover:bg-danger-500"
                        >Yes</button>
                        <button
                            type="button"
                            wire:click="cancelDelete"
                            class="rounded px-2 py-1 text-xs text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700"
                        >Cancel</button>
                    @else
                        <button
                            type="button"
                            wire:click="confirmDelete({{ $index }})"
                            title="Delete block"
                            class="rounded p-1 text-gray-400 hover:bg-danger-50 hover:text-danger-600 dark:hover:bg-gray-700"
                        >✕</button>
                    @endif

                </div>
            </div>

            {{-- Block body --}}
            <div class="p-4">

                @if ($block['widget_type_handle'] === 'text_block')
                    {{-- -------------------------------------------------- --}}
                    {{-- Text block: inline Trix editor                       --}}
                    {{-- -------------------------------------------------- --}}
                    <div wire:ignore>
                        <input
                            id="trix-{{ $block['id'] }}"
                            type="hidden"
                            value="{{ $block['query_config']['content'] ?? '' }}"
                        >
                        <trix-editor
                            input="trix-{{ $block['id'] }}"
                            x-on:trix-change="$wire.updateTextContent('{{ $block['id'] }}', $event.target.value)"
                            class="min-h-[120px] rounded border border-gray-300 bg-white p-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                        ></trix-editor>
                    </div>

                @else
                    {{-- -------------------------------------------------- --}}
                    {{-- Widget block: show query config summary              --}}
                    {{-- -------------------------------------------------- --}}
                    @if (empty($block['widget_type_collections']))
                        <p class="text-xs text-gray-400 italic">No collections configured for this widget type.</p>
                    @else
                        @foreach ($block['widget_type_collections'] as $collHandle)
                            @php $cfg = $block['query_config'][$collHandle] ?? []; @endphp
                            <div class="mb-2 rounded bg-gray-50 px-3 py-2 text-xs dark:bg-gray-700">
                                <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $collHandle }}</span>
                                <span class="ml-2 text-gray-500">
                                    limit: {{ $cfg['limit'] ?? '—' }} &nbsp;|&nbsp;
                                    order: {{ $cfg['order_by'] ?? 'sort_order' }} {{ $cfg['direction'] ?? 'asc' }}
                                    @if (!empty($cfg['include_tags']))
                                        &nbsp;|&nbsp; include: {{ implode(', ', $cfg['include_tags']) }}
                                    @endif
                                    @if (!empty($cfg['exclude_tags']))
                                        &nbsp;|&nbsp; exclude: {{ implode(', ', $cfg['exclude_tags']) }}
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    @endif

                @endif
            </div>
        </div>
    @empty
        <div class="rounded-lg border-2 border-dashed border-gray-200 p-8 text-center text-sm text-gray-400 dark:border-gray-700">
            No blocks yet. Click <strong>+ Add Block</strong> to get started.
        </div>
    @endforelse

    {{-- ------------------------------------------------------------------ --}}
    {{-- Add Block Modal                                                      --}}
    {{-- ------------------------------------------------------------------ --}}
    @if ($showAddModal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            x-on:keydown.escape.window="$wire.set('showAddModal', false)"
        >
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900">
                <h3 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">Add Block</h3>

                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                    Block Type
                </label>
                <select
                    wire:model="selectedWidgetTypeId"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                >
                    <option value="">— Select a block type —</option>
                    @foreach ($widgetTypes as $wt)
                        <option value="{{ $wt['id'] }}">{{ $wt['label'] }}</option>
                    @endforeach
                </select>

                @error('selectedWidgetTypeId')
                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                @enderror

                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        wire:click="$set('showAddModal', false)"
                        class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                    >Cancel</button>
                    <button
                        type="button"
                        wire:click="createBlock"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500"
                    >Add Block</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ------------------------------------------------------------------ --}}
    {{-- Configure Modal (widget blocks)                                      --}}
    {{-- ------------------------------------------------------------------ --}}
    @if ($showConfigModal && $configModalBlockIndex !== null)
        @php $configBlock = $blocks[$configModalBlockIndex] ?? null; @endphp
        @if ($configBlock)
            <div
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                x-on:keydown.escape.window="$wire.closeConfigModal()"
            >
                <div class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900 overflow-y-auto max-h-[90vh]">
                    <h3 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">
                        Configure: {{ $configBlock['widget_type_label'] }}
                    </h3>

                    {{-- Admin label --}}
                    <div class="mb-4">
                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Admin Label <span class="text-gray-400 font-normal">(optional)</span>
                        </label>
                        <input
                            type="text"
                            wire:model="configModalLabel"
                            placeholder="{{ $configBlock['widget_type_label'] }}"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                        >
                    </div>

                    {{-- Per-collection config --}}
                    @foreach ($configBlock['widget_type_collections'] as $collHandle)
                        <div class="mb-5 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <h4 class="mb-3 text-sm font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide">
                                Collection: {{ $collHandle }}
                            </h4>

                            <div class="grid grid-cols-2 gap-3 mb-3">
                                <div>
                                    <label class="block mb-1 text-xs font-medium text-gray-600 dark:text-gray-400">Limit</label>
                                    <input
                                        type="number"
                                        min="1"
                                        wire:model="configModalQueryConfig.{{ $collHandle }}.limit"
                                        placeholder="All"
                                        class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                    >
                                </div>
                                <div>
                                    <label class="block mb-1 text-xs font-medium text-gray-600 dark:text-gray-400">Direction</label>
                                    <select
                                        wire:model="configModalQueryConfig.{{ $collHandle }}.direction"
                                        class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                    >
                                        <option value="asc">Ascending</option>
                                        <option value="desc">Descending</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="block mb-1 text-xs font-medium text-gray-600 dark:text-gray-400">Order By</label>
                                <select
                                    wire:model="configModalQueryConfig.{{ $collHandle }}.order_by"
                                    class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                >
                                    <option value="sort_order">Sort Order</option>
                                    <option value="created_at">Created At</option>
                                    <option value="updated_at">Updated At</option>
                                    <option value="published_at">Published At</option>
                                </select>
                            </div>

                            @if (count($cmsTags) > 0)
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block mb-1 text-xs font-medium text-gray-600 dark:text-gray-400">Include Tags</label>
                                        <div class="space-y-1 max-h-32 overflow-y-auto">
                                            @foreach ($cmsTags as $tag)
                                                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                                    <input
                                                        type="checkbox"
                                                        wire:model="configModalQueryConfig.{{ $collHandle }}.include_tags"
                                                        value="{{ $tag['slug'] }}"
                                                        class="rounded border-gray-300"
                                                    >
                                                    {{ $tag['name'] }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block mb-1 text-xs font-medium text-gray-600 dark:text-gray-400">Exclude Tags</label>
                                        <div class="space-y-1 max-h-32 overflow-y-auto">
                                            @foreach ($cmsTags as $tag)
                                                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                                    <input
                                                        type="checkbox"
                                                        wire:model="configModalQueryConfig.{{ $collHandle }}.exclude_tags"
                                                        value="{{ $tag['slug'] }}"
                                                        class="rounded border-gray-300"
                                                    >
                                                    {{ $tag['name'] }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            wire:click="closeConfigModal"
                            class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                        >Cancel</button>
                        <button
                            type="button"
                            wire:click="saveConfigModal"
                            class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500"
                        >Save</button>
                    </div>
                </div>
            </div>
        @endif
    @endif

</div>
