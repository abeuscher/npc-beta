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
                            [{ header: [1, 2, 3, 4, 5, false] }],
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
