<x-filament-panels::page>
    @php $blockedTypes = $this->getBlockedTypes(); @endphp

    <div class="max-w-2xl space-y-6">
        <p class="text-sm text-gray-500">Choose the type of data you want to import.</p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">

            {{-- Import Contacts --}}
            @if (in_array('contact', $blockedTypes))
                <div class="flex flex-col items-center gap-3 rounded-xl border-2 border-gray-200 bg-gray-50 p-6 text-center opacity-60 cursor-not-allowed dark:bg-gray-800 dark:border-gray-700">
                    <x-heroicon-o-users class="h-10 w-10 text-gray-400" />
                    <span class="text-base font-semibold text-gray-400">Import Contacts</span>
                    <span class="text-xs text-gray-400">A previous contact import is awaiting review. Approve or roll it back before starting a new one.
                        @if (auth()->user()?->can('review_imports'))
                            <a href="#review-queue" class="underline text-primary-500">Go to review queue</a>
                        @endif
                    </span>
                </div>
            @else
                <a href="{{ \App\Filament\Pages\ImportContactsPage::getUrl() }}"
                   class="flex flex-col items-center gap-3 rounded-xl border-2 border-primary-300 bg-white p-6 text-center shadow-sm transition hover:border-primary-500 hover:shadow-md dark:bg-gray-900 dark:border-primary-700 dark:hover:border-primary-400">
                    <x-heroicon-o-users class="h-10 w-10 text-primary-500" />
                    <span class="text-base font-semibold">Import Contacts</span>
                </a>
            @endif

            {{-- Import Events (stub) --}}
            <div title="Coming soon"
                 class="flex flex-col items-center gap-3 rounded-xl border-2 border-gray-200 bg-gray-50 p-6 text-center opacity-50 cursor-not-allowed dark:bg-gray-800 dark:border-gray-700">
                <x-heroicon-o-calendar class="h-10 w-10 text-gray-400" />
                <span class="text-base font-semibold text-gray-400">Import Events</span>
                <span class="text-xs text-gray-400">Coming soon</span>
            </div>

            {{-- Import Financial Data (stub) --}}
            <div title="Coming soon"
                 class="flex flex-col items-center gap-3 rounded-xl border-2 border-gray-200 bg-gray-50 p-6 text-center opacity-50 cursor-not-allowed dark:bg-gray-800 dark:border-gray-700">
                <x-heroicon-o-banknotes class="h-10 w-10 text-gray-400" />
                <span class="text-base font-semibold text-gray-400">Import Financial Data</span>
                <span class="text-xs text-gray-400">Coming soon</span>
            </div>

        </div>

        @if (auth()->user()?->can('import_data'))
        <div class="pt-2">
            <a href="{{ \App\Filament\Pages\ImportHistoryPage::getUrl() }}"
               class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                <x-heroicon-o-clock class="h-4 w-4" />
                View Import History
            </a>
        </div>
        @endif
    </div>

    @if (auth()->user()?->can('review_imports'))
    <div id="review-queue" class="mt-10 space-y-4">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Review Queue</h2>
        {{ $this->table }}
    </div>
    @endif
</x-filament-panels::page>
