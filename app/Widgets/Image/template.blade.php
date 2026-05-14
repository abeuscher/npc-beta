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

    $classes = trim('widget-image widget-image--' . $objectFit . ' ' . $ratioClass);
@endphp

@if (!empty($configMedia['image']))
    @if ($linkUrl)<a href="{{ $linkUrl }}">@endif

    <x-picture
        :media="$configMedia['image']"
        :alt="$altText"
        :class="$classes"
        :style="$style"
    />

    @if ($linkUrl)</a>@endif
@elseif ($demoUrl)
    @if ($linkUrl)<a href="{{ $linkUrl }}">@endif
    <img src="{{ $demoUrl }}" alt="{{ $altText }}" class="{{ $classes }}" @if ($style) style="{{ $style }}" @endif loading="lazy">
    @if ($linkUrl)</a>@endif
@endif
