<div>
    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
        {{ $field['label'] }}
    </label>

    @if ($field['type'] === 'richtext')
        <div
            wire:ignore
            data-upload-url="{{ route('filament.admin.inline-image-upload') }}"
            data-model-type="page_widget"
            data-model-id="{{ $blockId }}"
            x-data="{
                init() {
                    const container = this.$el;
                    const quill = new Quill(this.$refs.editor, {
                        theme: 'snow',
                        modules: {
                            toolbar: {
                                container: [
                                    [{ header: [2, 3, 4, 5, false] }],
                                    ['bold', 'italic', 'underline', 'strike'],
                                    [{ color: [] }, { background: [] }],
                                    [{ list: 'bullet' }, { list: 'ordered' }],
                                    ['blockquote'],
                                    [{ align: ['', 'center', 'right', 'justify'] }],
                                    ['link', 'image'],
                                    ['clean']
                                ],
                                handlers: {
                                    image: function () {
                                        const modelType = container.dataset.modelType;
                                        const modelId = container.dataset.modelId;
                                        if (!modelType || !modelId) return;

                                        const input = document.createElement('input');
                                        input.type = 'file';
                                        input.accept = 'image/png,image/jpeg,image/gif,image/webp,image/svg+xml';
                                        input.onchange = async () => {
                                            const file = input.files[0];
                                            if (!file) return;

                                            const form = new FormData();
                                            form.append('file', file);
                                            form.append('model_type', modelType);
                                            form.append('model_id', modelId);

                                            try {
                                                const res = await fetch(container.dataset.uploadUrl, {
                                                    method: 'POST',
                                                    headers: { 'X-CSRF-TOKEN': document.head.querySelector('meta[name=csrf-token]').content },
                                                    body: form
                                                });
                                                const data = await res.json();
                                                if (data.url) {
                                                    const range = quill.getSelection(true);
                                                    quill.insertEmbed(range.index, 'image', data.url);
                                                    quill.setSelection(range.index + 1);
                                                }
                                            } catch (e) {
                                                console.error('Inline image upload failed', e);
                                            }
                                        };
                                        input.click();
                                    }
                                }
                            }
                        }
                    });

                    const initial = {{ json_encode($block['config'][$field['key']] ?? '') }};
                    if (initial) quill.root.innerHTML = initial;

                    quill.on('text-change', () => {
                        $wire.updateConfig('{{ $field['key'] }}', quill.root.innerHTML);
                    });
                }
            }"
        >
            <div x-ref="editor" class="min-h-[16rem]"></div>
        </div>

    @elseif ($field['type'] === 'textarea')
        <textarea
            wire:model.lazy="block.config.{{ $field['key'] }}"
            rows="4"
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
        ></textarea>

    @elseif ($field['type'] === 'toggle')
        <label class="flex items-center gap-2">
            <input
                type="checkbox"
                wire:model="block.config.{{ $field['key'] }}"
                class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500"
            >
            <span class="text-sm text-gray-700 dark:text-gray-300">Enabled</span>
        </label>

    @elseif ($field['type'] === 'number')
        <input
            type="number"
            wire:model.lazy="block.config.{{ $field['key'] }}"
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
        >

    @elseif ($field['type'] === 'url')
        <input
            type="url"
            wire:model.lazy="block.config.{{ $field['key'] }}"
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
        >

    @elseif ($field['type'] === 'select')
        <select
            wire:model.live="block.config.{{ $field['key'] }}"
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
        >
            <option value="">— Select —</option>
            @foreach ($selectOptions[$field['key']] ?? [] as $optValue => $optLabel)
                <option value="{{ $optValue }}">{{ $optLabel }}</option>
            @endforeach
        </select>

    @elseif ($field['type'] === 'image')
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

    @else {{-- text (default) --}}
        <input
            type="text"
            wire:model.lazy="block.config.{{ $field['key'] }}"
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
        >
    @endif
</div>
