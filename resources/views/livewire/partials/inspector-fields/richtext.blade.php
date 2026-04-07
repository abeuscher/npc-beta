<div
    wire:ignore
    data-upload-url="{{ route('filament.admin.inline-image-upload') }}"
    data-model-type="page_widget"
    data-model-id="{{ $blockId }}"
    x-data="richtextEditor(@js($block['config'][$field['key']] ?? ''), @js($field['key']))"
>
    <div x-ref="editor" class="min-h-[16rem]"></div>
</div>
