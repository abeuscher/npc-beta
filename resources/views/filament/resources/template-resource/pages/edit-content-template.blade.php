<x-filament-panels::page>
    {{-- Details tab (Filament form) --}}
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}

        <x-filament-panels::form.actions :actions="$this->getCachedFormActions()" />
    </x-filament-panels::form>

    {{-- Widget editor (Vue page-builder in template mode) --}}
    <div class="mt-6">
        @livewire('page-builder', ['ownerId' => $this->record->id, 'ownerType' => 'template'], key('page-builder-' . $this->record->id))
    </div>
</x-filament-panels::page>
