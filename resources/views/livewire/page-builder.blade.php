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

    {{-- Public CSS is loaded via AdminPanelProvider render hook (build server bundle).
         Override body-level .np-site rules that don't apply to inline previews. --}}
    <style>
        .widget-preview-scope.np-site {
            min-height: auto;
            display: block;
        }
        .widget-preview-region {
            position: relative;
            cursor: pointer;
            padding: 10px 0;
            border: 2px solid transparent;
            transition: border-color 0.15s ease;
        }
        .widget-preview-region:hover {
            border-color: rgba(99,102,241,0.3);
        }
        .widget-preview-region--selected {
            border-color: rgb(99,102,241) !important;
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
            x-data="{
                presetViewport: 1920,
                zoomFactor: 1,
                libsReady: false,

                computeZoom() {
                    const pane = $el;
                    const paneWidth = pane.offsetWidth;
                    this.zoomFactor = paneWidth > 0 ? Math.min(1, paneWidth / this.presetViewport) : 1;
                },

                setViewport(w) {
                    this.presetViewport = w;
                    this.computeZoom();
                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => this.reinitWidgetAlpine());
                    });
                },

                pinHeights() {
                    const scope = this.$refs.previewScope;
                    if (!scope) return;
                    scope.querySelectorAll('.widget-preview-region').forEach(el => {
                        el.style.minHeight = el.offsetHeight + 'px';
                    });
                },

                unpinHeights() {
                    const scope = this.$refs.previewScope;
                    if (!scope) return;
                    scope.querySelectorAll('.widget-preview-region').forEach(el => {
                        el.style.minHeight = '';
                    });
                },

                async loadLibs() {
                    const libs = @js($requiredLibs);
                    const manifest = window.__widgetLibs || {};
                    const globalChecks = {
                        'swiper': () => !!window.Swiper,
                        'chart.js': () => !!window.Chart,
                        'jcalendar': () => !!window.calendarJs,
                    };

                    const promises = [];

                    for (const lib of libs) {
                        const entry = manifest[lib];
                        if (!entry) continue;

                        // Load CSS — awaited so styles are parsed before Swiper measures containers
                        if (entry.css && !document.querySelector(`link[data-widget-lib='${lib}']`)) {
                            promises.push(new Promise((resolve) => {
                                const link = document.createElement('link');
                                link.rel = 'stylesheet';
                                link.href = entry.css;
                                link.dataset.widgetLib = lib;
                                link.onload = resolve;
                                link.onerror = () => { console.warn('Failed to load widget lib CSS:', lib); resolve(); };
                                document.head.appendChild(link);
                            }));
                        }

                        // Load JS if global not yet available
                        const check = globalChecks[lib];
                        const alreadyLoaded = check ? check() : document.querySelector(`script[data-widget-lib='${lib}']`);
                        if (!alreadyLoaded && entry.js) {
                            promises.push(new Promise((resolve) => {
                                const script = document.createElement('script');
                                script.src = entry.js;
                                script.dataset.widgetLib = lib;
                                script.onload = resolve;
                                script.onerror = () => { console.warn('Failed to load widget lib JS:', lib); resolve(); };
                                document.head.appendChild(script);
                            }));
                        }
                    }

                    await Promise.all(promises);
                    this.libsReady = true;
                },

                reinitWidgetAlpine() {
                    const scope = this.$refs.previewScope;
                    if (!scope) return;

                    // Destroy existing Swiper instances so they re-init cleanly
                    scope.querySelectorAll('.swiper').forEach(el => {
                        if (el.swiper) el.swiper.destroy(true, true);
                    });

                    // Destroy existing Chart.js instances
                    scope.querySelectorAll('canvas').forEach(el => {
                        const chartInstance = window.Chart?.getChart?.(el);
                        if (chartInstance) chartInstance.destroy();
                    });

                    // Lift x-ignore — both the HTML attribute AND Alpine's internal
                    // _x_ignore JS property, which persists even after attribute removal.
                    const ignoreEls = scope.querySelectorAll('[x-ignore]');
                    ignoreEls.forEach(el => {
                        el.removeAttribute('x-ignore');
                        delete el._x_ignore;
                        delete el._x_ignoreSelf;
                        el.setAttribute('data-x-ignore-lifted', '');
                    });

                    // Initialize Alpine trees
                    scope.querySelectorAll('[x-data]').forEach(el => {
                        if (el._x_dataStack) {
                            Alpine.destroyTree(el);
                        }
                        Alpine.initTree(el);
                    });

                    // Restore x-ignore (attribute + internal property)
                    scope.querySelectorAll('[data-x-ignore-lifted]').forEach(el => {
                        el.removeAttribute('data-x-ignore-lifted');
                        el.setAttribute('x-ignore', '');
                        el._x_ignore = true;
                    });

                    // Force Swiper instances to recalculate and unpin heights after layout settles
                    requestAnimationFrame(() => {
                        scope.querySelectorAll('.swiper').forEach(el => {
                            if (el.swiper) el.swiper.update();
                        });
                        this.unpinHeights();
                    });
                },
            }"
            x-init="
                computeZoom();
                await loadLibs();
                // Double rAF: first ensures zoom styles are applied, second ensures layout is complete
                requestAnimationFrame(() => {
                    computeZoom();
                    requestAnimationFrame(() => reinitWidgetAlpine());
                });

                // No morph hook needed — preview uses wire:ignore and updates
                // are pushed via preview-content-changed event.
            "
            x-on:resize.window.debounce.150ms="computeZoom()"
            x-on:preview-content-changed.window="
                const blocks = $event.detail.blocks || $event.detail[0]?.blocks || [];
                const scope = $refs.previewScope;
                if (!scope || !blocks.length) return;

                pinHeights();

                // Replace preview HTML
                let html = '';
                for (const b of blocks) {
                    html += `<div class='widget-preview-region' data-widget-id='${b.id}'><div x-ignore>${b.html}</div></div>`;
                }
                scope.innerHTML = html;

                // Re-attach click handlers and selection classes
                scope.querySelectorAll('.widget-preview-region').forEach(el => {
                    const wid = el.dataset.widgetId;
                    el.addEventListener('click', (e) => {
                        e.stopPropagation();
                        selectedBlockId = wid;
                        $wire.selectBlock(wid);
                    });
                    if (selectedBlockId === wid) {
                        el.classList.add('widget-preview-region--selected');
                    }
                });

                // Init Alpine trees in new content
                await loadLibs();
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        reinitWidgetAlpine();
                    });
                });
            "
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
                class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white"
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
