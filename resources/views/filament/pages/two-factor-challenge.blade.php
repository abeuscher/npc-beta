<x-filament-panels::page.simple>
    <p class="mb-6 text-sm text-gray-600 dark:text-gray-400">
        Enter the 6-digit code from your authenticator app to continue. Lost your device? Enter one of your
        single-use recovery codes instead.
    </p>

    <x-filament-panels::form wire:submit="authenticate">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>
</x-filament-panels::page.simple>
