@if (!empty($configMedia['image']))
    @php
        $objectFit = in_array($config['object_fit'] ?? '', ['cover', 'contain']) ? $config['object_fit'] : 'cover';
        $altText = $config['alt_text'] ?? '';
        $linkUrl = $config['link_url'] ?? '';
    @endphp

    @if ($linkUrl)
        <a href="{{ $linkUrl }}">
    @endif

    <x-picture
        :media="$configMedia['image']"
        :alt="$altText"
        class="widget-image widget-image--{{ $objectFit }}"
    />

    @if ($linkUrl)
        </a>
    @endif
@endif
