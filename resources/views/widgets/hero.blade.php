@php
    $content         = $config['content'] ?? '';
    $overlayOpacity  = max(0, min(100, (int) ($config['overlay_opacity'] ?? 50))) / 100;
    $ctas            = $config['ctas'] ?? [];
    $overlapNav      = ($config['overlap_nav'] ?? false) == true;
    $fullscreen      = ($config['fullscreen'] ?? false) == true;
    $showScroll      = ($config['scroll_indicator'] ?? false) == true;
    $position        = $config['text_position'] ?? 'center-center';
    $minHeight       = $config['min_height'] ?? '24rem';
    $backgroundColor = $config['background_color'] ?? '';
    $textColor       = $config['text_color'] ?? '';

    $bgUrl = '';
    if (!empty($configMedia['background_image'])) {
        $media = $configMedia['background_image'];
        $bgUrl = !empty($media->generated_conversions['webp'])
            ? $media->getUrl('webp')
            : $media->getUrl();
    }

    $videoUrl = '';
    if (!empty($configMedia['background_video'])) {
        $videoUrl = $configMedia['background_video']->getUrl();
    }

    $hasBg = $videoUrl || $bgUrl || $backgroundColor;

    $classes = ['widget--hero'];
    if ($fullscreen)  $classes[] = 'hero--fullscreen';
    if ($overlapNav)  $classes[] = 'hero--overlap-nav';
    if ($showScroll)  $classes[] = 'hero--has-scroll';
    $classes[] = 'hero--pos-' . ($position ?: 'center-center');
    if (!$fullscreen) $classes[] = 'hero--height-' . str_replace('rem', '', $minHeight);
@endphp

<div class="{{ implode(' ', $classes) }}" style="--hero-bg: {{ $backgroundColor ? e($backgroundColor) . ' ' : '' }}url('{{ $bgUrl }}') center/cover no-repeat; --hero-overlay: {{ $overlayOpacity }};{{ $textColor ? ' color:' . e($textColor) . ';' : '' }}">

    @if ($videoUrl)
        <video class="hero-video" autoplay muted loop playsinline preload="auto" aria-hidden="true">
            <source src="{{ $videoUrl }}" type="{{ $configMedia['background_video']->mime_type }}">
        </video>
    @elseif ($bgUrl || $backgroundColor)
        <div class="hero-bg"></div>
    @endif

    @if ($hasBg)
        <div class="hero-overlay"></div>
    @endif

    <div class="hero-body">
        <div class="site-container">
            <div class="hero-inner">
            @if ($content)
                <div class="hero-content {{ $hasBg ? 'hero-content--on-image' : '' }}" data-config-key="content" data-config-type="richtext">
                    {!! $content !!}
                </div>
            @endif

            @if (!empty($ctas))
                <div class="hero-ctas">
                    @include('widgets.components.buttons', [
                        'buttons'   => $ctas,
                        'alignment' => str_contains($position, 'center') ? 'center' : (str_contains($position, 'right') ? 'right' : 'left'),
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
