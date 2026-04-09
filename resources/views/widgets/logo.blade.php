@php
    $linkUrl   = $config['link_url'] ?? '/';
    $logoText  = $config['text'] ?? '';
    $logoMedia = $configMedia['logo'] ?? null;
@endphp

<div class="widget-logo">
    <a href="{{ $linkUrl }}" class="widget-logo__link">
        @if ($logoMedia)
            <x-picture :media="$logoMedia" alt="{{ $logoText }}" class="widget-logo__img" />
        @endif
        @if ($logoText !== '')
            <span class="widget-logo__text">{{ $logoText }}</span>
        @endif
    </a>
</div>
