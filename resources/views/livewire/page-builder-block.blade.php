<div
    wire:key="block-{{ $block['id'] }}"
    x-sort:item="'{{ $block['id'] }}'"
    data-block-id="{{ $block['id'] }}"
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

        {{-- Inline label with edit-in-place --}}
        <div
            class="flex flex-1 items-center gap-1.5 min-w-0"
            x-data="{ editing: false, draft: @js($block['label']) }"
        >
            {{-- View mode --}}
            <template x-if="!editing">
                <div class="flex flex-1 items-center gap-1.5 min-w-0">
                    <button
                        type="button"
                        x-on:click="open = !open"
                        class="flex flex-1 items-center gap-2 text-left min-w-0"
                    >
                        <span class="truncate text-sm font-medium text-gray-800 dark:text-gray-100">
                            {{ $block['label'] }}
                        </span>
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
                    <button
                        type="button"
                        x-on:click.stop="draft = @js($block['label']); editing = true"
                        title="Rename block"
                        class="flex-shrink-0 rounded p-1 text-gray-300 hover:bg-gray-100 hover:text-gray-600 dark:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.364-6.364a2 2 0 112.828 2.828L11.828 15.828a2 2 0 01-1.414.586H9v-2.414a2 2 0 01.586-1.414z"/>
                        </svg>
                    </button>
                </div>
            </template>

            {{-- Edit mode --}}
            <template x-if="editing">
                <div class="flex flex-1 items-center gap-1.5 min-w-0">
                    <input
                        type="text"
                        x-model="draft"
                        x-on:keydown.enter.prevent="$wire.set('block.label', draft); editing = false"
                        x-on:keydown.escape.prevent="editing = false"
                        x-ref="labelInput"
                        x-init="$nextTick(() => $refs.labelInput.focus())"
                        class="flex-1 min-w-0 rounded border border-primary-400 bg-white px-2 py-0.5 text-sm font-medium text-gray-800 shadow-sm focus:outline-none dark:border-primary-500 dark:bg-gray-800 dark:text-gray-100"
                    >
                    <button
                        type="button"
                        x-on:click.stop="$wire.set('block.label', draft); editing = false"
                        class="flex-shrink-0 rounded bg-primary-600 px-2 py-1 text-xs font-semibold text-white hover:bg-primary-500"
                    >OK</button>
                    <button
                        type="button"
                        x-on:click.stop="editing = false"
                        class="flex-shrink-0 rounded px-2 py-1 text-xs text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700"
                    >Cancel</button>
                </div>
            </template>
        </div>

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
                            wire:click="requestAddAbove"
                            x-on:click="menuOpen = false"
                            class="flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700"
                        >Add Block Above</button>

                        <button
                            type="button"
                            wire:click="requestAddBelow"
                            x-on:click="menuOpen = false"
                            class="flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700"
                        >Add Block Below</button>

                        <button
                            type="button"
                            wire:click="requestCopy"
                            x-on:click="menuOpen = false"
                            class="flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700"
                        >Copy Block</button>

                        <hr class="my-1 border-gray-100 dark:border-gray-700">

                        <button
                            type="button"
                            wire:click="requestMoveUp"
                            x-on:click="menuOpen = false"
                            class="flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed"
                            @if($isFirst) disabled @endif
                        >Move Up</button>

                        <button
                            type="button"
                            wire:click="requestMoveDown"
                            x-on:click="menuOpen = false"
                            class="flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed"
                            @if($isLast) disabled @endif
                        >Move Down</button>

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
                                wire:click="requestDelete"
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

    {{-- Block body — x-show keeps Quill editors in the DOM from page load.      --}}
    {{-- The child component's root div contains everything, so libxml's         --}}
    {{-- HTML4 parser sees one root element and the multiple-root check passes.  --}}
    <div x-show="open" x-cloak class="space-y-4 border-t border-gray-100 p-4 dark:border-gray-700">

        {{-- Label --}}
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                Label <span class="font-normal text-gray-400">(for internal use)</span>
            </label>
            <input
                type="text"
                wire:model.lazy="block.label"
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
                    <div
                        wire:ignore
                        x-data="{
                            init() {
                                const quill = new Quill(this.$refs.editor, {
                                    theme: 'snow',
                                    modules: {
                                        toolbar: [
                                            [{ font: [] }, { size: [] }],
                                            ['bold', 'italic', 'underline', 'strike'],
                                            [{ color: [] }, { background: [] }],
                                            [{ list: 'ordered' }, { list: 'bullet' }],
                                            ['link'],
                                            ['clean']
                                        ]
                                    }
                                });

                                const initial = {{ json_encode($block['config'][$field['key']] ?? '') }};
                                if (initial) quill.root.innerHTML = initial;

                                quill.on('text-change', () => {
                                    $wire.updateConfig('{{ $field['key'] }}', quill.root.innerHTML);
                                });
                            }
                        }"
                    >
                        <div x-ref="editor" class="min-h-[120px]"></div>
                    </div>

                @elseif ($field['type'] === 'textarea')
                    <textarea
                        wire:model.lazy="block.config.{{ $field['key'] }}"
                        rows="4"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                    ></textarea>

                @elseif ($field['type'] === 'toggle')
                    <label class="flex items-center gap-2">
                        <input
                            type="checkbox"
                            wire:model="block.config.{{ $field['key'] }}"
                            class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500"
                        >
                        <span class="text-sm text-gray-700 dark:text-gray-300">Enabled</span>
                    </label>

                @elseif ($field['type'] === 'number')
                    <input
                        type="number"
                        wire:model.lazy="block.config.{{ $field['key'] }}"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                    >

                @elseif ($field['type'] === 'url')
                    <input
                        type="url"
                        wire:model.lazy="block.config.{{ $field['key'] }}"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                    >

                @else {{-- text (default) --}}
                    <input
                        type="text"
                        wire:model.lazy="block.config.{{ $field['key'] }}"
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
                                        wire:model.lazy="block.query_config.{{ $collHandle }}.limit"
                                        placeholder="All"
                                        class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                    >
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Direction</label>
                                    <select
                                        wire:model="block.query_config.{{ $collHandle }}.direction"
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
                                    wire:model="block.query_config.{{ $collHandle }}.order_by"
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
                                                        wire:model="block.query_config.{{ $collHandle }}.include_tags"
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
                                                        wire:model="block.query_config.{{ $collHandle }}.exclude_tags"
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
</div>
