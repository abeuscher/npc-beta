export function spacingControls(styleConfig) {
    return {
        spOpen: false,
        sc: styleConfig,

        get paddingAll() {
            const t = this.sc.padding_top ?? ''
            const r = this.sc.padding_right ?? ''
            const b = this.sc.padding_bottom ?? ''
            const l = this.sc.padding_left ?? ''
            return (t === r && r === b && b === l && t !== '') ? t : ''
        },
        set paddingAll(v) {
            this.sc = { ...this.sc, padding_top: v, padding_right: v, padding_bottom: v, padding_left: v }
        },
        get paddingAllPlaceholder() {
            const t = this.sc.padding_top ?? ''
            const r = this.sc.padding_right ?? ''
            const b = this.sc.padding_bottom ?? ''
            const l = this.sc.padding_left ?? ''
            return (t === r && r === b && b === l) ? '' : 'mixed'
        },

        get marginAll() {
            const t = this.sc.margin_top ?? ''
            const r = this.sc.margin_right ?? ''
            const b = this.sc.margin_bottom ?? ''
            const l = this.sc.margin_left ?? ''
            return (t === r && r === b && b === l && t !== '') ? t : ''
        },
        set marginAll(v) {
            this.sc = { ...this.sc, margin_top: v, margin_right: v, margin_bottom: v, margin_left: v }
        },
        get marginAllPlaceholder() {
            const t = this.sc.margin_top ?? ''
            const r = this.sc.margin_right ?? ''
            const b = this.sc.margin_bottom ?? ''
            const l = this.sc.margin_left ?? ''
            return (t === r && r === b && b === l) ? '' : 'mixed'
        },
    }
}
