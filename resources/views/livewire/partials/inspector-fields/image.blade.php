<div class="space-y-2">
    @if (!empty($imageUrls[$field['key']]))
        <div class="relative inline-block">
            <img
                src="{{ $imageUrls[$field['key']] }}"
                alt=""
                class="max-h-32 rounded border border-gray-200 dark:border-gray-700"
            >
            <button
                type="button"
                wire:click="removeImage('{{ $field['key'] }}')"
                class="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-xs text-white shadow hover:bg-red-600"
                title="Remove image"
            >&times;</button>
        </div>
    @endif
    <input
        type="file"
        wire:model="imageUploads.{{ $field['key'] }}"
        accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml"
        class="w-full text-sm text-gray-600 file:mr-2 file:rounded file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100 dark:text-gray-400 dark:file:bg-gray-700 dark:file:text-gray-200"
    >
    <div wire:loading wire:target="imageUploads.{{ $field['key'] }}" class="text-xs text-primary-600">
        Uploading…
    </div>
</div>
