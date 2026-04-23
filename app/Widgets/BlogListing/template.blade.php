@php
    $heading         = $config['heading'] ?? '';
    $defaultTemplate = '<p>{{item.image}}</p><h3><a href="{{item.url}}">{{item.title}}</a></h3><h4>{{item.date}}</h4><p>{{item.excerpt}}</p>';
    $rawTemplate = $config['content_template'] ?? '';
    $contentTemplate = trim(strip_tags($rawTemplate)) !== '' ? $rawTemplate : $defaultTemplate;
    $columns         = max(1, min(6, (int) ($config['columns'] ?? 3)));
    $perPage         = max(1, (int) ($config['items_per_page'] ?? 10));
    $showSearch      = $config['show_search'] ?? false;
    $sortDefault     = $config['sort_default'] ?? 'newest';
    $effect          = in_array($config['effect'] ?? '', ['slide', 'fade']) ? $config['effect'] : 'slide';
    $gap             = (int) ($config['gap'] ?? 24);

    $items = $widgetData['items'] ?? [];

    // Pre-render cards server-side using token replacement
    $renderedCards = [];
    foreach ($items as $item) {
        $card = $contentTemplate;
        foreach ($item as $key => $value) {
            if ($key === 'image') {
                $imgHtml = $value ? '<img src="' . e($value) . '" alt="' . e($item['title']) . '" loading="lazy">' : '';
                $card = str_replace('{{item.image}}', $imgHtml, $card);
            } else {
                $card = str_replace('{{item.' . $key . '}}', e((string) $value), $card);
            }
        }
        $card = preg_replace('/\{\{[^}]+\}\}/', '', $card);
        $renderedCards[] = trim($card);
    }

    // Group cards into pages for initial server render
    $pages = array_chunk($renderedCards, $perPage);

    $listingData = json_encode([
        'cards'       => $renderedCards,
        'items'       => $items,
        'columns'     => $columns,
        'perPage'     => $perPage,
        'sortDefault' => $sortDefault,
        'effect'      => $effect,
        'gap'         => $gap,
    ]);
@endphp

<div
    class="widget-blog-listing"
    x-data="NPWidgets.blogListing()"
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
