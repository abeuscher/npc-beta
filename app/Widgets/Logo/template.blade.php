@php
    $linkUrl   = $config['link_url'] ?? '/';
    $logoText  = $config['text'] ?? '';
    $logoMedia = $configMedia['logo'] ?? null;
    $usePlaceholder = !$logoMedia && $logoText === '';
@endphp

<div class="widget-logo">
    <a href="{{ $linkUrl }}" class="widget-logo__link">
        @if ($logoMedia)
            <x-picture :media="$logoMedia" alt="{{ $logoText }}" class="widget-logo__img" />
        @elseif ($usePlaceholder)
            <img src="{{ asset('images/default-logo.svg') }}" alt="Your logo" class="widget-logo__img widget-logo__img--placeholder" />
        @endif
        @if ($logoText !== '')
            <span class="widget-logo__text">{{ $logoText }}</span>
        @endif
    </a>
</div>
