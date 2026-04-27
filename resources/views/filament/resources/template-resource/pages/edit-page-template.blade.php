<x-filament-panels::page>
    @if ($this->isNonDefault)
        @php
            $colorsInherited = $this->record->primary_color === null
                && $this->record->header_bg_color === null
                && $this->record->nav_link_color === null
                && $this->record->nav_hover_color === null
                && $this->record->nav_active_color === null
                && $this->record->footer_bg_color === null;
        @endphp
        <div x-data="{ inherit: @js($colorsInherited) }">
            <x-filament-panels::form wire:submit="save">
                {{ $this->form }}

                <div class="mt-4">
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input
                            type="checkbox"
                            x-model="inherit"
                            x-on:change="if (inherit) { $wire.clearAppearance() }"
                            class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                        />
                        Inherit Colors from Default
                    </label>
                </div>

                <x-filament-panels::form.actions :actions="$this->getCachedFormActions()" />
            </x-filament-panels::form>
        </div>
    @else
        <x-filament-panels::form wire:submit="save">
            {{ $this->form }}
            <x-filament-panels::form.actions :actions="$this->getCachedFormActions()" />
        </x-filament-panels::form>
    @endif
</x-filament-panels::page>
