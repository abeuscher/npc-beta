<x-filament-panels::page
    @class([
        'fi-resource-edit-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'fi-resource-record-' . $record->getKey(),
    ])
>
    <x-filament-panels::form
        id="form"
        :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
        wire:submit="save"
    >
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>

    <div style="margin-top: 2rem;">
        @livewire('record-detail-view-builder', ['viewId' => $this->record->id], key('record-detail-view-builder-' . $this->record->id))
    </div>

    <x-filament-panels::page.unsaved-data-changes-alert />
</x-filament-panels::page>
