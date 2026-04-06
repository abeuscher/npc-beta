<div
    wire:key="block-{{ $block['id'] }}"
    x-sort:item="'{{ $block['id'] }}'"
    data-block-id="{{ $block['id'] }}"
    x-data="{
        open: {{ $block['widget_type_default_open'] ? 'true' : 'false' }},
        menuOpen: false,
        confirmDelete: false,
        selected: false,
        anySelected: false,
        childIsSelected: false,
    }"
    x-on:block-selected.window="
        selected = ($event.detail.blockId === '{{ $block['id'] }}');
        anySelected = ($event.detail.blockId !== '');
        childIsSelected = ($event.detail.parentBlockId === '{{ $block['id'] }}');
        if (childIsSelected) open = true;
    "
    x-bind:class="{
        'ring-2 ring-primary-500 widget-block--focused': selected,
        'widget-block--blurred': anySelected && !selected && !childIsSelected,
    }"
    x-bind:style="selected
        ? 'zoom: 1.1; transition: zoom 0.25s ease, filter 0.25s ease, opacity 0.25s ease;'
        : (anySelected && !childIsSelected
            ? 'filter: blur(4px); opacity: 0.45; transition: zoom 0.25s ease, filter 0.25s ease, opacity 0.25s ease; cursor: pointer;'
            : 'transition: zoom 0.25s ease, filter 0.25s ease, opacity 0.25s ease;'
        )
    "
    x-on:click="if (anySelected && !selected && !childIsSelected) { $event.stopPropagation(); $wire.selectSelf(); }"
    class="rounded-lg border shadow-sm {{ $block['widget_type_handle'] === 'column_widget' ? 'border-gray-300 bg-[#cccccc] dark:bg-gray-700' : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800' }}"
>
    {{-- Block header — pointer-events disabled when a sibling is focused --}}
    <div
        class="flex items-center gap-2 px-3 py-2"
        x-bind:style="(anySelected && !selected && !childIsSelected) ? 'pointer-events: none;' : ''"
    >

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

    {{-- Live widget preview — shown when block is focused (selected) --}}
    @if ($block['widget_type_handle'] !== 'column_widget')
    <div
        x-show="selected"
        x-cloak
        x-ref="previewFrame"
        x-data="{
            zoomFactor: 0.5,
            viewportW: 1920,
            computeZoom() {
                this.viewportW = window.innerWidth;
                // Measure the parent block element (always visible) instead of this
                // hidden x-show div which has 0 width until displayed.
                const block = this.$refs.previewFrame.closest('[data-block-id]');
                const panelWidth = block ? block.offsetWidth : 0;
                this.zoomFactor = panelWidth > 0 ? panelWidth / this.viewportW : 0.5;
                console.log('preview zoom:', { panelWidth, viewportW: this.viewportW, zoomFactor: this.zoomFactor });
            },
        }"
        x-init="$nextTick(() => computeZoom())"
        x-on:resize.window.debounce.150ms="computeZoom()"
        class="widget-preview-frame border-t border-gray-100 dark:border-gray-700"
        style="overflow: hidden; position: relative;"
    >
        @if ($previewHtml)
            {{-- Inner container: fixed pixel width matching viewport, zoom shrinks to fit panel --}}
            <div
                class="widget-preview-scope"
                x-bind:style="'width: ' + viewportW + 'px; zoom: ' + zoomFactor + ';'"
            >
                {!! $previewHtml !!}
            </div>
        @elseif ($isSelected)
            <div class="p-4 text-center text-sm text-gray-400">
                No preview available for this widget.
            </div>
        @endif
    </div>
    @endif

    {{-- Column widget slot panels — the only body content for block components --}}
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
    @teleport('body')
        @php
            $categoryLabels = \App\Filament\Resources\WidgetTypeResource::CATEGORY_OPTIONS;
            $categoryOrder = array_keys($categoryLabels);
            $activeCategories = collect($widgetTypes)
                ->flatMap(fn ($wt) => $wt['category'] ?? ['content'])
                ->unique()
                ->values();
        @endphp
        <div
            x-data="{
                picked: false,
                filter: '',
                activeCategory: '',
                matchesFilter(label, desc) {
                    if (this.filter === '') return true;
                    const q = this.filter.toLowerCase();
                    return label.toLowerCase().includes(q) || (desc && desc.toLowerCase().includes(q));
                },
                matchesCategory(cats) {
                    if (this.activeCategory === '') return true;
                    return cats.includes(this.activeCategory);
                },
            }"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            x-on:keydown.escape.window="if (filter !== '') { filter = ''; activeCategory = ''; } else { $wire.set('showChildAddModal', false); }"
        >
            <div class="container mx-auto rounded-xl bg-white shadow-xl dark:bg-gray-900 flex flex-col" style="max-height: 90vh;">
                {{-- Header with close button --}}
                <div class="flex items-center justify-between px-6 pt-5 pb-0">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                        Add Block — Column {{ ($childAddColumn ?? 0) + 1 }}
                    </h3>
                    <button
                        type="button"
                        x-on:click="$wire.set('showChildAddModal', false)"
                        class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Filter input --}}
                <div class="px-6 pt-4 pb-3">
                    <input
                        type="text"
                        x-model="filter"
                        placeholder="Search widgets by name or description…"
                        autofocus
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                    >
                </div>

                {{-- Category filter toolbar --}}
                <div class="flex flex-wrap gap-1.5 px-6 pb-3">
                    <button
                        type="button"
                        x-on:click="activeCategory = ''"
                        x-bind:class="activeCategory === '' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'"
                        class="rounded-full px-3 py-1 text-xs font-medium transition-colors"
                    >All</button>
                    @foreach ($categoryOrder as $cat)
                        @if ($activeCategories->contains($cat))
                        <button
                            type="button"
                            x-on:click="activeCategory = activeCategory === '{{ $cat }}' ? '' : '{{ $cat }}'"
                            x-bind:class="activeCategory === '{{ $cat }}' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'"
                            class="rounded-full px-3 py-1 text-xs font-medium transition-colors"
                        >{{ $categoryLabels[$cat] }}</button>
                        @endif
                    @endforeach
                </div>

                {{-- Widget tile grid --}}
                <div class="overflow-y-auto px-6 pb-5 flex-1 min-h-0">
                    <div class="grid grid-cols-6 gap-3">
                        @foreach ($widgetTypes as $wt)
                            <button
                                type="button"
                                x-on:click="if (picked) return; picked = true; $wire.createChildBlock('{{ $wt['id'] }}')"
                                x-show="matchesFilter({{ json_encode($wt['label']) }}, {{ json_encode($wt['description'] ?? '') }}) && matchesCategory({{ json_encode($wt['category'] ?? ['content']) }})"
                                x-bind:class="{ 'opacity-50 cursor-not-allowed pointer-events-none': picked }"
                                class="group flex flex-col items-start rounded-lg border border-gray-200 bg-gray-50 text-left shadow-sm hover:border-primary-400 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-500 transition-colors duration-200 ease-in-out overflow-hidden"
                                @mouseenter="$el.style.backgroundColor = '#d4d4d4'"
                                @mouseleave="$el.style.backgroundColor = ''"
                            >
                                {{-- Thumbnail area --}}
                                <div class="relative w-full overflow-hidden bg-gray-200 dark:bg-gray-700" style="aspect-ratio: 16/9;">
                                    @if ($wt['thumbnail'])
                                        <img
                                            src="{{ $wt['thumbnail'] }}"
                                            alt=""
                                            class="absolute inset-0 h-full w-full object-cover {{ $wt['thumbnail_hover'] ? 'group-hover:opacity-0' : '' }} transition-opacity duration-200 ease-in-out"
                                        >
                                        @if ($wt['thumbnail_hover'])
                                        <img
                                            src="{{ $wt['thumbnail_hover'] }}"
                                            alt=""
                                            class="absolute inset-0 h-full w-full object-cover opacity-0 group-hover:opacity-100 transition-opacity duration-200 ease-in-out"
                                        >
                                        @endif
                                    @else
                                        <div class="flex h-full items-center justify-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2 1m2-1l-2-1m2 1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21l-2-1m2 1l2-1m-2 1v-2.5M6 18l-2-1v-2.5M18 18l2-1v-2.5"/>
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                                {{-- Label and description --}}
                                <div class="px-3 py-2 w-full">
                                    <span class="font-medium text-gray-900 dark:text-white text-sm leading-tight block">{{ $wt['label'] }}</span>
                                    @if ($wt['description'] ?? false)
                                        <span class="mt-0.5 text-xs text-gray-400 dark:text-gray-500 leading-tight line-clamp-2 block">{{ $wt['description'] }}</span>
                                    @endif
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endteleport
    @endif

</div>
