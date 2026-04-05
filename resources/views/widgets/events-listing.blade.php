@php
    $eventsPrefix = config('site.events_prefix', 'events');
    $events = $pageContext->upcomingEvents();

    $heading         = $config['heading'] ?? '';
    $defaultTemplate = '<p>{{image}}</p><h3><a href="{{url}}">{{title}}</a></h3><h4>{{date}}</h4><p>{{location}}</p><p>{{price_badge}}</p>';
    $rawTemplate = $config['content_template'] ?? '';
    $contentTemplate = trim(strip_tags($rawTemplate)) !== '' ? $rawTemplate : $defaultTemplate;
    $columns         = max(1, min(6, (int) ($config['columns'] ?? 3)));
    $perPage         = max(1, (int) ($config['items_per_page'] ?? 10));
    $showSearch      = $config['show_search'] ?? false;
    $sortDefault     = $config['sort_default'] ?? 'soonest';
    $effect          = in_array($config['effect'] ?? '', ['slide', 'fade']) ? $config['effect'] : 'slide';
    $bgColor         = $config['background_color'] ?? '';
    $textColor       = $config['text_color'] ?? '';

    // Serialize events for Alpine
    $items = $events->map(function ($event) use ($eventsPrefix) {
        $locationParts = array_filter([
            $event->address_line_1,
            $event->city,
            $event->state,
        ]);
        $location = implode(', ', $locationParts);

        $thumbnailUrl = $event->getFirstMediaUrl('event_thumbnail', 'webp')
            ?: $event->getFirstMediaUrl('event_thumbnail');

        return [
            'title'       => $event->title,
            'slug'        => $event->slug,
            'url'         => $event->landingPage
                ? url('/' . $event->landingPage->slug)
                : url('/' . $eventsPrefix),
            'date'        => $event->starts_at?->format('D, F j, Y \a\t g:i A') ?? '',
            'date_iso'    => $event->starts_at?->toIso8601String() ?? '',
            'ends_at'     => $event->ends_at?->format('g:i A') ?? '',
            'location'    => $location,
            'is_free'     => $event->is_free,
            'price_badge' => $event->is_free ? '<span class="content-card__badge">Free</span>' : '',
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
            } elseif ($key === 'price_badge') {
                $card = str_replace('{{price_badge}}', $value, $card);
            } elseif (is_string($value)) {
                $card = str_replace('{{' . $key . '}}', e($value), $card);
            }
        }
        $card = preg_replace('/\{\{[^}]+\}\}/', '', $card);
        $renderedCards[] = trim($card);
    }

    // Group cards into pages for initial server render
    $pages = array_chunk($renderedCards, $perPage);

    $widgetId = 'events-listing-' . uniqid();
@endphp

<div
    id="{{ $widgetId }}"
    class="widget-events-listing"
    @if ($bgColor || $textColor)
    style="{{ $bgColor ? 'background-color:' . e($bgColor) . ';' : '' }}{{ $textColor ? 'color:' . e($textColor) . ';' : '' }}"
    @endif
>
    <div class="site-container">
        @if ($heading)
            <h2 class="widget-events-listing__heading">{{ $heading }}</h2>
        @endif

        @if ($showSearch)
            <div class="widget-events-listing__controls" x-data="{ search: '' }" x-effect="$dispatch('events-search', { query: search })">
                <input
                    type="search"
                    x-model.debounce.300ms="search"
                    placeholder="Search events..."
                    class="widget-events-listing__search"
                    aria-label="Search events"
                >
            </div>
        @endif

        <div class="swiper widget-events-listing__swiper">
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

        <p class="widget-events-listing__empty" style="display:none;">No upcoming events. Check back soon.</p>
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
    var emptyEl = el.querySelector('.widget-events-listing__empty');

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
                    || (item.location || '').toLowerCase().indexOf(q) !== -1
                    || item.date.toLowerCase().indexOf(q) !== -1;
            });
        }

        indices.sort(function (a, b) {
            var ia = allItems[a], ib = allItems[b];
            switch (sortBy) {
                case 'furthest':  return (ib.date_iso || '').localeCompare(ia.date_iso || '');
                case 'title_az':  return ia.title.localeCompare(ib.title);
                case 'title_za':  return ib.title.localeCompare(ia.title);
                default:          return (ia.date_iso || '').localeCompare(ib.date_iso || '');
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

    el.addEventListener('events-search', function (e) {
        rebuildSlides(e.detail.query, sortDefault);
    });
});
</script>
