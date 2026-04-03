@php
    $content        = $config['content'] ?? '';
    $overlayOpacity = max(0, min(100, (int) ($config['overlay_opacity'] ?? 50))) / 100;
    $minHeight      = in_array($config['min_height'] ?? '', ['16rem', '24rem', '32rem', '40rem']) ? $config['min_height'] : '24rem';
    $ctas           = $config['ctas'] ?? [];
    $overlapNav     = ($config['overlap_nav'] ?? false) == true;
    $position       = $config['text_position'] ?? 'center-center';

    $bgUrl = '';
    if (!empty($configMedia['background_image'])) {
        $media = $configMedia['background_image'];
        $bgUrl = !empty($media->generated_conversions['webp'])
            ? $media->getUrl('webp')
            : $media->getUrl();
    }

    // Map position value to flex alignment classes
    $positionMap = [
        'top-left'       => 'items-start justify-start text-left',
        'top-center'     => 'items-start justify-center text-center',
        'top-right'      => 'items-start justify-end text-right',
        'center-left'    => 'items-center justify-start text-left',
        'center-center'  => 'items-center justify-center text-center',
        'center-right'   => 'items-center justify-end text-right',
        'bottom-left'    => 'items-end justify-start text-left',
        'bottom-center'  => 'items-end justify-center text-center',
        'bottom-right'   => 'items-end justify-end text-right',
    ];
    $positionClasses = $positionMap[$position] ?? $positionMap['center-center'];

    // Derive button alignment from text position
    $buttonAlignment = str_contains($positionClasses, 'text-center')
        ? 'justify-center'
        : (str_contains($positionClasses, 'text-right') ? 'justify-end' : 'justify-start');
@endphp

<div class="widget--hero relative overflow-hidden" style="min-height: {{ $minHeight }}{{ $overlapNav ? '; margin-top: -4.5rem' : '' }}">

    @if ($bgUrl)
        <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('{{ $bgUrl }}')"></div>
    @endif

    @if ($bgUrl)
        <div class="absolute inset-0 bg-black" style="opacity: {{ $overlayOpacity }}"></div>
    @endif

    <div class="relative z-10 flex flex-col {{ $positionClasses }} w-full h-full p-8 md:p-12 {{ $overlapNav ? 'pt-24' : '' }}" style="min-height: {{ $minHeight }}">
        <div class="max-w-2xl">
            @if ($content)
                <div class="hero-content {{ $bgUrl ? 'text-white' : '' }}">
                    {!! $content !!}
                </div>
            @endif

            @if (!empty($ctas))
                <div class="mt-6">
                    @include('widgets.components.buttons', ['buttons' => $ctas, 'alignment' => $buttonAlignment])
                </div>
            @endif
        </div>
    </div>

</div>
