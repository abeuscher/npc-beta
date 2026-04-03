<select
    wire:model.live="block.config.{{ $field['key'] }}"
    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
>
    <option value="">— Select —</option>
    @foreach ($selectOptions[$field['key']] ?? [] as $optValue => $optLabel)
        <option value="{{ $optValue }}">{{ $optLabel }}</option>
    @endforeach
</select>
