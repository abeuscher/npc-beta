@php
    $logos = $widgetData['items'] ?? [];
    $imageField = $config['image_field'] ?? '';
    $nameField = $config['name_field'] ?? '';
    $displayMode = in_array($config['display_mode'] ?? '', ['static', 'carousel', 'smooth', 'flipper']) ? $config['display_mode'] : 'static';
    $showName = $config['show_name'] ?? false;
    $bgColor = $config['container_background_color'] ?? '#ffffff';
    $logosPerRow = max(1, (int) ($config['logos_per_row'] ?? 5));
    $logoMaxHeight = max(20, (int) ($config['logo_max_height'] ?? 150));
    $carouselDuration = (int) ($config['carousel_duration'] ?? 3000);
    $flipDuration = (int) ($config['flip_duration'] ?? 4000);

    // Resolve media for each logo
    $resolvedLogos = [];
    foreach ($logos as $logo) {
        $media = $logo['_media'] ?? [];
        $logoImage = $imageField ? ($media[$imageField] ?? null) : null;
        if (! $logoImage) {
            foreach ($media as $m) {
                if ($m) { $logoImage = $m; break; }
            }
        }
        $name = $nameField ? ($logo[$nameField] ?? '') : '';
        if ($logoImage) {
            $resolvedLogos[] = ['media' => $logoImage, 'name' => $name];
        }
    }
@endphp

@if (count($resolvedLogos) > 0)
    @if ($displayMode === 'carousel')
        {{-- Carousel mode — Swiper.js, automatic, no user controls --}}
        <div
            x-data="{
                swiper: null,
                init() {
                    this.swiper = new window.Swiper(this.$refs.container, {
                        modules: [window.SwiperModules.Autoplay],
                        slidesPerView: {{ $logosPerRow }},
                        spaceBetween: {{ (int) ($config['gap'] ?? 16) }},
                        loop: true,
                        autoplay: { delay: {{ $carouselDuration }}, disableOnInteraction: false },
                        allowTouchMove: false,
                        breakpoints: {
                            0: { slidesPerView: Math.min(2, {{ $logosPerRow }}) },
                            576: { slidesPerView: Math.min(3, {{ $logosPerRow }}) },
                            768: { slidesPerView: {{ $logosPerRow }} }
                        }
                    });
                }
            }"
            class="widget-logo-garden widget-logo-garden--carousel"
            style="--logo-container-bg: {{ e($bgColor) }}; --logo-max-height: {{ $logoMaxHeight }}px;"
        >
            <div x-ref="container" class="swiper">
                <div class="swiper-wrapper">
                    @foreach ($resolvedLogos as $logo)
                        <div class="swiper-slide">
                            <div class="logo-garden__cell">
                                <x-picture
                                    :media="$logo['media']"
                                    alt="{{ e($logo['name']) }}"
                                    class="logo-garden__img"
                                />
                                @if ($showName && $logo['name'])
                                    <span class="logo-garden__name">{{ $logo['name'] }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

    @elseif ($displayMode === 'smooth')
        {{-- Smooth scroll mode — continuous ribbon loop --}}
        <div
            x-data="{
                swiper: null,
                init() {
                    this.swiper = new window.Swiper(this.$refs.container, {
                        modules: [window.SwiperModules.Autoplay, window.SwiperModules.FreeMode],
                        slidesPerView: {{ $logosPerRow }},
                        spaceBetween: {{ (int) ($config['gap'] ?? 16) }},
                        loop: true,
                        freeMode: true,
                        speed: {{ $carouselDuration }},
                        autoplay: { delay: 0, disableOnInteraction: false },
                        allowTouchMove: false,
                        breakpoints: {
                            0: { slidesPerView: Math.min(2, {{ $logosPerRow }}) },
                            576: { slidesPerView: Math.min(3, {{ $logosPerRow }}) },
                            768: { slidesPerView: {{ $logosPerRow }} }
                        }
                    });
                }
            }"
            class="widget-logo-garden widget-logo-garden--carousel"
            style="--logo-container-bg: {{ e($bgColor) }}; --logo-max-height: {{ $logoMaxHeight }}px;"
        >
            <div x-ref="container" class="swiper">
                <div class="swiper-wrapper">
                    @foreach ($resolvedLogos as $logo)
                        <div class="swiper-slide">
                            <div class="logo-garden__cell">
                                <x-picture
                                    :media="$logo['media']"
                                    alt="{{ e($logo['name']) }}"
                                    class="logo-garden__img"
                                />
                                @if ($showName && $logo['name'])
                                    <span class="logo-garden__name">{{ $logo['name'] }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

    @elseif ($displayMode === 'flipper')
        {{-- Flipper mode — CSS 3D flip with Alpine.js --}}
        <div
            class="widget-logo-garden widget-logo-garden--flipper"
            style="--logo-columns: {{ $logosPerRow }}; --logo-container-bg: {{ e($bgColor) }}; --logo-max-height: {{ $logoMaxHeight }}px;"
        >
            @foreach ($resolvedLogos as $i => $logo)
                @php
                    $nextLogo = $resolvedLogos[($i + 1) % count($resolvedLogos)];
                    $delay = $i * 200;
                @endphp
                <div
                    x-data="{ flipped: false }"
                    x-init="setInterval(() => { flipped = !flipped }, {{ $flipDuration }})"
                    class="logo-garden__flip-container"
                    style="animation-delay: {{ $delay }}ms;"
                >
                    <div class="logo-garden__flipper" :class="{ 'is-flipped': flipped }">
                        <div class="logo-garden__flip-face logo-garden__flip-front">
                            <x-picture
                                :media="$logo['media']"
                                alt="{{ e($logo['name']) }}"
                                class="logo-garden__img"
                            />
                            @if ($showName && $logo['name'])
                                <span class="logo-garden__name">{{ $logo['name'] }}</span>
                            @endif
                        </div>
                        <div class="logo-garden__flip-face logo-garden__flip-back">
                            <x-picture
                                :media="$nextLogo['media']"
                                alt="{{ e($nextLogo['name']) }}"
                                class="logo-garden__img"
                            />
                            @if ($showName && $nextLogo['name'])
                                <span class="logo-garden__name">{{ $nextLogo['name'] }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

    @else
        {{-- Static grid mode --}}
        <div
            class="widget-logo-garden widget-logo-garden--static"
            style="--logo-columns: {{ $logosPerRow }}; --logo-container-bg: {{ e($bgColor) }}; --logo-max-height: {{ $logoMaxHeight }}px;"
        >
            @foreach ($resolvedLogos as $logo)
                <div class="logo-garden__cell">
                    <x-picture
                        :media="$logo['media']"
                        alt="{{ e($logo['name']) }}"
                        class="logo-garden__img"
                        title="{{ e($logo['name']) }}"
                    />
                    @if ($showName && $logo['name'])
                        <span class="logo-garden__name">{{ $logo['name'] }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
@endif
