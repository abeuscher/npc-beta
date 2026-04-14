<x-filament-panels::page>
    @php
        $__fontNames = \App\Services\TypographyResolver::googleFontNames();
        $__fontsHref = 'https://fonts.googleapis.com/css2?' . collect($__fontNames)
            ->map(fn ($f) => 'family=' . str_replace(' ', '+', $f) . ':wght@100;200;300;400;500;600;700;800;900')
            ->implode('&') . '&display=swap';
    @endphp
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="{{ $__fontsHref }}">

    @php($returnTo = $this->getReturnToUrl())
    @if ($returnTo)
        <div class="mb-4">
            <a href="{{ $returnTo }}" class="text-sm text-primary-600 hover:text-primary-500 dark:text-primary-400">
                ← Back to editor
            </a>
        </div>
    @endif

    {{-- Tab navigation --}}
    <div class="flex gap-2 border-b border-gray-200 dark:border-white/10 mb-6">
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

    {{-- Text Styles tab — Vue typography editor --}}
    @if ($activeTab === 'text-styles')
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div
                data-theme-editor-app
                data-bootstrap='@json($this->getTypographyBootstrap())'
                wire:ignore
            ></div>
            @vite('resources/js/theme-editor/main.ts')
        </div>
    @endif
</x-filament-panels::page>
