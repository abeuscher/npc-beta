<div
    class="page-builder page-builder--dashboard"
    x-data
    x-on:open-widget-picker.window="if ($event.detail?.ownerId === @js($dashboardConfigId)) { $wire.openAddModal($event.detail?.insertPosition ?? null) }"
>
    <div data-page-builder-app data-bootstrap='@json($bootstrapData)' wire:ignore></div>
    @vite('resources/js/page-builder-vue/main.ts')

    @if ($showAddModal)
        <x-widget-picker-modal
            :widget-types="$widgetTypes"
            title="Add Dashboard Widget"
            show-property="showAddModal"
            create-action="$wire.createBlock"
        />
    @endif
</div>
