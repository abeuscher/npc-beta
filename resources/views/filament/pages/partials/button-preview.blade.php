<div
    x-data="{
        get radius() {
            const val = $wire.data?.button_styles?.['{{ $variant }}']?.border_radius ?? 'slightly-rounded';
            return { 'sharp': '0', 'slightly-rounded': '0.25em', 'rounded': '0.5em', 'pill': '999px' }[val] ?? '0.25em';
        },
        get bg() { return $wire.data?.button_styles?.['{{ $variant }}']?.bg_color || 'transparent'; },
        get color() { return $wire.data?.button_styles?.['{{ $variant }}']?.text_color || 'inherit'; },
        get borderColor() { return $wire.data?.button_styles?.['{{ $variant }}']?.border_color || 'transparent'; },
        get borderWidth() { return $wire.data?.button_styles?.['{{ $variant }}']?.border_width || '0'; },
        get fontWeight() { return $wire.data?.button_styles?.['{{ $variant }}']?.font_weight || '600'; },
        get textTransform() { return $wire.data?.button_styles?.['{{ $variant }}']?.text_transform || 'none'; },
    }"
    class="mt-3 rounded-lg bg-gray-50 dark:bg-white/5 p-4"
>
    <p class="text-xs text-gray-400 dark:text-gray-500 mb-2">Preview</p>
    <div class="flex items-center gap-3">
        <span
            :style="`
                display: inline-block;
                padding: 0.625rem 1.5rem;
                text-decoration: none;
                cursor: pointer;
                background: ${bg};
                color: ${color};
                border-radius: ${radius};
                border: ${borderWidth} solid ${borderColor};
                font-weight: ${fontWeight};
                text-transform: ${textTransform};
                font-size: 0.875rem;
                line-height: 1.5;
            `"
        >{{ $label }} Button</span>

        <span
            :style="`
                display: inline-block;
                padding: 0.375rem 1rem;
                text-decoration: none;
                cursor: pointer;
                background: ${bg};
                color: ${color};
                border-radius: ${radius};
                border: ${borderWidth} solid ${borderColor};
                font-weight: ${fontWeight};
                text-transform: ${textTransform};
                font-size: 0.75rem;
                line-height: 1.5;
            `"
        >Small</span>
    </div>
</div>
