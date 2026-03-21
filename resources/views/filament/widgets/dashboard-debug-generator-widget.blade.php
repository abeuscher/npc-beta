<x-filament-widgets::widget>
    <x-filament::section>
        <div class="space-y-4">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center rounded-md bg-warning-50 dark:bg-warning-400/10 px-2 py-1 text-xs font-semibold text-warning-700 dark:text-warning-400 ring-1 ring-inset ring-warning-600/20">
                    ⚠ DEBUG TOOLS
                </span>
                <span class="text-xs text-gray-400">Only visible when <code class="font-mono">APP_DEBUG_TOOLS=true</code>. Remove before go-live.</span>
            </div>

            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Record type</label>
                    <select
                        wire:model="type"
                        class="block rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white px-3 py-1.5 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                    >
                        <option value="contacts">Contacts</option>
                        <option value="events">Events</option>
                        <option value="donations">Donations</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Quantity</label>
                    <input
                        type="number"
                        wire:model="quantity"
                        min="1"
                        max="200"
                        class="block w-24 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white px-3 py-1.5 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                    >
                </div>

                <x-filament::button
                    wire:click="generate"
                    wire:loading.attr="disabled"
                    size="sm"
                >
                    Generate
                </x-filament::button>

                <x-filament::button
                    wire:click="wipe"
                    wire:confirm="This will hard-delete ALL records of the selected type from the database. This cannot be undone. Are you sure?"
                    wire:loading.attr="disabled"
                    color="danger"
                    size="sm"
                >
                    Wipe all
                </x-filament::button>
            </div>

            @if ($feedback)
                <p class="text-sm font-medium text-success-600 dark:text-success-400">{{ $feedback }}</p>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
