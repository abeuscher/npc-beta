@php
    /**
     * Renders a <picture> element from a Spatie media object with WebP source
     * and responsive srcset, or a plain <img> for SVGs / missing variants.
     *
     * Props:
     *   $media   — Spatie\MediaLibrary\MediaCollections\Models\Media instance (required)
     *   $alt     — alt text (default: '')
     *   $class   — CSS class (default: '')
     *   $loading — 'lazy' | 'eager' (default: 'lazy')
     *   $width   — explicit width attribute (optional)
     *   $height  — explicit height attribute (optional)
     */

    $isSvg = $media && str_contains($media->mime_type, 'svg');
    $hasConversions = $media && count($media->generated_conversions ?? []) > 0;
    $originalUrl = $media?->getUrl();

    // Build WebP srcset from generated conversions
    $webpSrcset = '';
    if ($media && $hasConversions && !$isSvg) {
        $widthEntries = [];

        // Collect responsive-* conversions (each carries an explicit width descriptor)
        foreach ($media->generated_conversions as $name => $generated) {
            if (!$generated) continue;
            if (str_starts_with($name, 'responsive-')) {
                $w = (int) str_replace('responsive-', '', $name);
                $widthEntries[$w] = $media->getUrl($name) . " {$w}w";
            }
        }

        if (!empty($widthEntries)) {
            krsort($widthEntries);
            $webpSrcset = implode(', ', $widthEntries);
        } elseif (!empty($media->generated_conversions['webp'])) {
            // No responsive variants — fall back to a plain srcset of the base webp URL.
            $webpSrcset = $media->getUrl('webp');
        }
    }
@endphp

@if ($media)
    @if ($isSvg || !$hasConversions)
        <img
            src="{{ $originalUrl }}"
            alt="{{ $alt ?? '' }}"
            @if (!empty($class)) class="{{ $class }}" @endif
            loading="{{ $loading ?? 'lazy' }}"
            @if (!empty($width)) width="{{ $width }}" @endif
            @if (!empty($height)) height="{{ $height }}" @endif
            {{ $attributes->except(['media', 'alt', 'class', 'loading', 'width', 'height']) }}
        >
    @else
        <picture>
            @if ($webpSrcset)
                <source
                    type="image/webp"
                    srcset="{{ $webpSrcset }}"
                    sizes="{{ $attributes->get('sizes', '100vw') }}"
                >
            @endif
            <img
                src="{{ $originalUrl }}"
                alt="{{ $alt ?? '' }}"
                @if (!empty($class)) class="{{ $class }}" @endif
                loading="{{ $loading ?? 'lazy' }}"
                @if (!empty($width)) width="{{ $width }}" @endif
                @if (!empty($height)) height="{{ $height }}" @endif
                {{ $attributes->except(['media', 'alt', 'class', 'loading', 'width', 'height', 'sizes']) }}
            >
        </picture>
    @endif
@endif
