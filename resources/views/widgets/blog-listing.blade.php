@php
    $posts = $pageContext->posts();
    $blogPrefix = config('site.blog_prefix', 'news');

    $heading         = $config['heading'] ?? '';
    $defaultTemplate = '<p>{{image}}</p><h3><a href="{{url}}">{{title}}</a></h3><h4>{{date}}</h4><p>{{excerpt}}</p>';
    $rawTemplate = $config['content_template'] ?? '';
    $contentTemplate = trim(strip_tags($rawTemplate)) !== '' ? $rawTemplate : $defaultTemplate;
    $columns         = max(1, min(6, (int) ($config['columns'] ?? 3)));
    $perPage         = max(1, (int) ($config['items_per_page'] ?? 10));
    $showSearch      = $config['show_search'] ?? false;
    $sortDefault     = $config['sort_default'] ?? 'newest';
    $effect          = in_array($config['effect'] ?? '', ['slide', 'fade']) ? $config['effect'] : 'slide';

    // Serialize posts for Alpine
    $items = $posts->map(function ($post) use ($blogPrefix) {
        $excerpt = \Illuminate\Support\Str::limit(strip_tags($post->meta_description ?? ''), 160);

        $thumbnailUrl = $post->getFirstMediaUrl('post_thumbnail', 'webp')
            ?: $post->getFirstMediaUrl('post_thumbnail');

        return [
            'title'       => $post->title,
            'slug'        => $post->slug,
            'url'         => url('/' . $post->slug),
            'date'        => $post->published_at?->format('F j, Y') ?? '',
            'date_iso'    => $post->published_at?->toIso8601String() ?? '',
            'excerpt'     => $excerpt,
            'image'       => $thumbnailUrl,
        ];
    })->values()->all();

    // Pre-render cards server-side using token replacement
    $renderedCards = [];
    foreach ($items as $item) {
        $card = $contentTemplate;
        foreach ($item as $key => $value) {
            if ($key === 'image') {
                $imgHtml = $value ? '<img src="' . e($value) . '" alt="' . e($item['title']) . '" loading="lazy">' : '';
                $card = str_replace('{{image}}', $imgHtml, $card);
            } else {
                $card = str_replace('{{' . $key . '}}', e((string) $value), $card);
            }
        }
        $card = preg_replace('/\{\{[^}]+\}\}/', '', $card);
        $renderedCards[] = trim($card);
    }

    // Group cards into pages for initial server render
    $pages = array_chunk($renderedCards, $perPage);

    $listingData = json_encode([
        'cards' => $renderedCards,
        'items' => $items,
        'columns' => $columns,
        'perPage' => $perPage,
        'sortDefault' => $sortDefault,
        'effect' => $effect,
    ]);
@endphp

<div
    class="widget-blog-listing"
    x-data="{
        swiper: null,
        search: '',
        cfg: null,
        init() {
            this.cfg = JSON.parse(this.$refs.listingData.textContent);
            let swiperEl = this.$refs.swiperEl;
            if (!swiperEl || !window.Swiper) return;

            let modules = [window.SwiperModules.Navigation, window.SwiperModules.Pagination];
            if (this.cfg.effect === 'fade') modules.push(window.SwiperModules.EffectFade);

            this.swiper = new window.Swiper(swiperEl, this.buildOpts(modules));
        },
        buildOpts(modules) {
            return {
                modules: modules,
                slidesPerView: 1,
                slidesPerGroup: 1,
                spaceBetween: 24,
                effect: this.cfg.effect,
                fadeEffect: this.cfg.effect === 'fade' ? { crossFade: true } : undefined,
                navigation: { nextEl: this.$refs.btnNext, prevEl: this.$refs.btnPrev },
                pagination: {
                    el: this.$refs.pagination,
                    clickable: true,
                    renderBullet: (index, className) => '<span class=&quot;' + className + '&quot;>' + (index + 1) + '</span>',
                },
            };
        },
        rebuildSlides() {
            let indices = this.getFilteredIndices(this.search, this.cfg.sortDefault);
            let swiperEl = this.$refs.swiperEl;
            if (this.swiper) this.swiper.destroy(true, true);

            let wrapper = swiperEl.querySelector('.swiper-wrapper');
            wrapper.innerHTML = '';

            if (indices.length) {
                for (let i = 0; i < indices.length; i += this.cfg.perPage) {
                    let chunk = indices.slice(i, i + this.cfg.perPage).map(idx => this.cfg.cards[idx]);
                    wrapper.innerHTML += this.buildSlideHtml(chunk);
                }
                this.$refs.emptyMsg.style.display = 'none';
            } else {
                this.$refs.emptyMsg.style.display = '';
            }

            let modules = [window.SwiperModules.Navigation, window.SwiperModules.Pagination];
            if (this.cfg.effect === 'fade') modules.push(window.SwiperModules.EffectFade);
            this.swiper = new window.Swiper(swiperEl, this.buildOpts(modules));
        },
        buildSlideHtml(cardHtmlArray) {
            let cards = cardHtmlArray.map(html => '<article class=&quot;content-card&quot;>' + html + '</article>').join('');
            return '<div class=&quot;swiper-slide&quot;><div class=&quot;content-grid&quot; style=&quot;grid-template-columns:repeat(' + this.cfg.columns + ',1fr);&quot;>' + cards + '</div></div>';
        },
        getFilteredIndices(query, sortBy) {
            let indices = this.cfg.items.map((_, i) => i);
            if (query && query.trim()) {
                let q = query.toLowerCase();
                indices = indices.filter(i => {
                    let item = this.cfg.items[i];
                    return item.title.toLowerCase().includes(q)
                        || item.excerpt.toLowerCase().includes(q)
                        || item.date.toLowerCase().includes(q);
                });
            }
            indices.sort((a, b) => {
                let ia = this.cfg.items[a], ib = this.cfg.items[b];
                switch (sortBy) {
                    case 'oldest':   return (ia.date_iso || '').localeCompare(ib.date_iso || '');
                    case 'title_az': return ia.title.localeCompare(ib.title);
                    case 'title_za': return ib.title.localeCompare(ia.title);
                    default:         return (ib.date_iso || '').localeCompare(ia.date_iso || '');
                }
            });
            return indices;
        },
    }"
    x-effect="if (cfg && search !== undefined) rebuildSlides()"
>
    <script x-ref="listingData" type="application/json">{!! $listingData !!}</script>

    <div class="site-container">
        @if ($heading)
            <h2 class="widget-blog-listing__heading">{{ $heading }}</h2>
        @endif

        @if ($showSearch)
            <div class="widget-blog-listing__controls">
                <input
                    type="search"
                    x-model.debounce.300ms="search"
                    placeholder="Search posts..."
                    class="widget-blog-listing__search"
                    aria-label="Search posts"
                >
            </div>
        @endif

        <div x-ref="swiperEl" class="swiper widget-blog-listing__swiper">
            <div class="swiper-wrapper">
                @foreach ($pages as $page)
                    <div class="swiper-slide">
                        <div class="content-grid" style="grid-template-columns: repeat({{ $columns }}, 1fr);">
                            @foreach ($page as $card)
                                <article class="content-card">{!! $card !!}</article>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <nav class="widget-listing__pager" aria-label="Pagination">
            <button x-ref="btnPrev" class="widget-listing__nav" type="button" aria-label="Previous page">&lsaquo;</button>
            <div x-ref="pagination" class="widget-listing__bullets"></div>
            <button x-ref="btnNext" class="widget-listing__nav" type="button" aria-label="Next page">&rsaquo;</button>
        </nav>

        <p x-ref="emptyMsg" class="widget-blog-listing__empty" style="display:none;">No posts found.</p>
    </div>
</div>
