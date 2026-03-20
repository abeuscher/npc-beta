<x-filament-panels::page>
    <div class="mx-auto max-w-2xl space-y-6">
        <p class="text-sm text-gray-500">Choose the type of data you want to import.</p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">

            {{-- Import Contacts --}}
            <a href="{{ \App\Filament\Pages\ImportContactsPage::getUrl() }}"
               class="flex flex-col items-center gap-3 rounded-xl border-2 border-primary-300 bg-white p-6 text-center shadow-sm transition hover:border-primary-500 hover:shadow-md dark:bg-gray-900 dark:border-primary-700 dark:hover:border-primary-400">
                <x-heroicon-o-users class="h-10 w-10 text-primary-500" />
                <span class="text-base font-semibold">Import Contacts</span>
            </a>

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
    </div>
</x-filament-panels::page>
