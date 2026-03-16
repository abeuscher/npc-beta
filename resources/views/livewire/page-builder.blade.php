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
            wire:click="openAddModal()"
            class="inline-flex items-center gap-1 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus:outline-none"
        >
            + Add Block
        </button>
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Block list                                                           --}}
    {{--                                                                      --}}
    {{-- @alpinejs/sort is loaded via AdminPanelProvider and enables          --}}
    {{-- drag-to-reorder via x-sort / x-sort:item / x-sort:handle below.     --}}
    {{-- If CSP blocks it (eval() issue from Session 007), the Up / Down      --}}
    {{-- options in each block's ellipsis menu serve as a fallback.           --}}
    {{-- TODO: remove moveUp/moveDown from PageBuilder.php and the ellipsis   --}}
    {{-- menu once CSP is resolved and drag-to-reorder is confirmed working.  --}}
    {{-- ------------------------------------------------------------------ --}}
    <div
        x-data
        x-sort="() => {
            const ids = [...$el.querySelectorAll('[x-sort\\:item]')].map(e => e.getAttribute('x-sort:item'));
            $wire.updateOrder(ids);
        }"
        class="space-y-2"
    >
        @forelse ($blocks as $index => $block)
            <div
                wire:key="block-{{ $block['id'] }}"
                x-sort:item="'{{ $block['id'] }}'"
                x-data="{ open: false, menuOpen: false, confirmDelete: false }"
                class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                {{-- Block header --}}
                <div class="flex items-center gap-2 px-3 py-2">

                    {{-- Drag handle --}}
                    <div
                        x-sort:handle
                        title="Drag to reorder"
                        class="flex-shrink-0 cursor-grab text-gray-300 hover:text-gray-500 dark:text-gray-600 dark:hover:text-gray-400 select-none"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </div>

                    {{-- Collapse toggle --}}
                    <button
                        type="button"
                        x-on:click="open = !open"
                        class="flex flex-1 items-center gap-2 text-left min-w-0"
                    >
                        @if ($block['label'])
                            <span class="truncate text-sm font-medium text-gray-800 dark:text-gray-100">
                                {{ $block['label'] }}
                            </span>
                        @endif

                        <span class="flex-shrink-0 rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                            {{ $block['widget_type_label'] }}
                        </span>

                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="ml-auto flex-shrink-0 h-4 w-4 text-gray-400 transition-transform duration-150"
                            x-bind:class="{ 'rotate-180': open }"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    {{-- Ellipsis menu --}}
                    <div class="relative flex-shrink-0">
                        <button
                            type="button"
                            x-on:click="menuOpen = !menuOpen; confirmDelete = false"
                            class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-700"
                            title="Block options"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                <circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/>
                            </svg>
                        </button>

                        <div
                            x-show="menuOpen"
                            x-on:click.outside="menuOpen = false; confirmDelete = false"
                            x-cloak
                            class="absolute right-0 top-8 z-20 w-52 rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800"
                        >
                            <template x-if="!confirmDelete">
                                <div>
                                    <button
                                        type="button"
                                        wire:click="openAddModal({{ $index }})"
                                        x-on:click="menuOpen = false"
                                        class="flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700"
                                    >Add Block Above</button>

                                    <button
                                        type="button"
                                        wire:click="openAddModal({{ $index + 1 }})"
                                        x-on:click="menuOpen = false"
                                        class="flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700"
                                    >Add Block Below</button>

                                    <button
                                        type="button"
                                        wire:click="copyBlock({{ $index }})"
                                        x-on:click="menuOpen = false"
                                        class="flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700"
                                    >Copy Block</button>

                                    {{--
                                        CSP FALLBACK: Uncomment these two buttons if @alpinejs/sort
                                        is blocked by the CSP eval() issue (Session 007).
                                        Once CSP is resolved and drag-to-reorder works, remove these
                                        and the moveUp / moveDown methods from PageBuilder.php.
                                    --}}
                                    {{--
                                    <hr class="my-1 border-gray-100 dark:border-gray-700">
                                    <button type="button" wire:click="moveUp({{ $index }})" x-on:click="menuOpen = false"
                                        class="flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700"
                                        @if($index === 0) disabled @endif>Move Up</button>
                                    <button type="button" wire:click="moveDown({{ $index }})" x-on:click="menuOpen = false"
                                        class="flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700"
                                        @if($index === count($blocks) - 1) disabled @endif>Move Down</button>
                                    --}}

                                    <hr class="my-1 border-gray-100 dark:border-gray-700">

                                    <button
                                        type="button"
                                        x-on:click="confirmDelete = true"
                                        class="flex w-full items-center gap-2 px-4 py-2 text-sm font-medium text-danger-600 hover:bg-danger-50 dark:hover:bg-gray-700"
                                    >Delete</button>
                                </div>
                            </template>

                            <template x-if="confirmDelete">
                                <div class="px-4 py-3">
                                    <p class="mb-2 text-sm font-medium text-danger-600">Delete this block?</p>
                                    <div class="flex gap-2">
                                        <button
                                            type="button"
                                            wire:click="deleteBlock('{{ $block['id'] }}')"
                                            class="rounded-lg bg-danger-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-danger-500"
                                        >Yes, delete</button>
                                        <button
                                            type="button"
                                            x-on:click="confirmDelete = false"
                                            class="rounded-lg px-3 py-1.5 text-xs text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700"
                                        >Cancel</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Block body (collapsed by default).                           --}}
                {{-- x-if is used (not x-show) so Trix only mounts once the card  --}}
                {{-- is opened — Trix doesn't initialize properly under display:none --}}
                <template x-if="open">
                    <div class="space-y-4 border-t border-gray-100 p-4 dark:border-gray-700">

                        {{-- Label --}}
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                Label <span class="font-normal text-gray-400">(for internal use)</span>
                            </label>
                            <input
                                type="text"
                                wire:model.lazy="blocks.{{ $index }}.label"
                                placeholder="{{ $block['widget_type_label'] }}"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                            >
                        </div>

                        {{-- Singleton fields from config_schema --}}
                        @foreach ($block['widget_type_config_schema'] as $field)
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                    {{ $field['label'] }}
                                </label>

                                @if ($field['type'] === 'richtext')
                                    <div wire:ignore>
                                        <input
                                            id="trix-{{ $block['id'] }}-{{ $field['key'] }}"
                                            type="hidden"
                                            value="{{ $block['config'][$field['key']] ?? '' }}"
                                        >
                                        <trix-editor
                                            input="trix-{{ $block['id'] }}-{{ $field['key'] }}"
                                            x-on:trix-change="$wire.updateConfig('{{ $block['id'] }}', '{{ $field['key'] }}', $event.target.value)"
                                            class="min-h-[120px] rounded border border-gray-300 bg-white p-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                                        ></trix-editor>
                                    </div>

                                @elseif ($field['type'] === 'textarea')
                                    <textarea
                                        wire:model.lazy="blocks.{{ $index }}.config.{{ $field['key'] }}"
                                        rows="4"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                    ></textarea>

                                @elseif ($field['type'] === 'toggle')
                                    <label class="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            wire:model="blocks.{{ $index }}.config.{{ $field['key'] }}"
                                            class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500"
                                        >
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Enabled</span>
                                    </label>

                                @elseif ($field['type'] === 'number')
                                    <input
                                        type="number"
                                        wire:model.lazy="blocks.{{ $index }}.config.{{ $field['key'] }}"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                    >

                                @elseif ($field['type'] === 'url')
                                    <input
                                        type="url"
                                        wire:model.lazy="blocks.{{ $index }}.config.{{ $field['key'] }}"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                    >

                                @else {{-- text (default) --}}
                                    <input
                                        type="text"
                                        wire:model.lazy="blocks.{{ $index }}.config.{{ $field['key'] }}"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                    >
                                @endif
                            </div>
                        @endforeach

                        {{-- Query Settings — collapsible subsection --}}
                        @if (! empty($block['widget_type_collections']))
                            <div x-data="{ qOpen: false }" class="pt-2">
                                <button
                                    type="button"
                                    x-on:click="qOpen = !qOpen"
                                    class="flex items-center gap-1.5 text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="h-3.5 w-3.5 transition-transform duration-150"
                                        x-bind:class="{ 'rotate-90': qOpen }"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                    </svg>
                                    Query Settings
                                </button>

                                <div x-show="qOpen" x-cloak class="mt-3 space-y-4">
                                    @foreach ($block['widget_type_collections'] as $collHandle)
                                        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                                            <h5 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">
                                                {{ $collHandle }}
                                            </h5>

                                            <div class="mb-3 grid grid-cols-2 gap-3">
                                                <div>
                                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Limit</label>
                                                    <input
                                                        type="number"
                                                        min="1"
                                                        wire:model.lazy="blocks.{{ $index }}.query_config.{{ $collHandle }}.limit"
                                                        placeholder="All"
                                                        class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                                    >
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Direction</label>
                                                    <select
                                                        wire:model="blocks.{{ $index }}.query_config.{{ $collHandle }}.direction"
                                                        class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                                    >
                                                        <option value="asc">Ascending</option>
                                                        <option value="desc">Descending</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Order By</label>
                                                <select
                                                    wire:model="blocks.{{ $index }}.query_config.{{ $collHandle }}.order_by"
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
                                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Include Tags</label>
                                                        <div class="max-h-32 space-y-1 overflow-y-auto">
                                                            @foreach ($cmsTags as $tag)
                                                                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                                                    <input
                                                                        type="checkbox"
                                                                        wire:model="blocks.{{ $index }}.query_config.{{ $collHandle }}.include_tags"
                                                                        value="{{ $tag['slug'] }}"
                                                                        class="rounded border-gray-300"
                                                                    >
                                                                    {{ $tag['name'] }}
                                                                </label>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Exclude Tags</label>
                                                        <div class="max-h-32 space-y-1 overflow-y-auto">
                                                            @foreach ($cmsTags as $tag)
                                                                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                                                    <input
                                                                        type="checkbox"
                                                                        wire:model="blocks.{{ $index }}.query_config.{{ $collHandle }}.exclude_tags"
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
                                </div>
                            </div>
                        @endif

                    </div>
                </template>

            </div>
        @empty
            <div class="rounded-lg border-2 border-dashed border-gray-200 p-8 text-center text-sm text-gray-400 dark:border-gray-700">
                No blocks yet. Click <strong>+ Add Block</strong> to get started.
            </div>
        @endforelse
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Add Block Modal                                                      --}}
    {{-- ------------------------------------------------------------------ --}}
    @if ($showAddModal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            x-on:keydown.escape.window="$wire.set('showAddModal', false)"
        >
            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900 max-h-[80vh] overflow-y-auto">
                <h3 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">Add Block</h3>

                {{-- Required block label --}}
                <div class="mb-5">
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Block Label <span class="text-danger-600">*</span>
                    </label>
                    <input
                        type="text"
                        wire:model="addModalLabel"
                        placeholder="e.g. Hero, About Us intro, News feed…"
                        autofocus
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 @error('addModalLabel') border-danger-500 @enderror"
                    >
                    @error('addModalLabel')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-400">Internal label shown in the page builder. Not visible to visitors.</p>
                </div>

                {{-- Widget type cards —  clicking one creates the block --}}
                <p class="mb-2 text-xs font-medium text-gray-500 uppercase tracking-wide">Choose a block type</p>
                <div class="grid grid-cols-2 gap-3">
                    @foreach ($widgetTypes as $wt)
                        <button
                            type="button"
                            wire:click="createBlock('{{ $wt['id'] }}')"
                            class="flex flex-col items-start rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-left shadow-sm hover:border-primary-400 hover:bg-primary-50 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-500 dark:hover:bg-gray-700"
                        >
                            <span class="font-medium text-gray-900 dark:text-white text-sm">{{ $wt['label'] }}</span>
                            <span class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">{{ $wt['handle'] }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="mt-5 flex justify-end">
                    <button
                        type="button"
                        wire:click="$set('showAddModal', false)"
                        class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                    >Cancel</button>
                </div>
            </div>
        </div>
    @endif

</div>
