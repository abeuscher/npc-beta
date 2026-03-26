<x-filament-panels::page>
    <div class="max-w-2xl space-y-6">

        {{ $this->form }}

        <div>
            <x-filament::button wire:click="loadSummary" color="gray">
                Load Summary
            </x-filament::button>
        </div>

        @if ($this->summaryLoaded)
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:bg-gray-900 dark:border-gray-700 space-y-4">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                Summary — {{ $this->data['tax_year'] ?? '' }} tax year
            </h2>

            <dl class="grid grid-cols-3 gap-4 text-center">
                <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                    <dt class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Eligible donors</dt>
                    <dd class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $this->eligibleCount }}</dd>
                </div>
                <div class="rounded-lg bg-green-50 dark:bg-green-900/20 p-4">
                    <dt class="text-xs text-green-600 dark:text-green-400 uppercase tracking-wide">Already receipted</dt>
                    <dd class="mt-1 text-2xl font-bold text-green-700 dark:text-green-300">{{ $this->receiptedCount }}</dd>
                </div>
                <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 p-4">
                    <dt class="text-xs text-amber-600 dark:text-amber-400 uppercase tracking-wide">Pending</dt>
                    <dd class="mt-1 text-2xl font-bold text-amber-700 dark:text-amber-300">{{ $this->pendingCount }}</dd>
                </div>
            </dl>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                Use the buttons in the top-right to send receipts to pending donors, or force re-send to all eligible donors.
            </p>
        </div>
        @endif

    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
