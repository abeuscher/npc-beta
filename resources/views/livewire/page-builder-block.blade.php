<div
    wire:key="block-{{ $block['id'] }}"
    x-sort:item="'{{ $block['id'] }}'"
    data-block-id="{{ $block['id'] }}"
    x-data="{
        open: {{ $block['widget_type_default_open'] ? 'true' : 'false' }},
        menuOpen: false,
        confirmDelete: false,
        selected: false,
    }"
    x-on:block-selected.window="
        selected = ($event.detail.blockId === '{{ $block['id'] }}');
    "
    x-bind:class="{
        'ring-2 ring-primary-500': selected,
    }"
    class="rounded-lg border shadow-sm {{ $block['widget_type_handle'] === 'column_widget' ? 'border-gray-300 bg-[#cccccc] dark:bg-gray-700' : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800' }}"
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
                    {{-- Label area: click selects the block in the inspector (and toggles column open) --}}
                    <button
                        type="button"
                        wire:click="selectSelf"
                        class="flex flex-1 items-center gap-2 text-left min-w-0"
                        @if ($block['widget_type_handle'] === 'column_widget')
                        x-on:click="open = !open"
                        @endif
                    >
                        <span class="truncate text-sm font-medium text-gray-800 dark:text-gray-100">
                            {{ $block['label'] }}
                        </span>
                        @if ($parentBlockId === '')
                        <span class="flex-shrink-0 rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                            {{ $block['widget_type_label'] }}
                        </span>
                        @endif
                        @if ($block['widget_type_handle'] === 'column_widget')
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="ml-auto flex-shrink-0 h-4 w-4 text-gray-400 transition-transform duration-150"
                            x-bind:class="{ 'rotate-180': open }"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                        @endif
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
                            class="flex w-full items-center gap-2 px-3 py-1.5 text-xs whitespace-nowrap text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700"
                        >Add Block Above</button>

                        <button
                            type="button"
                            wire:click="requestAddBelow"
                            x-on:click="menuOpen = false"
                            class="flex w-full items-center gap-2 px-3 py-1.5 text-xs whitespace-nowrap text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700"
                        >Add Block Below</button>

                        <button
                            type="button"
                            wire:click="requestCopy"
                            x-on:click="menuOpen = false"
                            class="flex w-full items-center gap-2 px-3 py-1.5 text-xs whitespace-nowrap text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700"
                        >Copy Block</button>

                        <hr class="my-1 border-gray-100 dark:border-gray-700">

                        <button
                            type="button"
                            wire:click="requestMoveUp"
                            x-on:click="menuOpen = false"
                            class="flex w-full items-center gap-2 px-3 py-1.5 text-xs whitespace-nowrap text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed"
                            @if($isFirst) disabled @endif
                        >Move Up</button>

                        <button
                            type="button"
                            wire:click="requestMoveDown"
                            x-on:click="menuOpen = false"
                            class="flex w-full items-center gap-2 px-3 py-1.5 text-xs whitespace-nowrap text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed"
                            @if($isLast) disabled @endif
                        >Move Down</button>

                        @if (! $isRequired)
                        <hr class="my-1 border-gray-100 dark:border-gray-700">

                        <button
                            type="button"
                            x-on:click="confirmDelete = true"
                            class="flex w-full items-center gap-2 px-3 py-1.5 text-xs whitespace-nowrap font-medium text-danger-600 hover:bg-danger-50 dark:hover:bg-gray-700"
                        >Delete</button>
                        @endif
                    </div>
                </template>

                <template x-if="confirmDelete">
                    <div class="px-4 py-3">
                        <p class="mb-2 text-sm font-medium text-danger-600">Delete this block?</p>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                wire:click="requestDelete"
                                class="rounded-lg border border-danger-600 px-3 py-1.5 text-xs font-semibold text-danger-600 hover:bg-danger-50 dark:hover:bg-gray-700"
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

    {{-- Column widget slot panels --}}
    @if ($block['widget_type_handle'] === 'column_widget')
    <div x-show="open" x-cloak class="border-t border-gray-100 p-4 dark:border-gray-700">

        {{-- num_columns display --}}
        @php $numCols = isset($block['config']['num_columns']) && $block['config']['num_columns'] !== '' ? (int) $block['config']['num_columns'] : 2; @endphp

        {{-- Column slot panels --}}
        <div class="flex gap-3">
            @for ($colIdx = 0; $colIdx < $numCols; $colIdx++)
            <div class="flex-1 min-w-0 rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-200 px-3 py-2 dark:border-gray-700">
                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-400">Column {{ $colIdx + 1 }}</span>
                    <button
                        type="button"
                        wire:click="openChildAddModal({{ $colIdx }})"
                        class="rounded bg-primary-600 px-2 py-1 text-xs font-semibold text-white hover:bg-primary-500"
                    >+ Add Block</button>
                </div>
                <div class="space-y-2 p-2">
                    @forelse ($childSlots[$colIdx] ?? [] as $childIdx => $child)
                        @livewire('page-builder-block', [
                            'blockId'    => $child['id'],
                            'isFirst'    => $childIdx === 0,
                            'isLast'     => $childIdx === count($childSlots[$colIdx]) - 1,
                            'isRequired' => false,
                            'parentBlockId' => $block['id'],
                            'parentColumnIndex' => $colIdx,
                            'pageType'   => $pageType,
                        ], key('child-block-' . $child['id']))
                    @empty
                        <p class="py-3 text-center text-xs text-gray-400">No blocks in this column.</p>
                    @endforelse
                </div>
            </div>
            @endfor
        </div>

    </div>
    @endif

    {{-- Child Add Block Modal (column widget only) --}}
    @if ($showChildAddModal)
        <x-widget-picker-modal
            :widget-types="$widgetTypes"
            :title="'Add Block — Column ' . (($childAddColumn ?? 0) + 1)"
            show-property="showChildAddModal"
            create-action="$wire.createChildBlock"
        />
    @endif


</div>
