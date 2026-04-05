@php
    $heading     = $config['heading'] ?? '';
    $mapInput    = $config['map_input'] ?? '';
    $aspectRatio = $config['aspect_ratio'] ?? '16/9';
    $minHeight   = ($config['min_height'] ?? 300) . 'px';
    $maxHeight   = ($config['max_height'] ?? 600) . 'px';

    $embedUrl = \App\Services\MapEmbedParser::extractEmbedUrl($mapInput);
@endphp

@if ($embedUrl)
    <div class="widget-map-embed">
        @if ($heading)
            <h2 class="map-embed__heading">{{ $heading }}</h2>
        @endif

        <div
            class="map-embed__container"
            style="aspect-ratio: {{ $aspectRatio }}; min-height: {{ $minHeight }}"
            x-data="{ active: false }"
            x-on:keydown.escape.window="active = false"
            x-on:click.outside="active = false"
        >
            <iframe
                src="{{ $embedUrl }}"
                class="map-embed__iframe"
                :style="active ? '' : 'pointer-events: none'"
                allowfullscreen
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
            ></iframe>

            <div
                class="map-embed__overlay"
                x-show="!active"
                x-on:click="active = true"
            >
                <span class="map-embed__overlay-hint">Click to interact with map</span>
            </div>
        </div>
    </div>
@endif
