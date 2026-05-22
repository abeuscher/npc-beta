import { openHeroiconPicker, setHeroiconsUrl } from './heroicon-picker.js';
import { registerHeroiconBlot, HEROICON_TOOLBAR_BUTTON_SVG } from './heroicon-blot.js';
import { applyToolbarTitles } from './quill-toolbar-titles.js';

/**
 * Upload-time dedup for inline images. Hashes the chosen file, asks the library
 * whether it already holds it, and — on an exact (content-hash) match — offers
 * to reuse the existing asset. Returns the existing URL when the operator
 * accepts reuse, or null to fall through to a normal upload. Degrades silently
 * to a normal upload if hashing or the check is unavailable.
 */
async function findExistingInlineImage(container, file) {
    const checkUrl = container.dataset.dedupCheckUrl;
    if (!checkUrl || !globalThis.crypto?.subtle) return null;

    let hash;
    try {
        const digest = await globalThis.crypto.subtle.digest('SHA-256', await file.arrayBuffer());
        hash = Array.from(new Uint8Array(digest)).map((b) => b.toString(16).padStart(2, '0')).join('');
    } catch {
        return null;
    }

    let matches;
    try {
        const res = await fetch(checkUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.head.querySelector('meta[name=csrf-token]').content,
            },
            body: JSON.stringify({ hash }),
        });
        matches = (await res.json()).matches ?? [];
    } catch {
        return null;
    }

    const identical = matches.find((m) => m.match_type === 'identical' && m.url);
    if (!identical) return null;

    const reuse = window.confirm(
        'You already have this image in your media library. Reuse the existing copy instead of uploading another?'
    );

    return reuse ? identical.url : null;
}

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

                                const insert = (url) => {
                                    const range = quill.getSelection(true);
                                    quill.insertEmbed(range.index, 'image', url);
                                    quill.setSelection(range.index + 1);
                                };

                                // Warn-and-offer: if this exact image is already in
                                // the library, offer to reuse it (no new upload).
                                const existingUrl = await findExistingInlineImage(container, file);
                                if (existingUrl) {
                                    insert(existingUrl);
                                    return;
                                }

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
                                        insert(data.url);
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

        applyToolbarTitles(quill.getModule('toolbar').container);

        if (this.state) quill.root.innerHTML = this.state;

        quill.on('text-change', () => {
            self.state = quill.root.innerHTML;
        });
    },
});
