<div class="flex items-center gap-2">
    <input
        type="color"
        wire:model.live="block.config.{{ $field['key'] }}"
        class="h-8 w-8 cursor-pointer rounded border border-gray-300 p-0 dark:border-gray-600"
    >
    <input
        type="text"
        wire:model.live="block.config.{{ $field['key'] }}"
        placeholder="{{ $field['helper'] ?? '#000000' }}"
        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
    >
</div>
