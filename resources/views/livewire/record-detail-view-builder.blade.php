<div
    class="page-builder page-builder--record-detail"
    x-data
    x-on:open-widget-picker.window="if ($event.detail?.ownerId === @js($viewId)) { $wire.openAddModal($event.detail?.insertPosition ?? null, $event.detail?.layoutId ?? null, $event.detail?.columnIndex ?? null) }"
>
    <div data-page-builder-app data-bootstrap='@json($bootstrapData)' wire:ignore></div>
    @vite('resources/js/page-builder-vue/main.ts')

    @if ($showAddModal)
        <x-widget-picker-modal
            :widget-types="$widgetTypes"
            title="Add Widget"
            show-property="showAddModal"
            create-action="$wire.createBlock"
        />
    @endif
</div>
