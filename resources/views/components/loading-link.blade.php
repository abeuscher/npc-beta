@props(['href'])

<a
    href="{{ $href }}"
    x-data="{ navigating: false }"
    x-on:click="if (!$event.metaKey && !$event.ctrlKey && !$event.shiftKey && !$event.altKey && $event.button === 0) navigating = true"
    x-bind:class="{ 'pointer-events-none opacity-60': navigating }"
    {{ $attributes }}
>
    <span x-show="!navigating" class="contents">{{ $icon ?? '' }}</span>
    <span x-show="navigating" x-cloak class="contents"><x-loading-spinner /></span>
    {{ $slot }}
</a>
