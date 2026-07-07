@php
    $objectFit = in_array($config['object_fit'] ?? '', ['cover', 'contain']) ? $config['object_fit'] : 'cover';
    $altText = $config['alt_text'] ?? '';
    $linkUrl = $config['link_url'] ?? '';
    $demoUrl = (is_string($config['image'] ?? null) && $config['image'] !== '') ? $config['image'] : null;

    $aspectRatio = $config['aspect_ratio'] ?? 'auto';
    $validRatios = ['1:1', '4:3', '3:2', '16:9', '4:5', '3:4'];
    $ratioClass  = in_array($aspectRatio, $validRatios, true)
        ? 'widget-image--ratio-' . str_replace(':', '-', $aspectRatio)
        : '';

    $rawMaxWidth = trim((string) ($config['max_width'] ?? ''));
    $maxWidth = '';
    if ($rawMaxWidth !== '') {
        if (preg_match('/^[0-9]+(\.[0-9]+)?$/', $rawMaxWidth)) {
            $maxWidth = $rawMaxWidth . 'px';
        } elseif (preg_match('/^[0-9]+(\.[0-9]+)?(px|%|rem|em|vw|vh)$/', $rawMaxWidth)) {
            $maxWidth = $rawMaxWidth;
        }
    }
    $style = $maxWidth !== '' ? "max-width: {$maxWidth};" : '';

    $validPositions = ['top-left', 'top-center', 'top-right', 'middle-left', 'center', 'middle-right', 'bottom-left', 'bottom-center', 'bottom-right'];
    $objectPosition = in_array($config['object_position'] ?? '', $validPositions, true) ? $config['object_position'] : 'center';

    $classes = trim('widget-image widget-image--' . $objectFit . ' widget-image--pos-' . $objectPosition . ' ' . $ratioClass);

    // LCP path: an eager image loads immediately and is hinted high-priority.
    // Default lazy — only a hero / above-the-fold image should opt into eager.
    $eager       = ($config['loading_priority'] ?? 'lazy') === 'eager';
    $loadingAttr = $eager ? 'eager' : 'lazy';

    // Responsive sizes: PageBlockRenderer derives a per-column value from the
    // grid fraction (e.g. 60vw in a 3fr column). Falls back to 100vw outside a
    // multi-column layout — the partial's own default, restated concretely.
    $pictureSizes = ($columnSizes ?? null) ?: '100vw';
@endphp

@if (!empty($configMedia['image']))
    @if ($linkUrl)<a href="{{ $linkUrl }}">@endif

    <x-picture
        :media="$configMedia['image']"
        :alt="$altText"
        :class="$classes"
        :style="$style"
        :loading="$loadingAttr"
        :fetchpriority="$eager ? 'high' : null"
        :sizes="$pictureSizes"
    />

    @if ($linkUrl)</a>@endif
@elseif ($demoUrl)
    @if ($linkUrl)<a href="{{ $linkUrl }}">@endif
    <img src="{{ $demoUrl }}" alt="{{ $altText }}" class="{{ $classes }}" @if ($style) style="{{ $style }}" @endif loading="{{ $loadingAttr }}"@if ($eager) fetchpriority="high"@endif>
    @if ($linkUrl)</a>@endif
@endif
