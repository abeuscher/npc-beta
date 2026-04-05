@php
    $products = \App\Services\WidgetDataResolver::resolveProducts([
        'limit' => $config['limit'] ?? null,
    ]);
    $heading = $config['heading'] ?? '';
    $showNavigation = $config['navigation'] ?? false;
    $showPagination = $config['pagination'] ?? false;
    $autoplay = $config['autoplay'] ?? false;
    $interval = (int) ($config['interval'] ?? 5000);
    $bgColor = $config['background_color'] ?? '#000000';
    $textColor = $config['text_color'] ?? '#ffffff';
    $successPage = $config['success_page'] ?? '';
@endphp

@if (count($products) > 0)
    <div
        x-data="{
            swiper: null,
            init() {
                const modules = [window.SwiperModules.EffectCoverflow];
                @if($showNavigation) modules.push(window.SwiperModules.Navigation); @endif
                @if($showPagination) modules.push(window.SwiperModules.Pagination); @endif
                @if($autoplay) modules.push(window.SwiperModules.Autoplay); @endif

                this.swiper = new window.Swiper(this.$refs.container, {
                    modules: modules,
                    effect: 'coverflow',
                    centeredSlides: true,
                    slidesPerView: 'auto',
                    coverflowEffect: {
                        rotate: 30,
                        stretch: 0,
                        depth: 100,
                        modifier: 1,
                        slideShadows: false,
                    },
                    loop: {{ count($products) > 2 ? 'true' : 'false' }},
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
        class="widget-product-carousel"
        style="background-color: {{ e($bgColor) }}; color: {{ e($textColor) }}; --carousel-bg: {{ e($bgColor) }};"
    >
        <div class="widget-product-carousel__fade-left" aria-hidden="true"></div>
        <div class="widget-product-carousel__fade-right" aria-hidden="true"></div>

        @if ($heading)
            <h2 class="widget-product-carousel__heading">{{ $heading }}</h2>
        @endif

        <div x-ref="container" class="swiper">
            <div class="swiper-wrapper">
                @foreach ($products as $product)
                    <div class="swiper-slide product-slide">
                        @if ($product['image_url'])
                            <div class="product-slide__image">
                                <img src="{{ $product['image_url'] }}" alt="{{ $product['name'] }}" loading="lazy">
                            </div>
                            <div class="product-slide__reflection" aria-hidden="true">
                                <img src="{{ $product['image_url'] }}" alt="">
                            </div>
                        @else
                            <div class="product-slide__image product-slide__image--placeholder">
                                <svg xmlns="http://www.w3.org/2000/svg" class="product-slide__placeholder-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2 1m2-1l-2-1m2 1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21l-2-1m2 1l2-1m-2 1v-2.5M6 18l-2-1v-2.5M18 18l2-1v-2.5"/>
                                </svg>
                            </div>
                        @endif

                        <h3 class="product-slide__name">{{ $product['name'] }}</h3>

                        @if ($product['description'])
                            <p class="product-slide__description">{{ $product['description'] }}</p>
                        @endif

                        <div class="product-slide__prices">
                            @foreach ($product['prices'] as $price)
                                <form method="POST" action="{{ route('products.checkout') }}">
                                    @csrf
                                    <input type="hidden" name="product_price_id" value="{{ $price['id'] }}">
                                    @if ($successPage)
                                        <input type="hidden" name="success_page" value="{{ $successPage }}">
                                    @endif
                                    <button type="submit" class="btn btn--primary product-slide__buy-btn">
                                        @if ($price['amount'] > 0)
                                            {{ $price['label'] }} &mdash; ${{ number_format($price['amount'], 2) }}
                                        @else
                                            {{ $price['label'] }} &mdash; Free
                                        @endif
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($showPagination)
                <div x-ref="pagination" class="swiper-pagination"></div>
            @endif
            @if ($showNavigation)
                <button x-ref="prev" class="swiper-button-prev" type="button"></button>
                <button x-ref="next" class="swiper-button-next" type="button"></button>
            @endif
        </div>
    </div>
@endif
