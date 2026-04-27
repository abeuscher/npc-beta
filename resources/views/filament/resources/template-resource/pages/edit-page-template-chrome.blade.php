<x-filament-panels::page>
    @if ($this->isNonDefault)
        @if ($this->hasCustomChrome)
            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Custom {{ $this->chromePosition }} for this template. Widget changes are saved automatically.
                </p>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        wire:click="inheritChrome"
                        wire:confirm="Reset to the default {{ $this->chromePosition }}? Your custom {{ $this->chromePosition }} widgets will be orphaned."
                        class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        Inherit from Default
                    </button>
                    <button
                        type="button"
                        @click="document.activeElement?.blur()"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500"
                    >
                        Save
                    </button>
                </div>
            </div>

            @livewire('page-builder', ['pageId' => $this->activeChromePageId], key('tmpl-' . $this->chromePosition . '-' . $this->activeChromePageId))
        @else
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ ucfirst($this->chromePosition) }} is inherited from the Default template.
                </p>
                <button
                    type="button"
                    wire:click="enableCustomChrome"
                    class="mt-3 inline-flex items-center gap-1 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500"
                >
                    Use Custom {{ ucfirst($this->chromePosition) }}
                </button>
            </div>
        @endif
    @else
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Widget changes are saved automatically as you edit.
            </p>
            <button
                type="button"
                @click="document.activeElement?.blur()"
                class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500"
            >
                Save
            </button>
        </div>

        @if ($this->activeChromePageId)
            @livewire('page-builder', ['pageId' => $this->activeChromePageId], key('tmpl-' . $this->chromePosition))
        @else
            <div class="rounded-lg border-2 border-dashed border-gray-200 p-8 text-center text-sm text-gray-400 dark:border-gray-700">
                No {{ $this->chromePosition }} page found. Run the system page seeder to create the _{{ $this->chromePosition }} page.
            </div>
        @endif
    @endif
</x-filament-panels::page>
