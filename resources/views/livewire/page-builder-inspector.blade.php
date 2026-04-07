<div class="page-builder-inspector h-full">

    @if ($blockId === '' || empty($block))

        {{-- Placeholder when nothing is selected --}}
        <div class="flex h-full min-h-[200px] items-center justify-center rounded-lg border-2 border-dashed border-gray-200 p-6 text-center text-sm text-gray-400 dark:border-gray-700">
            Select a block to edit its settings.
        </div>

    @else

        <div class="space-y-0" x-data="{ activeTab: 'content' }">

            {{-- Block label (edit-in-place) — always visible above tabs --}}
            <div
                x-data="{ editing: false, draft: @js($block['label']) }"
                class="rounded-t-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800"
            >
                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ $block['widget_type_label'] }}
                </p>

                <template x-if="!editing">
                    <div class="flex items-center gap-2">
                        <span class="flex-1 text-sm font-medium text-gray-800 dark:text-gray-100">
                            {{ $block['label'] }}
                        </span>
                        <button
                            type="button"
                            x-on:click="draft = @js($block['label']); editing = true"
                            title="Rename block"
                            class="flex-shrink-0 rounded p-1 text-gray-300 hover:bg-gray-100 hover:text-gray-600 dark:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.364-6.364a2 2 0 112.828 2.828L11.828 15.828a2 2 0 01-1.414.586H9v-2.414a2 2 0 01.586-1.414z"/>
                            </svg>
                        </button>
                    </div>
                </template>

                <template x-if="editing">
                    <div class="flex items-center gap-1.5">
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
                            x-on:click="$wire.set('block.label', draft); editing = false"
                            class="flex-shrink-0 rounded bg-primary-600 px-2 py-1 text-xs font-semibold text-white hover:bg-primary-500"
                        >OK</button>
                        <button
                            type="button"
                            x-on:click="editing = false"
                            class="flex-shrink-0 rounded px-2 py-1 text-xs text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700"
                        >Cancel</button>
                    </div>
                </template>
            </div>

            {{-- Tab bar --}}
            <div class="flex border-x border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900">
                <button
                    type="button"
                    x-on:click="activeTab = 'content'"
                    x-bind:class="activeTab === 'content'
                        ? 'bg-white dark:bg-gray-800 opacity-100 border-b-white dark:border-b-gray-800'
                        : 'bg-transparent opacity-60 border-b-gray-200 dark:border-b-gray-700 hover:opacity-80'"
                    class="relative rounded-t-md border border-gray-200 dark:border-gray-700 -mb-px px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700 dark:text-gray-300 transition-all duration-150 ml-1 mt-1"
                >
                    Content
                </button>
                <button
                    type="button"
                    x-on:click="activeTab = 'appearance'"
                    x-bind:class="activeTab === 'appearance'
                        ? 'bg-white dark:bg-gray-800 opacity-100 border-b-white dark:border-b-gray-800'
                        : 'bg-transparent opacity-60 border-b-gray-200 dark:border-b-gray-700 hover:opacity-80'"
                    class="relative rounded-t-md border border-gray-200 dark:border-gray-700 -mb-px px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700 dark:text-gray-300 transition-all duration-150 ml-1 mt-1"
                >
                    Appearance
                </button>
            </div>

            {{-- Tab content container --}}
            <div class="rounded-b-lg border border-t-0 border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">

                {{-- ── Content tab ────────────────────────────────────── --}}
                <div x-show="activeTab === 'content'" x-cloak class="p-4 space-y-4">
                    @php
                        $contentFields = array_filter($block['widget_type_config_schema'] ?? [], fn ($f) => ($f['group'] ?? 'content') === 'content');
                        $contentPrimary = array_filter($contentFields, fn ($f) => empty($f['advanced']));
                        $contentAdvanced = array_filter($contentFields, fn ($f) => !empty($f['advanced']));
                    @endphp

                    @include('livewire.partials.inspector-field-group', [
                        'primaryFields'  => $contentPrimary,
                        'advancedFields' => $contentAdvanced,
                        'emptyMessage'   => 'No content settings for this widget.',
                    ])
                </div>

                {{-- ── Appearance tab ─────────────────────────────────── --}}
                <div x-show="activeTab === 'appearance'" x-cloak class="p-4 space-y-4">
                    @php
                        $appearanceFields = array_filter($block['widget_type_config_schema'] ?? [], fn ($f) => ($f['group'] ?? 'content') === 'appearance');
                        $appearancePrimary = array_filter($appearanceFields, fn ($f) => empty($f['advanced']));
                        $appearanceAdvanced = array_filter($appearanceFields, fn ($f) => !empty($f['advanced']));
                    @endphp

                    @include('livewire.partials.inspector-field-group', [
                        'primaryFields'  => $appearancePrimary,
                        'advancedFields' => $appearanceAdvanced,
                    ])

                    {{-- Query Settings --}}
                    @if (! empty($block['widget_type_collections']))
                        <div x-data="{ qOpen: false }">
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

                    {{-- Spacing panel (style_config) --}}
                    <div
                        x-data="spacingControls(@entangle('block.style_config').live)"
                    >
                        <button
                            type="button"
                            x-on:click="spOpen = !spOpen"
                            class="flex items-center gap-1.5 text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-3.5 w-3.5 transition-transform duration-150"
                                x-bind:class="{ 'rotate-90': spOpen }"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                            Spacing & Layout
                        </button>

                        <div x-show="spOpen" x-cloak class="mt-3 space-y-4">
                            {{-- Full width --}}
                            <label class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    wire:model.live="block.style_config.full_width"
                                    class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500"
                                >
                                <span class="text-sm text-gray-700 dark:text-gray-300">Full width</span>
                            </label>

                            {{-- Padding --}}
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Padding (px)</p>
                                <div class="grid grid-cols-[--cols-default] gap-2" style="--cols-default:repeat(5,minmax(0,1fr))">
                                    <div>
                                        <label class="block text-center text-xs text-gray-400 mb-1">All</label>
                                        <input type="number" min="0" x-model="paddingAll" x-bind:placeholder="paddingAllPlaceholder" class="w-full rounded border border-gray-300 bg-white px-1.5 py-1 text-sm text-center dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                                    </div>
                                    @foreach (['padding_left' => 'Left', 'padding_top' => 'Top', 'padding_right' => 'Right', 'padding_bottom' => 'Bottom'] as $key => $label)
                                    <div>
                                        <label class="block text-center text-xs text-gray-400 mb-1">{{ $label }}</label>
                                        <input type="number" min="0" wire:model.live="block.style_config.{{ $key }}" class="w-full rounded border border-gray-300 bg-white px-1.5 py-1 text-sm text-center dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                                    </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Margin --}}
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Margin (px)</p>
                                <div class="grid grid-cols-[--cols-default] gap-2" style="--cols-default:repeat(5,minmax(0,1fr))">
                                    <div>
                                        <label class="block text-center text-xs text-gray-400 mb-1">All</label>
                                        <input type="number" min="0" x-model="marginAll" x-bind:placeholder="marginAllPlaceholder" class="w-full rounded border border-gray-300 bg-white px-1.5 py-1 text-sm text-center dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                                    </div>
                                    @foreach (['margin_left' => 'Left', 'margin_top' => 'Top', 'margin_right' => 'Right', 'margin_bottom' => 'Bottom'] as $key => $label)
                                    <div>
                                        <label class="block text-center text-xs text-gray-400 mb-1">{{ $label }}</label>
                                        <input type="number" min="0" wire:model.live="block.style_config.{{ $key }}" class="w-full rounded border border-gray-300 bg-white px-1.5 py-1 text-sm text-center dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>{{-- end tab content container --}}

            {{-- Apply Changes — sticky bottom of inspector scroll area --}}
            <div class="sticky bottom-0 z-10 border-t border-gray-200 bg-white/95 px-4 py-2 backdrop-blur-sm dark:border-gray-700 dark:bg-gray-800/95">
                <button
                    type="button"
                    wire:click="applyChanges"
                    class="w-full rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus:outline-none transition-colors"
                >
                    Apply Changes
                </button>
            </div>

        </div>

    @endif

</div>
