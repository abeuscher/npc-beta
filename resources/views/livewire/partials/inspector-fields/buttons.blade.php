@php
    $styleOptions = $field['style_options'] ?? [
        'primary'   => 'Primary',
        'secondary' => 'Secondary',
        'text'      => 'Text Only',
    ];
@endphp

<div
    wire:ignore
    x-data="buttonListManager(@js($block['config'][$field['key']] ?? []), @js($field['key']))"
>
    <template x-for="(btn, index) in buttons" :key="index">
        <div class="mb-2 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-2 flex items-center justify-between">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400" x-text="'Button ' + (index + 1)"></span>
                <div class="flex items-center gap-1">
                    <button type="button" x-on:click="moveUp(index)" x-show="index > 0"
                        class="rounded p-0.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" title="Move up">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>
                        </svg>
                    </button>
                    <button type="button" x-on:click="moveDown(index)" x-show="index < buttons.length - 1"
                        class="rounded p-0.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" title="Move down">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <button type="button" x-on:click="remove(index)"
                        class="rounded p-0.5 text-red-400 hover:text-red-600" title="Remove button">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="space-y-2">
                <input
                    type="text"
                    x-model="btn.text"
                    x-on:change="save()"
                    placeholder="Button text"
                    class="w-full rounded border border-gray-300 bg-white px-2.5 py-1.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                >
                <input
                    type="text"
                    x-model="btn.url"
                    x-on:change="save()"
                    placeholder="URL (e.g. /about or https://example.com)"
                    class="w-full rounded border border-gray-300 bg-white px-2.5 py-1.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                >
                <select
                    x-model="btn.style"
                    x-on:change="save()"
                    class="w-full rounded border border-gray-300 bg-white px-2.5 py-1.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                >
                    @foreach ($styleOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </template>

    <button
        type="button"
        x-on:click="add()"
        class="flex items-center gap-1.5 rounded-lg border border-dashed border-gray-300 px-3 py-2 text-xs font-medium text-gray-500 hover:border-primary-400 hover:text-primary-600 dark:border-gray-600 dark:text-gray-400 dark:hover:border-primary-500 dark:hover:text-primary-400"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Add Button
    </button>
</div>
