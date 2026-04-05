<div
    class="grid gap-2"
    style="grid-template-columns: repeat({{ $field['columns'] ?? 2 }}, 1fr)"
>
    @foreach ($field['options'] ?? [] as $optValue => $optLabel)
        <label class="flex items-center gap-2">
            <input
                type="checkbox"
                value="{{ $optValue }}"
                @if (in_array($optValue, $block['config'][$field['key']] ?? []))
                    checked
                @endif
                wire:click="toggleCheckbox('{{ $field['key'] }}', '{{ $optValue }}')"
                class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500"
            >
            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $optLabel }}</span>
        </label>
    @endforeach
</div>
