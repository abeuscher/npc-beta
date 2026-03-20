<x-filament-panels::page>
    <div class="mx-auto max-w-2xl space-y-6">

        @if ($done)

            @if ($rejected)
                {{-- ── PII rejection card ── --}}
                <div class="rounded-xl border border-red-200 bg-red-50 p-6 dark:border-red-800 dark:bg-red-950">
                    <div class="flex items-center gap-3">
                        <x-heroicon-o-shield-exclamation class="h-7 w-7 text-red-600 dark:text-red-400" />
                        <h2 class="text-lg font-semibold text-red-800 dark:text-red-300">Import rejected</h2>
                    </div>
                    <p class="mt-2 text-sm text-red-700 dark:text-red-400">
                        This import was rejected because it contains data that may include sensitive financial or personal identifiers. Remove the flagged data and try again.
                    </p>
                    @if ($rejectionReason)
                        <p class="mt-3 rounded bg-red-100 px-3 py-2 font-mono text-xs text-red-800 dark:bg-red-900 dark:text-red-300">
                            {{ $rejectionReason }}
                        </p>
                    @endif
                </div>

                <div class="flex gap-3">
                    <a href="{{ \App\Filament\Pages\ImporterPage::getUrl() }}"
                       class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500">
                        <x-heroicon-o-arrow-up-tray class="h-4 w-4" />
                        Start a new import
                    </a>
                </div>

            @else

                @if ($importSessionId)
                    {{-- ── Pending review card ── --}}
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 dark:border-amber-800 dark:bg-amber-950">
                        <div class="flex items-center gap-3">
                            <x-heroicon-o-clock class="h-7 w-7 text-amber-600 dark:text-amber-400" />
                            <h2 class="text-lg font-semibold text-amber-800 dark:text-amber-300">Import pending review</h2>
                        </div>
                        <p class="mt-2 text-sm text-amber-700 dark:text-amber-400">
                            The import has been processed and is waiting for a reviewer to approve it before contacts become visible.
                        </p>
                    </div>
                @else
                    {{-- ── Legacy completion card (no session) ── --}}
                    <div class="rounded-xl border border-green-200 bg-green-50 p-6 dark:border-green-800 dark:bg-green-950">
                        <div class="flex items-center gap-3">
                            <x-heroicon-o-check-circle class="h-7 w-7 text-green-600 dark:text-green-400" />
                            <h2 class="text-lg font-semibold text-green-800 dark:text-green-300">Import complete</h2>
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <p class="text-2xl font-bold text-green-600">{{ number_format($imported) }}</p>
                        <p class="mt-1 text-sm text-gray-500">Imported</p>
                    </div>
                    <div class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <p class="text-2xl font-bold text-blue-600">{{ number_format($updated) }}</p>
                        <p class="mt-1 text-sm text-gray-500">Staged</p>
                    </div>
                    <div class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <p class="text-2xl font-bold text-amber-500">{{ number_format($skipped) }}</p>
                        <p class="mt-1 text-sm text-gray-500">Skipped</p>
                    </div>
                    <div class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <p class="text-2xl font-bold {{ $errorCount > 0 ? 'text-red-600' : 'text-gray-400' }}">
                            {{ number_format($errorCount) }}
                        </p>
                        <p class="mt-1 text-sm text-gray-500">Errors</p>
                    </div>
                </div>

                @if (!empty($customFieldLog))
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-5 dark:border-blue-800 dark:bg-blue-950">
                    <h3 class="text-sm font-semibold text-blue-800 dark:text-blue-300 mb-3">Custom Field Definitions</h3>
                    <ul class="space-y-1 text-sm">
                        @foreach ($customFieldLog as $entry)
                        <li class="flex items-center gap-2">
                            @if ($entry['action'] === 'created')
                                <x-heroicon-o-plus-circle class="h-4 w-4 text-green-600 shrink-0" />
                                <span><strong>{{ $entry['label'] }}</strong> <span class="font-mono text-xs text-gray-500">({{ $entry['handle'] }})</span> — created new field</span>
                            @else
                                <x-heroicon-o-arrow-path class="h-4 w-4 text-blue-500 shrink-0" />
                                <span><strong>{{ $entry['label'] }}</strong> <span class="font-mono text-xs text-gray-500">({{ $entry['handle'] }})</span> — reused existing field</span>
                            @endif
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <div class="flex gap-3">
                    @if ($importSessionId && auth()->user()?->can('review_imports'))
                        <a href="{{ \App\Filament\Pages\ImporterPage::getUrl() }}"
                           class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500">
                            <x-heroicon-o-clipboard-document-check class="h-4 w-4" />
                            Go to review queue
                        </a>
                    @elseif ($importSessionId)
                        <p class="text-sm text-gray-500 self-center">A reviewer will approve this import before contacts appear in the system.</p>
                    @else
                        <a href="{{ \App\Filament\Pages\ImportHistoryPage::getUrl() }}"
                           class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500">
                            <x-heroicon-o-clock class="h-4 w-4" />
                            View import history
                        </a>
                        <a href="{{ \App\Filament\Resources\ContactResource::getUrl('index') }}"
                           class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-800">
                            <x-heroicon-o-users class="h-4 w-4" />
                            Go to contacts
                        </a>
                    @endif
                </div>

            @endif

        @else

            {{-- ── In-progress card, polls every 500ms ── --}}
            <div wire:poll.500ms="tick" class="space-y-5">

                <div class="flex items-center justify-between text-sm text-gray-500">
                    <span>Processing row {{ number_format($processed) }} of {{ number_format($total) }}</span>
                    <span class="font-medium">{{ $this->percent() }}%</span>
                </div>

                {{-- Progress bar --}}
                <div class="h-3 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                    <div class="h-3 rounded-full bg-primary-500 transition-all duration-500"
                         style="width: {{ $this->percent() }}%">
                    </div>
                </div>

                {{-- Running counters --}}
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <p class="text-2xl font-bold text-green-600">{{ number_format($imported) }}</p>
                        <p class="mt-1 text-sm text-gray-500">Imported</p>
                    </div>
                    <div class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <p class="text-2xl font-bold text-blue-600">{{ number_format($updated) }}</p>
                        <p class="mt-1 text-sm text-gray-500">Staged</p>
                    </div>
                    <div class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <p class="text-2xl font-bold text-amber-500">{{ number_format($skipped) }}</p>
                        <p class="mt-1 text-sm text-gray-500">Skipped</p>
                    </div>
                    <div class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <p class="text-2xl font-bold {{ $errorCount > 0 ? 'text-red-600' : 'text-gray-400' }}">
                            {{ number_format($errorCount) }}
                        </p>
                        <p class="mt-1 text-sm text-gray-500">Errors</p>
                    </div>
                </div>

                <p class="text-center text-xs text-gray-400">
                    Please stay on this page until the import finishes.
                </p>

            </div>

        @endif

    </div>
</x-filament-panels::page>
