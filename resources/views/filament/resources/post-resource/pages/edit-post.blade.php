<x-filament-panels::page>
    @livewire('page-builder', ['pageId' => $this->record->id], key('page-builder-' . $this->record->id))
</x-filament-panels::page>
