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
    $bgColor         = $config['background_color'] ?? '';
    $textColor       = $config['text_color'] ?? '';

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

    $widgetId = 'blog-listing-' . uniqid();
@endphp

<div
    id="{{ $widgetId }}"
    class="widget-blog-listing"
    @if ($bgColor || $textColor)
    style="{{ $bgColor ? 'background-color:' . e($bgColor) . ';' : '' }}{{ $textColor ? 'color:' . e($textColor) . ';' : '' }}"
    @endif
>
    <div class="site-container">
        @if ($heading)
            <h2 class="widget-blog-listing__heading">{{ $heading }}</h2>
        @endif

        @if ($showSearch)
            <div class="widget-blog-listing__controls" x-data="{ search: '' }" x-effect="$dispatch('blog-search', { query: search })">
                <input
                    type="search"
                    x-model.debounce.300ms="search"
                    placeholder="Search posts..."
                    class="widget-blog-listing__search"
                    aria-label="Search posts"
                >
            </div>
        @endif

        <div class="swiper widget-blog-listing__swiper">
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
            <button class="widget-listing__nav swiper-button-prev" type="button" aria-label="Previous page">&lsaquo;</button>
            <div class="swiper-pagination"></div>
            <button class="widget-listing__nav swiper-button-next" type="button" aria-label="Next page">&rsaquo;</button>
        </nav>

        <p class="widget-blog-listing__empty" style="display:none;">No posts found.</p>
    </div>
</div>

<script>
window.addEventListener('load', function () {
    var el = document.getElementById('{{ $widgetId }}');
    if (!el) return;

    var allCards = @json($renderedCards);
    var allItems = @json($items);
    var columns = {{ $columns }};
    var perPage = {{ $perPage }};
    var sortDefault = '{{ $sortDefault }}';
    var swiperEl = el.querySelector('.swiper');
    var emptyEl = el.querySelector('.widget-blog-listing__empty');

    var effect = '{{ $effect }}';
    var modules = [window.SwiperModules.Navigation, window.SwiperModules.Pagination];
    if (effect === 'fade') modules.push(window.SwiperModules.EffectFade);

    var swiperOpts = {
        modules: modules,
        slidesPerView: 1,
        slidesPerGroup: 1,
        spaceBetween: 24,
        effect: effect,
        fadeEffect: effect === 'fade' ? { crossFade: true } : undefined,
        navigation: {
            nextEl: el.querySelector('.swiper-button-next'),
            prevEl: el.querySelector('.swiper-button-prev')
        },
        pagination: {
            el: el.querySelector('.swiper-pagination'),
            clickable: true,
            renderBullet: function (index, className) {
                return '<span class="' + className + '">' + (index + 1) + '</span>';
            }
        }
    };

    var swiper = new window.Swiper(swiperEl, swiperOpts);

    function buildSlideHtml(cardHtmlArray) {
        var cards = cardHtmlArray.map(function (html) {
            return '<article class="content-card">' + html + '</article>';
        }).join('');
        return '<div class="swiper-slide"><div class="content-grid" style="grid-template-columns:repeat(' + columns + ',1fr);">' + cards + '</div></div>';
    }

    function getFilteredIndices(query, sortBy) {
        var indices = allItems.map(function (_, i) { return i; });

        if (query && query.trim()) {
            var q = query.toLowerCase();
            indices = indices.filter(function (i) {
                var item = allItems[i];
                return item.title.toLowerCase().indexOf(q) !== -1
                    || item.excerpt.toLowerCase().indexOf(q) !== -1
                    || item.date.toLowerCase().indexOf(q) !== -1;
            });
        }

        indices.sort(function (a, b) {
            var ia = allItems[a], ib = allItems[b];
            switch (sortBy) {
                case 'oldest':   return (ia.date_iso || '').localeCompare(ib.date_iso || '');
                case 'title_az': return ia.title.localeCompare(ib.title);
                case 'title_za': return ib.title.localeCompare(ia.title);
                default:         return (ib.date_iso || '').localeCompare(ia.date_iso || '');
            }
        });

        return indices;
    }

    function rebuildSlides(query, sortBy) {
        var indices = getFilteredIndices(query, sortBy);

        swiper.destroy(true, true);

        var wrapper = swiperEl.querySelector('.swiper-wrapper');
        wrapper.innerHTML = '';

        if (indices.length) {
            for (var i = 0; i < indices.length; i += perPage) {
                var chunk = indices.slice(i, i + perPage).map(function (idx) { return allCards[idx]; });
                wrapper.innerHTML += buildSlideHtml(chunk);
            }
            emptyEl.style.display = 'none';
        } else {
            emptyEl.style.display = '';
        }

        swiper = new window.Swiper(swiperEl, swiperOpts);
    }

    el.addEventListener('blog-search', function (e) {
        rebuildSlides(e.detail.query, sortDefault);
    });
});
</script>
