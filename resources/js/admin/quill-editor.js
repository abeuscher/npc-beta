import { openHeroiconPicker, setHeroiconsUrl } from './heroicon-picker.js';
import { registerHeroiconBlot, HEROICON_TOOLBAR_BUTTON_SVG } from './heroicon-blot.js';

export default (state) => ({
    state,
    init() {
        registerHeroiconBlot();

        const container = this.$el;
        const self = this;
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
                        ['link', 'image', 'heroicon'],
                        ['clean'],
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
                                        body: form,
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
                        },
                        heroicon: function () {
                            const button = this.container.querySelector('button.ql-heroicon');
                            if (!button) return;
                            if (container.dataset.heroiconsUrl) {
                                setHeroiconsUrl(container.dataset.heroiconsUrl);
                            }
                            const range = quill.getSelection(true) || { index: quill.getLength() - 1 };
                            openHeroiconPicker(button, (icon) => {
                                quill.insertEmbed(range.index, 'heroicon', icon, Quill.sources.USER);
                                quill.setSelection(range.index + 1, Quill.sources.SILENT);
                            });
                        },
                    },
                },
            },
        });

        // Quill creates the heroicon toolbar button as <button class="ql-heroicon">
        // with empty content. Inject the picker icon so the operator sees what
        // the button does.
        const toolbarButton = this.$el.querySelector('button.ql-heroicon');
        if (toolbarButton && !toolbarButton.firstChild) {
            toolbarButton.innerHTML = HEROICON_TOOLBAR_BUTTON_SVG;
        }

        if (this.state) quill.root.innerHTML = this.state;

        quill.on('text-change', () => {
            self.state = quill.root.innerHTML;
        });
    },
});
