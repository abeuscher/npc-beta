@props([
    'widgetTypes',
    'title' => 'Add Block',
    'showProperty',
    'createAction',
])

@php
    $categoryLabels = \App\Filament\Resources\WidgetTypeResource::CATEGORY_OPTIONS;
    $categoryOrder = array_keys($categoryLabels);
    $activeCategories = collect($widgetTypes)
        ->flatMap(fn ($wt) => $wt['category'] ?? ['content'])
        ->unique()
        ->values();
    $hasMostUsed = $activeCategories->contains('most_used');
@endphp

@teleport('body')
    <div
        x-data="widgetPickerModal(@js($hasMostUsed ? 'most_used' : ''))"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
        x-on:keydown.escape.window="if (filter !== '') { filter = ''; activeCategory = ''; } else { $wire.set('{{ $showProperty }}', false); }"
    >
        <div class="container mx-auto rounded-xl bg-white shadow-xl dark:bg-gray-900 flex flex-col" style="height: 90vh;">
            {{-- Header with close button --}}
            <div class="flex items-center justify-between px-6 pt-5">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $title }}</h3>
                <button
                    type="button"
                    x-on:click="$wire.set('{{ $showProperty }}', false)"
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
                            x-on:click="if (picked) return; picked = true; {{ $createAction }}('{{ $wt['id'] }}')"
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
