<x-filament-panels::page>
    {{-- Filter bar --}}
    <div class="flex flex-wrap items-end gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:bg-gray-900 dark:border-gray-700">

        {{-- Year dropdown --}}
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                Tax Year
            </label>
            <select wire:model.live="taxYear"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                @foreach ($this->getYearOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        {{-- Minimum threshold --}}
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                Minimum Total
            </label>
            <div class="relative flex items-center">
                <span class="pointer-events-none absolute left-3 text-sm text-gray-400 dark:text-gray-500">$</span>
                <input type="number"
                       wire:model.live.debounce.500ms="minimumTotal"
                       min="0"
                       step="0.01"
                       placeholder="250.00"
                       class="rounded-lg border border-gray-300 bg-white py-2 pl-7 pr-3 text-sm shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200 w-36">
            </div>
        </div>

        {{-- Include below threshold toggle --}}
        <div class="flex items-center gap-2 pb-1">
            <input type="checkbox"
                   wire:model.live="includeBelowThreshold"
                   id="include_below"
                   class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600">
            <label for="include_below" class="text-sm text-gray-600 dark:text-gray-300 cursor-pointer select-none">
                Include donors below threshold
            </label>
        </div>

    </div>

    {{-- Table --}}
    {{ $this->table }}

    <x-filament-actions::modals />
</x-filament-panels::page>
