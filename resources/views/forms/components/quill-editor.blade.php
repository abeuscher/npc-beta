@php
    $record = $getRecord();
    $inlineImageModelType = match(true) {
        $record instanceof \App\Models\Event => 'event',
        $record instanceof \App\Models\EmailTemplate => 'email_template',
        default => null,
    };
    $inlineImageModelId = $record?->getKey();
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:ignore
        class="w-full overflow-hidden border-b border-[#ccc]"
        data-upload-url="{{ route('filament.admin.inline-image-upload') }}"
        data-model-type="{{ $inlineImageModelType }}"
        data-model-id="{{ $inlineImageModelId }}"
        x-data="quillEditor($wire.entangle('{{ $getStatePath() }}'))"
    >
        <div x-ref="editor" class="min-h-[16rem]"></div>
    </div>
</x-dynamic-component>
