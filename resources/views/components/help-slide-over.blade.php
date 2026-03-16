@props(['article' => null])

@if ($article)
    <div
        x-data="{ open: false }"
        x-on:keydown.escape.window="open = false"
    >
        {{-- Trigger button --}}
        <button
            type="button"
            x-on:click="open = true"
            title="Help"
            class="fi-icon-btn relative flex items-center justify-center rounded-lg outline-none transition duration-75 focus-visible:ring-2 h-9 w-9 text-gray-400 hover:text-gray-500 focus-visible:ring-primary-600 dark:text-gray-500 dark:hover:text-gray-400 dark:focus-visible:ring-primary-500"
        >
            <x-heroicon-o-question-mark-circle class="h-5 w-5" />
            <span class="sr-only">Help</span>
        </button>

        {{-- Backdrop --}}
        <div
            x-show="open"
            x-transition:enter="ease-in-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in-out duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:click="open = false"
            class="fixed inset-0 z-40 bg-gray-900/50"
            style="display: none;"
        ></div>

        {{-- Slide-over panel --}}
        <div
            x-show="open"
            x-transition:enter="transform transition ease-in-out duration-300"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transform transition ease-in-out duration-300"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col bg-white shadow-xl dark:bg-gray-900"
            style="display: none;"
        >
            {{-- Header --}}
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                    {{ $article->title }}
                </h2>
                <button
                    type="button"
                    x-on:click="open = false"
                    class="rounded-lg p-1 text-gray-400 hover:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400"
                >
                    <x-heroicon-o-x-mark class="h-5 w-5" />
                    <span class="sr-only">Close help</span>
                </button>
            </div>

            {{-- Content --}}
            <div class="flex-1 overflow-y-auto px-6 py-5">
                <div class="prose prose-sm dark:prose-invert max-w-none">
                    {!! \Illuminate\Support\Str::markdown($article->content) !!}
                </div>
            </div>

            {{-- Footer --}}
            <div class="border-t border-gray-200 px-6 py-3 dark:border-gray-700">
                <p class="text-xs text-gray-400 dark:text-gray-500">
                    Last updated {{ $article->last_updated?->format('F j, Y') }}
                    @if ($article->app_version)
                        &middot; v{{ $article->app_version }}
                    @endif
                </p>
            </div>
        </div>
    </div>
@endif
