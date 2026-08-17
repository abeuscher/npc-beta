// Config arrives in a sibling `<script type="application/json" x-ref="config">`
// block rather than as an `x-data` argument (session 375). Blade's `@js()`
// helper compiles to `JSON.parse(...)`, and the public site's CSP-safe Alpine
// build cannot reach globals like `JSON` from a template expression — the JSON
// block keeps one shape working on both the public site and the admin panel.
export default () => ({
    open: false,
    value: '',
    label: '',
    activeIndex: -1,
    allOptions: [],
    searchable: false,
    query: '',
    placeholder: '',
    inputId: '',

    init() {
        const config = JSON.parse(this.$refs.config.textContent);

        this.value = config.value ?? '';
        this.label = config.value ? config.selectedLabel : config.placeholder;
        this.allOptions = config.options;
        this.searchable = !!config.searchable;
        this.placeholder = config.placeholder;
        this.inputId = config.inputId;
    },

    get isEmpty() {
        return this.value === '' || this.value === null;
    },
    get filtered() {
        if (!this.searchable || !this.query.trim()) return this.allOptions;
        const q = this.query.toLowerCase();
        return this.allOptions.filter(o => o.label.toLowerCase().includes(q));
    },
    toggle() {
        this.open ? this.close() : this.openDropdown();
    },
    openDropdown() {
        this.open = true;
        this.query = '';
        const opts = this.filtered;
        this.activeIndex = opts.findIndex(o => o.value === this.value);
        if (this.activeIndex < 0) this.activeIndex = 0;
        this.$nextTick(() => {
            if (this.searchable && this.$refs.search) this.$refs.search.focus();
            this.scrollToActive();
        });
    },
    close() {
        this.open = false;
        this.activeIndex = -1;
        this.query = '';
    },
    select(index) {
        const opt = this.filtered[index];
        if (!opt) return;
        this.value = opt.value;
        this.label = opt.label;
        this.close();
        this.$refs.nativeSelect.value = opt.value;
        this.$refs.nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
        this.$dispatch('custom-select-change', { value: opt.value });
        this.$refs.trigger.focus();
    },
    onKeydown(e) {
        if (!this.open && (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            this.openDropdown();
            return;
        }
        if (!this.open) return;
        const opts = this.filtered;
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.activeIndex = Math.min(this.activeIndex + 1, opts.length - 1);
                this.scrollToActive();
                break;
            case 'ArrowUp':
                e.preventDefault();
                this.activeIndex = Math.max(this.activeIndex - 1, 0);
                this.scrollToActive();
                break;
            case 'Enter':
                e.preventDefault();
                this.select(this.activeIndex);
                break;
            case 'Escape':
                e.preventDefault();
                this.close();
                this.$refs.trigger.focus();
                break;
            case 'Tab':
                this.close();
                break;
        }
    },
    onSearchKeydown(e) {
        if (e.key === ' ') return;
        this.onKeydown(e);
    },
    // When the dropdown has its own search input, that input owns the keyboard;
    // the root listener only applies to the non-searchable variant.
    onKeydownUnlessSearchable(e) {
        if (!this.searchable) this.onKeydown(e);
    },
    scrollToActive() {
        const list = this.$refs.listbox;
        if (!list) return;
        const items = list.querySelectorAll('[role=option]');
        const el = items[this.activeIndex];
        if (el) el.scrollIntoView({ block: 'nearest' });
    },
    optionId(index) {
        return this.inputId + '_opt_' + index;
    },
});
