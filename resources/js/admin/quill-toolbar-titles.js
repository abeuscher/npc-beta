// Quill renders its toolbar controls without title or aria-label attributes,
// so the icon-only buttons have no hover tooltip and no accessible name. Apply
// a label to each known control. Shared by the admin Quill editor and the
// page-builder inspector's rich-text field so both toolbars read identically.

const CONTROL_LABELS = [
    ['button.ql-bold', 'Bold'],
    ['button.ql-italic', 'Italic'],
    ['button.ql-underline', 'Underline'],
    ['button.ql-strike', 'Strikethrough'],
    ['button.ql-list[value="bullet"]', 'Bulleted list'],
    ['button.ql-list[value="ordered"]', 'Numbered list'],
    ['button.ql-blockquote', 'Blockquote'],
    ['button.ql-link', 'Insert link'],
    ['button.ql-image', 'Insert image'],
    ['button.ql-heroicon', 'Insert icon'],
    ['button.ql-clean', 'Clear formatting'],
    ['.ql-header .ql-picker-label', 'Heading level'],
    ['.ql-align .ql-picker-label', 'Text alignment'],
];

export function applyToolbarTitles(toolbarEl) {
    if (!toolbarEl) return;

    for (const [selector, label] of CONTROL_LABELS) {
        toolbarEl.querySelectorAll(selector).forEach((el) => {
            el.setAttribute('title', label);
            el.setAttribute('aria-label', label);
        });
    }
}
