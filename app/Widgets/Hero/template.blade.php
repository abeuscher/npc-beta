@php
    $content         = $config['content'] ?? '';
    $overlayOpacity  = max(0, min(100, (int) ($config['background_overlay_opacity'] ?? 50))) / 100;
    $ctas            = $config['ctas'] ?? [];
    $overlapNav      = ($config['overlap_nav'] ?? false) == true;
    $fullscreen      = ($config['fullscreen'] ?? false) == true;
    $showScroll      = ($config['scroll_indicator'] ?? false) == true;
    $position        = $config['text_position'] ?? 'center';
    $minHeight       = $config['min_height'] ?? '24rem';
    $textMaxWidth    = $config['text_max_width'] ?? '42rem';
    $buttonAlignment = $config['button_alignment'] ?? 'auto';

    $videoUrl = '';
    if (!empty($configMedia['background_video'])) {
        $videoUrl = $configMedia['background_video']->getUrl();
    }

    $classes = ['widget--hero'];
    if ($fullscreen)  $classes[] = 'hero--fullscreen';
    if ($overlapNav)  $classes[] = 'hero--overlap-nav';
    if ($showScroll)  $classes[] = 'hero--has-scroll';
    // Legacy select values (pre nine-point control) normalize to the shared
    // alignment vocabulary for the class only — the raw value still feeds the
    // auto button-alignment below, so pages saved before the control change
    // render unchanged (legacy 'center-left' centers auto buttons).
    $positionClass = [
        'center-center' => 'center',
        'center-left'   => 'middle-left',
        'center-right'  => 'middle-right',
    ][$position] ?? ($position ?: 'center');
    $classes[] = 'hero--pos-' . $positionClass;
    if (!$fullscreen) $classes[] = 'hero--height-' . str_replace('rem', '', $minHeight);

    $resolvedButtonAlignment = $buttonAlignment === 'auto'
        ? (str_contains($position, 'center') ? 'center' : (str_contains($position, 'right') ? 'right' : 'left'))
        : $buttonAlignment;
@endphp

<div class="{{ implode(' ', $classes) }}" style="--hero-overlay: {{ $overlayOpacity }}; --hero-text-max-width: {{ $textMaxWidth }};">

    @if ($videoUrl)
        <video class="hero-video" autoplay muted loop playsinline preload="auto" aria-hidden="true">
            <source src="{{ $videoUrl }}" type="{{ $configMedia['background_video']->mime_type }}">
        </video>
    @endif

    @if ($videoUrl)
        <div class="hero-overlay"></div>
    @endif

    <div class="hero-body">
        <div class="site-container">
            <div class="hero-inner">
            @include('widget-shared.inline-prose', ['tag' => 'div', 'class' => 'hero-content', 'key' => 'content', 'type' => 'richtext', 'value' => $content, 'label' => 'Content'])

            @if (!empty($ctas))
                <div class="hero-ctas">
                    @include('widget-shared.buttons', [
                        'buttons'   => $ctas,
                        'alignment' => $resolvedButtonAlignment,
                    ])
                </div>
            @endif
            </div>
        </div>
    </div>

    @if ($showScroll)
        <div class="hero-scroll-indicator">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
        </div>
    @endif

</div>
