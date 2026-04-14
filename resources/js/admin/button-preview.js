const RADIUS_MAP = {
    sharp: '0',
    'slightly-rounded': '0.25em',
    rounded: '0.5em',
    pill: '999px',
};

export default (variant) => ({
    get _styles() {
        return this.$wire.data?.button_styles?.[variant] ?? {};
    },
    get radius() {
        return RADIUS_MAP[this._styles.border_radius ?? 'slightly-rounded'] ?? '0.25em';
    },
    get bg() { return this._styles.bg_color || 'transparent'; },
    get color() { return this._styles.text_color || 'inherit'; },
    get borderColor() { return this._styles.border_color || 'transparent'; },
    get borderWidth() { return this._styles.border_width || '0'; },
    get fontWeight() { return this._styles.font_weight || '600'; },
    get textTransform() { return this._styles.text_transform || 'none'; },
});
