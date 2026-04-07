export function richtextEditor(initialValue, configKey) {
    return {
        init() {
            const container = this.$el
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
                                const modelType = container.dataset.modelType
                                const modelId = container.dataset.modelId
                                if (!modelType || !modelId) return

                                const input = document.createElement('input')
                                input.type = 'file'
                                input.accept = 'image/png,image/jpeg,image/gif,image/webp,image/svg+xml'
                                input.onchange = async () => {
                                    const file = input.files[0]
                                    if (!file) return

                                    const form = new FormData()
                                    form.append('file', file)
                                    form.append('model_type', modelType)
                                    form.append('model_id', modelId)

                                    try {
                                        const res = await fetch(container.dataset.uploadUrl, {
                                            method: 'POST',
                                            headers: { 'X-CSRF-TOKEN': document.head.querySelector('meta[name=csrf-token]').content },
                                            body: form
                                        })
                                        const data = await res.json()
                                        if (data.url) {
                                            const range = quill.getSelection(true)
                                            quill.insertEmbed(range.index, 'image', data.url)
                                            quill.setSelection(range.index + 1)
                                        }
                                    } catch (e) {
                                        console.error('Inline image upload failed', e)
                                    }
                                }
                                input.click()
                            }
                        }
                    }
                }
            })

            if (initialValue) quill.root.innerHTML = initialValue

            const wire = this.$wire
            quill.on('text-change', () => {
                wire.updateConfig(configKey, quill.root.innerHTML)
            })

            Livewire.on('inspector-field-updated', (params) => {
                const data = Array.isArray(params) ? params[0] : params
                if (data.key !== configKey) return
                const current = quill.root.innerHTML
                if (current !== data.value) {
                    quill.root.innerHTML = data.value
                }
            })
        }
    }
}
