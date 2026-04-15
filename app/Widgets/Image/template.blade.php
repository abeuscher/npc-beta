@php
    $objectFit = in_array($config['object_fit'] ?? '', ['cover', 'contain']) ? $config['object_fit'] : 'cover';
    $altText = $config['alt_text'] ?? '';
    $linkUrl = $config['link_url'] ?? '';
    $demoUrl = (is_string($config['image'] ?? null) && $config['image'] !== '') ? $config['image'] : null;
@endphp

@if (!empty($configMedia['image']))
    @if ($linkUrl)<a href="{{ $linkUrl }}">@endif

    <x-picture
        :media="$configMedia['image']"
        :alt="$altText"
        class="widget-image widget-image--{{ $objectFit }}"
    />

    @if ($linkUrl)</a>@endif
@elseif ($demoUrl)
    @if ($linkUrl)<a href="{{ $linkUrl }}">@endif
    <img src="{{ $demoUrl }}" alt="{{ $altText }}" class="widget-image widget-image--{{ $objectFit }}" loading="lazy">
    @if ($linkUrl)</a>@endif
@endif
