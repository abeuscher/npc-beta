<x-filament-panels::page>
    <div
        data-testid="import-donations-wizard"
        x-data="{ uploading: false }"
        x-on:livewire-upload-start="uploading = true"
        x-on:livewire-upload-finish="uploading = false"
        x-on:livewire-upload-error="uploading = false"
    >
        <div :class="{ 'pointer-events-none select-none opacity-60': uploading }">
            <x-filament-panels::form wire:submit="runImport">
                {{ $this->form }}
            </x-filament-panels::form>
        </div>

        <p x-show="uploading" style="display: none" class="mt-3 text-sm text-center text-gray-500">
            Uploading file, please wait&hellip;
        </p>
    </div>
</x-filament-panels::page>
