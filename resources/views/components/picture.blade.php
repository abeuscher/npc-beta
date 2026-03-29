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
    $fallbackSrcset = '';
    if ($media && $hasConversions && !$isSvg) {
        $webpParts = [];
        $fallbackParts = [];

        // Collect responsive-* conversions
        foreach ($media->generated_conversions as $name => $generated) {
            if (!$generated) continue;
            if (str_starts_with($name, 'responsive-')) {
                $w = (int) str_replace('responsive-', '', $name);
                $webpParts[$w] = $media->getUrl($name) . " {$w}w";
            }
        }

        // Add the main webp conversion
        if (!empty($media->generated_conversions['webp'])) {
            $webpParts[0] = $media->getUrl('webp');
            // Use max breakpoint width or media width
            $maxW = $media->getCustomProperty('width', 0);
            if ($maxW && !isset($webpParts[$maxW])) {
                $webpParts[$maxW] = $media->getUrl('webp') . " {$maxW}w";
            }
        }

        krsort($webpParts);
        $webpSrcset = implode(', ', array_filter($webpParts, fn ($v, $k) => $k > 0, ARRAY_FILTER_USE_BOTH));

        // Original format fallback — just the original URL
        $fallbackSrcset = $originalUrl;
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
