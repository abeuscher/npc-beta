<div class="page-builder">

    {{-- ------------------------------------------------------------------ --}}
    {{-- Two-column layout: block list (left ~55%) + inspector (right ~45%) --}}
    {{-- ------------------------------------------------------------------ --}}
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">

        {{-- ── Left column: structural block list (8/12) ───────────────── --}}
        <div class="min-w-0 space-y-4">

            {{-- Header --}}
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

    {{-- ------------------------------------------------------------------ --}}
    {{-- Add Block Modal                                                      --}}
    {{-- ------------------------------------------------------------------ --}}
    @if ($showAddModal)
    @teleport('body')
        <div
            x-data="{ picked: false }"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            x-on:keydown.escape.window="$wire.set('showAddModal', false)"
        >
            <div class="container mx-auto rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900">
                <h3 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">Add Block</h3>

                {{-- Block label --}}
                <div class="mb-5">
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Block Label
                    </label>
                    <input
                        type="text"
                        wire:model="addModalLabel"
                        placeholder="e.g. Hero, About Us intro, News feed…"
                        autofocus
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                    >
                    <p class="mt-1 text-xs text-gray-400">Optional. If blank, auto-named by type (e.g. "Text Block 1").</p>
                </div>

                {{-- Widget type grid --}}
                <p class="mb-2 text-xs font-medium text-gray-500 uppercase tracking-wide">Choose a block type</p>
                <div class="overflow-y-auto rounded-lg" style="max-height: 50vh;">
                    <div style="--cols-default: repeat(3, minmax(0, 1fr)); gap: 0.75rem;" class="grid grid-cols-[--cols-default]">
                        @foreach ($widgetTypes as $wt)
                            <button
                                type="button"
                                x-on:click="if (picked) return; picked = true; $wire.createBlock('{{ $wt['id'] }}')"
                                x-bind:class="{ 'opacity-50 cursor-not-allowed': picked }"
                                class="flex flex-col items-start rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-left shadow-sm hover:border-primary-400 hover:bg-primary-50 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-500 dark:hover:bg-gray-700"
                            >
                                <span class="font-medium text-gray-900 dark:text-white text-sm">{{ $wt['label'] }}</span>
                                <span class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">{{ $wt['handle'] }}</span>
                            </button>
                        @endforeach
                    </div>
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
    @endteleport
    @endif

</div>
