<x-filament-panels::page>
    {{-- Tab navigation --}}
    <div class="flex gap-2 border-b border-gray-200 dark:border-white/10 mb-6">
        <button
            wire:click="switchTab('buttons')"
            @class([
                'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition',
                'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' => $activeTab === 'buttons',
                'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' => $activeTab !== 'buttons',
            ])
        >
            Buttons
        </button>
        <button
            wire:click="switchTab('text-styles')"
            @class([
                'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition',
                'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' => $activeTab === 'text-styles',
                'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' => $activeTab !== 'text-styles',
            ])
        >
            Text Styles
        </button>
    </div>

    {{-- Buttons tab --}}
    @if ($activeTab === 'buttons')
        <x-filament-panels::form wire:submit="save">
            {{ $this->form }}

            <x-filament-panels::form.actions
                :actions="$this->getFormActions()"
            />
        </x-filament-panels::form>
    @endif

    {{-- Text Styles tab (placeholder) --}}
    @if ($activeTab === 'text-styles')
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <h3 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">Text Styles</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                This tab will provide controls for site-wide typography settings including heading styles,
                body text, link styles, and pager styles. Coming in a future session.
            </p>
        </div>
    @endif
</x-filament-panels::page>
