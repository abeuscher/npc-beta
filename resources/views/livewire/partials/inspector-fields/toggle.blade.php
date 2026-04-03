<label class="flex items-center gap-2">
    <input
        type="checkbox"
        wire:model="block.config.{{ $field['key'] }}"
        class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500"
    >
    <span class="text-sm text-gray-700 dark:text-gray-300">Enabled</span>
</label>
