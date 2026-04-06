<div
    class="page-builder"
    x-data="{
        handlePreviewMessage(e) {
            if (e.data?.type === 'preview-widget-clicked' && e.data.widgetId) {
                $wire.switchToEdit(e.data.widgetId);
            }
        }
    }"
    x-init="window.addEventListener('message', (e) => handlePreviewMessage(e))"
    x-on:keydown.escape.window="
        if ($wire.mode === 'edit' && $wire.selectedBlockId !== '') {
            $wire.selectBlock('');
        }
    "
>

    {{-- Public CSS is loaded via AdminPanelProvider render hook (build server bundle).
         Override body-level .np-site rules that don't apply to inline previews. --}}
    <style>
        .widget-preview-scope.np-site {
            min-height: auto;
            display: block;
        }
        .page-builder-preview-iframe {
            width: 100%;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            min-height: 600px;
            background: white;
        }
    </style>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Toolbar --}}
    {{-- ------------------------------------------------------------------ --}}
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <p class="text-sm text-gray-500">
                {{ count($blocks) }} block(s) on this page.
            </p>

            {{-- Edit / Preview mode toggle --}}
            <div class="inline-flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden">
                <button
                    type="button"
                    wire:click="switchToEdit"
                    class="px-3 py-1.5 text-xs font-medium transition-colors {{ $mode === 'edit' ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300' }}"
                >Edit</button>
                <button
                    type="button"
                    wire:click="switchToPreview"
                    class="px-3 py-1.5 text-xs font-medium transition-colors {{ $mode === 'preview' ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300' }}"
                >Preview</button>
            </div>
        </div>

        <div class="flex items-center gap-2">
            @if ($mode === 'edit')
                <button
                    type="button"
                    x-data
                    x-show="$wire.selectedBlockId !== ''"
                    x-cloak
                    wire:click="selectBlock('')"
                    class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Deselect
                </button>
            @endif
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
    {{-- Preview mode: full-page iframe --}}
    {{-- ------------------------------------------------------------------ --}}
    @if ($mode === 'preview')
        <iframe
            src="{{ route('filament.admin.page-preview', ['page' => $pageId]) }}"
            class="page-builder-preview-iframe"
            style="height: 80vh;"
        ></iframe>
    @endif

    {{-- ------------------------------------------------------------------ --}}
    {{-- Edit mode: block list + inspector --}}
    {{-- ------------------------------------------------------------------ --}}
    @if ($mode === 'edit')
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">

        {{-- ── Left column: structural block list (8/12) ───────────────── --}}
        <div class="min-w-0 space-y-4">

            {{-- Block list                                                      --}}
            {{-- @alpinejs/sort is loaded via AdminPanelProvider and enables     --}}
            {{-- drag-to-reorder via x-sort / x-sort:item / x-sort:handle.      --}}
            <div
                x-data
                x-sort="() => {
                    const ids = [...$el.querySelectorAll('[data-block-id]')].map(e => e.getAttribute('data-block-id'));
                    $wire.updateOrder(ids);
                }"
                class="space-y-2"
            >
                @forelse ($blocks as $index => $block)
                    @livewire('page-builder-block', [
                        'blockId'    => $block['id'],
                        'isFirst'    => $loop->first,
                        'isLast'     => $loop->last,
                        'isRequired' => $block['is_required'],
                        'pageType'   => $pageType,
                    ], key('block-' . $block['id']))
                @empty
                    <div class="rounded-lg border-2 border-dashed border-gray-200 p-8 text-center text-sm text-gray-400 dark:border-gray-700">
                        No blocks yet. Click <strong>+ Add Block</strong> to get started.
                    </div>
                @endforelse
            </div>

        </div>

        {{-- ── Right column: inspector panel (4/12) ──────────────────── --}}
        <div
            class="min-w-0"
            style="position: sticky; top: 1rem; max-height: calc(100vh - 6rem); overflow-y: auto; align-self: flex-start;"
        >
            @livewire('page-builder-inspector', ['blockId' => $selectedBlockId], key('inspector-' . $selectedBlockId))
        </div>

    </div>
    @endif {{-- end edit mode --}}

    {{-- ------------------------------------------------------------------ --}}
    {{-- Add Block Modal                                                      --}}
    {{-- ------------------------------------------------------------------ --}}
    @if ($showAddModal)
    @teleport('body')
        @php
            $categoryLabels = \App\Filament\Resources\WidgetTypeResource::CATEGORY_OPTIONS;
            $categoryOrder = array_keys($categoryLabels);
            // Build set of categories that have at least one widget
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
            x-on:keydown.escape.window="if (filter !== '') { filter = ''; activeCategory = ''; } else { $wire.set('showAddModal', false); }"
        >
            <div class="container mx-auto rounded-xl bg-white shadow-xl dark:bg-gray-900 flex flex-col" style="height: 90vh;">
                {{-- Header with close button --}}
                <div class="flex items-center justify-between px-6 pt-5">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Add Block</h3>
                    <button
                        type="button"
                        x-on:click="$wire.set('showAddModal', false)"
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
                                x-on:click="if (picked) return; picked = true; $wire.createBlock('{{ $wt['id'] }}')"
                                x-show="matchesFilter({{ json_encode($wt['label']) }}, {{ json_encode($wt['description'] ?? '') }}) && matchesCategory({{ json_encode($wt['category'] ?? ['content']) }})"
                                x-bind:class="{ 'opacity-50 cursor-not-allowed pointer-events-none': picked }"
                                class="group flex flex-col items-start rounded-lg border border-gray-200 bg-gray-50 text-left shadow-sm hover:border-primary-400 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-500 transition-colors duration-200 ease-in-out overflow-hidden"
                                style="--hover-bg: #d4d4d4;"
                                x-bind:style="!picked && 'cursor: pointer'"
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
