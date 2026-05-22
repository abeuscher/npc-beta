@php
    $linkUrl   = $config['link_url'] ?? '/';
    $logoText  = $config['text'] ?? '';
    $logoMedia = $configMedia['logo'] ?? null;
    $usePlaceholder = !$logoMedia && $logoText === '';
    $logoFallbackAlt = config('app.name', 'Home');
@endphp

<div class="widget-logo">
    <a href="{{ $linkUrl }}" class="widget-logo__link">
        @if ($logoMedia)
            <x-picture :media="$logoMedia" alt="{{ $logoText !== '' ? '' : $logoFallbackAlt }}" class="widget-logo__img" />
        @elseif ($usePlaceholder)
            <img src="{{ asset('images/default-logo.svg') }}" alt="{{ $logoFallbackAlt }}" class="widget-logo__img widget-logo__img--placeholder" />
        @endif
        @if ($logoText !== '')
            <span class="widget-logo__text">{{ $logoText }}</span>
        @endif
    </a>
</div>
