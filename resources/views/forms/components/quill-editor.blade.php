<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:ignore
        x-data="{
            state: $wire.entangle('{{ $getStatePath() }}'),
            init() {
                const quill = new Quill(this.$refs.editor, {
                    theme: 'snow',
                    modules: {
                        toolbar: [
                            [{ font: [] }, { size: [] }],
                            ['bold', 'italic', 'underline', 'strike'],
                            [{ color: [] }, { background: [] }],
                            [{ list: 'ordered' }, { list: 'bullet' }],
                            ['link'],
                            ['clean']
                        ]
                    }
                });

                if (this.state) quill.root.innerHTML = this.state;

                quill.on('text-change', () => {
                    this.state = quill.root.innerHTML;
                });
            }
        }"
    >
        <div x-ref="editor" class="min-h-[160px]"></div>
    </div>
</x-dynamic-component>
