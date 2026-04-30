// Heroicon Quill blot — shared by the Filament admin Quill editor
// (resources/js/admin/quill-editor.js) and the page-builder Vue richtext field
// (resources/js/page-builder-vue/components/fields/RichTextField.vue). Both
// surfaces use the same global Quill v2 instance loaded via CDN by Filament's
// AdminPanelProvider, so registering the blot once per page is sufficient —
// the registration is idempotent.

let heroiconBlotRegistered = false;

// Inline SVG used as the toolbar button icon. Heroicon outline `face-smile`,
// chosen because it's a recognisable "icon picker" mark that doesn't visually
// collide with the existing bold/italic/list toolbar set.
export const HEROICON_TOOLBAR_BUTTON_SVG = `
<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
  <path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 0 1-6.364 0M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Z"/>
</svg>`;

export function registerHeroiconBlot() {
    if (heroiconBlotRegistered || typeof window === 'undefined' || typeof window.Quill === 'undefined') {
        return;
    }
    const Quill = window.Quill;
    const Embed = Quill.import('blots/embed');

    // The heroicon blot wraps an inline <svg> in a <span class="ql-heroicon">.
    // Round-trip via DOM: Quill stores the document via root.innerHTML and
    // rehydrates from the same HTML on next load — the blot's `tagName` +
    // `className` match makes Quill rewrap the existing DOM. The data-heroicon
    // attribute carries the name; the inner SVG carries the visual.
    class HeroiconBlot extends Embed {
        static create(value) {
            const node = super.create();
            if (value && typeof value === 'object') {
                node.setAttribute('data-heroicon', value.name || '');
                const svg = document.createElement('span');
                svg.setAttribute('aria-hidden', 'true');
                svg.className = 'ql-heroicon__svg';
                svg.innerHTML = value.svg || '';
                node.insertBefore(svg, node.firstChild);
            }
            return node;
        }

        static value(node) {
            const wrapper = node.querySelector('.ql-heroicon__svg');
            return {
                name: node.getAttribute('data-heroicon') || '',
                svg: wrapper ? wrapper.innerHTML : '',
            };
        }
    }
    HeroiconBlot.blotName = 'heroicon';
    HeroiconBlot.tagName = 'span';
    HeroiconBlot.className = 'ql-heroicon';

    Quill.register(HeroiconBlot);
    heroiconBlotRegistered = true;
}
