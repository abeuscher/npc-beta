@php
    $item = $widgetData['item'] ?? null;

    // Pick the requested event image, falling back to the other when the chosen
    // collection is empty. Both URLs come from the projector (DTO, not a model).
    $source = ($config['image_source'] ?? 'header') === 'thumbnail' ? 'thumbnail' : 'header';
    $header = (string) ($item['header_image'] ?? '');
    $thumb  = (string) ($item['image'] ?? '');
    $src = $source === 'thumbnail'
        ? ($thumb !== '' ? $thumb : $header)
        : ($header !== '' ? $header : $thumb);

    $alt = trim((string) ($config['alt_text'] ?? '')) !== ''
        ? $config['alt_text']
        : (string) ($item['title'] ?? '');

    $linkUrl = $config['link_url'] ?? '';

    $objectFit = in_array($config['object_fit'] ?? '', ['cover', 'contain'], true) ? $config['object_fit'] : 'cover';

    $ratioMap = ['1:1' => '1 / 1', '4:3' => '4 / 3', '3:2' => '3 / 2', '16:9' => '16 / 9', '4:5' => '4 / 5', '3:4' => '3 / 4'];
    $ratioCss = $ratioMap[$config['aspect_ratio'] ?? 'auto'] ?? '';

    $rawMaxWidth = trim((string) ($config['max_width'] ?? ''));
    $maxWidth = '';
    if ($rawMaxWidth !== '') {
        if (preg_match('/^[0-9]+(\.[0-9]+)?$/', $rawMaxWidth)) {
            $maxWidth = $rawMaxWidth . 'px';
        } elseif (preg_match('/^[0-9]+(\.[0-9]+)?(px|%|rem|em|vw|vh)$/', $rawMaxWidth)) {
            $maxWidth = $rawMaxWidth;
        }
    }

    $imgStyle = 'display:block;width:100%;height:auto;object-fit:' . $objectFit . ';';
    if ($ratioCss !== '') {
        $imgStyle .= 'aspect-ratio:' . $ratioCss . ';';
    }
    $wrapStyle = $maxWidth !== '' ? 'max-width:' . $maxWidth . ';' : '';

    // LCP path: an eager image loads immediately and is hinted high-priority.
    // Default lazy — only an above-the-fold event image should opt into eager.
    $eager       = ($config['loading_priority'] ?? 'lazy') === 'eager';
    $loadingAttr = $eager ? 'eager' : 'lazy';
@endphp

@if ($item && $src !== '')
    <div class="widget-event-image"@if ($wrapStyle) style="{{ $wrapStyle }}"@endif>
        @if ($linkUrl)<a href="{{ $linkUrl }}">@endif
        <img src="{{ $src }}" alt="{{ $alt }}" style="{{ $imgStyle }}" loading="{{ $loadingAttr }}"@if ($eager) fetchpriority="high"@endif>
        @if ($linkUrl)</a>@endif
    </div>
@endif
