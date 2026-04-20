@php
    $slides = $collectionData['slides'] ?? [];
    $imageField = $config['image_field'] ?? '';
    $captionTemplate = $config['caption_template'] ?? '{{item.title}}';
    $objectFit = in_array($config['object_fit'] ?? '', ['cover', 'contain']) ? $config['object_fit'] : 'cover';
    $autoplay = $config['autoplay'] ?? true;
    $interval = (int) ($config['interval'] ?? 5000);
    $loop = $config['loop'] ?? true;
    $showPagination = $config['pagination'] ?? true;
    $showNavigation = $config['navigation'] ?? true;
    $slidesPerView = (int) ($config['slides_per_view'] ?? 1);
    $effect = in_array($config['effect'] ?? '', ['slide', 'fade']) ? $config['effect'] : 'slide';
    $speed = (int) ($config['speed'] ?? 300);
    $textColor = $config['caption_text_color'] ?? '';
    $linkColor = $config['caption_link_color'] ?? '';

    // Fade only works with 1 slide per view
    if ($effect === 'fade') {
        $slidesPerView = 1;
    }
@endphp

@if (count($slides) > 0)
    <div
        x-data="{
            swiper: null,
            init() {
                const modules = [];
                @if($showNavigation) modules.push(window.SwiperModules.Navigation); @endif
                @if($showPagination) modules.push(window.SwiperModules.Pagination); @endif
                @if($autoplay) modules.push(window.SwiperModules.Autoplay); @endif
                @if($effect === 'fade') modules.push(window.SwiperModules.EffectFade); @endif

                this.swiper = new window.Swiper(this.$refs.container, {
                    modules: modules,
                    slidesPerView: {{ $slidesPerView }},
                    spaceBetween: {{ $slidesPerView > 1 ? 16 : 0 }},
                    loop: {{ $loop ? 'true' : 'false' }},
                    speed: {{ $speed }},
                    effect: '{{ $effect }}',
                    @if($effect === 'fade')
                    fadeEffect: { crossFade: true },
                    @endif
                    @if($autoplay)
                    autoplay: { delay: {{ $interval }}, disableOnInteraction: false },
                    @endif
                    @if($showPagination)
                    pagination: { el: this.$refs.pagination, clickable: true },
                    @endif
                    @if($showNavigation)
                    navigation: { nextEl: this.$refs.next, prevEl: this.$refs.prev },
                    @endif
                });
            }
        }"
        class="widget-carousel"
        @if($linkColor || $textColor)
        style="{{ $textColor ? 'color: ' . e($textColor) . ';' : '' }}{{ $linkColor ? '--carousel-link-color: ' . e($linkColor) . ';' : '' }}"
        @endif
    >
        <div x-ref="container" class="swiper">
            <div class="swiper-wrapper">
                @foreach ($slides as $slide)
                    @php
                        $media = $slide['_media'] ?? [];
                        $slideImage = $imageField ? ($media[$imageField] ?? null) : null;
                        if (! $slideImage) {
                            foreach ($media as $m) {
                                if ($m) { $slideImage = $m; break; }
                            }
                        }

                        // Token replacement for caption
                        $caption = $captionTemplate;
                        foreach ($slide as $key => $value) {
                            if ($key === '_media' || !is_string($value)) continue;
                            $caption = str_replace('{{item.' . $key . '}}', e($value), $caption);
                        }
                        // Remove any unmatched tokens
                        $caption = preg_replace('/\{\{[^}]+\}\}/', '', $caption);
                        $caption = trim($caption);
                    @endphp
                    <div class="swiper-slide">
                        @if ($slideImage)
                            <x-picture
                                :media="$slideImage"
                                alt="{{ $caption }}"
                                style="object-fit: {{ $objectFit }}; width: 100%; height: 100%; display: block;"
                            />
                        @endif
                        @if ($caption)
                            <div class="carousel-caption">
                                <span>{!! $caption !!}</span>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($showPagination)
                <div x-ref="pagination" class="swiper-pagination"></div>
            @endif

            @if ($showNavigation)
                <button x-ref="prev" class="swiper-button-prev" type="button" aria-label="Previous slide"></button>
                <button x-ref="next" class="swiper-button-next" type="button" aria-label="Next slide"></button>
            @endif
        </div>
    </div>
@endif
