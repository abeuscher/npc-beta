@php
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Str;

    $heading    = $config['heading'] ?? '';
    $items      = $widgetData['items'] ?? [];
    $tz         = config('app.timezone');

    // Layout toggles — orthogonal. Item layout (cards vs side-by-side rows) and
    // day grouping are independent; either one switches off the carousel.
    $sideBySide = (bool) ($config['side_by_side_rows'] ?? false);
    $groupByDay = (bool) ($config['group_by_day'] ?? false);
    $isStatic   = $sideBySide || $groupByDay;

    $showSearch = (bool) ($config['show_search'] ?? false);
    $showFilter = (bool) ($config['show_event_type_filter'] ?? false);

    // Card-layout config (carousel + static cards both use the card template).
    $defaultTemplate = '<p>{{item.image}}</p><h3><a href="{{item.url}}">{{item.title}}</a></h3><h4>{{item.event_date}}</h4><p>{{item.event_time}}</p><p>{{item.location}}</p><p>{{item.price_badge}}</p>';
    $rawTemplate     = $config['content_template'] ?? '';
    $contentTemplate = trim(strip_tags($rawTemplate)) !== '' ? $rawTemplate : $defaultTemplate;
    $columns         = max(1, min(6, (int) ($config['columns'] ?? 3)));
    $perPage         = max(1, (int) ($config['items_per_page'] ?? 10));
    $sortDefault     = $config['sort_default'] ?? 'soonest';
    $effect          = in_array($config['effect'] ?? '', ['slide', 'fade']) ? $config['effect'] : 'slide';
    $gap             = (int) ($config['gap'] ?? 24);

    // Featured event (all layouts) — matched from the already-fetched items by
    // slug, then pulled out of the listing so it isn't shown twice.
    $featuredSlug = (string) ($config['featured_event_slug'] ?? '');
    $featured = null;
    if ($featuredSlug !== '') {
        foreach ($items as $candidate) {
            if (($candidate['slug'] ?? '') === $featuredSlug) {
                $featured = $candidate;
                break;
            }
        }
    }
    $listSource = array_values(array_filter(
        $items,
        fn ($item) => $featured === null || ($item['slug'] ?? '') !== ($featured['slug'] ?? null)
    ));

    // Shared helpers. Art fallback: row/card thumbnail prefers the thumbnail,
    // then the header image; the hero prefers the header image, then thumbnail.
    $thumbFor   = fn ($item) => ($item['image'] ?? '') ?: ($item['header_image'] ?? '');
    $heroImgFor = fn ($item) => ($item['header_image'] ?? '') ?: ($item['image'] ?? '');
    $badgesFor  = function ($item) {
        if (! empty($item['sold_out'])) {
            return [['label' => 'Sold Out', 'mod' => 'soldout']];
        }
        if (! empty($item['is_free'])) {
            return [['label' => 'Free', 'mod' => 'free']];
        }
        return [];
    };
    $tagSlugs = fn ($item) => implode(' ', array_map(fn ($t) => $t['slug'] ?? '', $item['tags'] ?? []));

    // Event-type filter: distinct tags across the listed events.
    $filterTags = [];
    foreach ($listSource as $item) {
        foreach (($item['tags'] ?? []) as $t) {
            if (($t['slug'] ?? '') !== '') {
                $filterTags[$t['slug']] = $t['name'] ?? $t['slug'];
            }
        }
    }
    asort($filterTags);
    $showFilterControl = $showFilter && count($filterTags) > 0;

    // Card token-replacement (shared by carousel + static-cards).
    $renderCard = function ($item) use ($contentTemplate) {
        $card = $contentTemplate;
        foreach ($item as $key => $value) {
            if ($key === 'image') {
                $imgHtml = $value ? '<img src="' . e($value) . '" alt="' . e($item['title']) . '" loading="lazy">' : '';
                $card = str_replace('{{item.image}}', $imgHtml, $card);
            } elseif ($key === 'is_free') {
                continue;
            } elseif (is_scalar($value)) {
                $card = str_replace('{{item.' . $key . '}}', e((string) $value), $card);
            }
        }
        $priceBadge = ! empty($item['is_free']) ? '<span class="content-card__badge">Free</span>' : '';
        $card = str_replace('{{item.price_badge}}', $priceBadge, $card);
        $card = preg_replace('/\{\{[^}]+\}\}/', '', $card);
        return trim($card);
    };

    // Day-heading token template (only when grouping). Styleable rich text.
    // Day tokens are namespaced ({{day.*}}) so they survive WidgetRenderer's
    // page-context token substitution on richtext config (which consumes bare
    // tokens like {{date}}) and reach this replacement intact — the same reason
    // the card template uses {{item.*}}.
    $dayHeadingTemplate = trim(strip_tags((string) ($config['day_heading_template'] ?? ''))) !== ''
        ? $config['day_heading_template']
        : '<h3>{{day.weekday}}, {{day.date}}</h3>';
    $renderDayHeading = function (string $dayKey) use ($dayHeadingTemplate, $tz) {
        $c = Carbon::parse($dayKey)->setTimezone($tz);
        return str_replace(
            ['{{day.weekday}}', '{{day.weekday_short}}', '{{day.month}}', '{{day.number}}', '{{day.year}}', '{{day.date}}'],
            [e($c->format('l')), e($c->format('D')), e($c->format('F')), e($c->format('j')), e($c->format('Y')), e($c->format('F j'))],
            $dayHeadingTemplate
        );
    };

    // Group the listed events. When not grouping, a single heading-less group —
    // one render path covers both.
    $groups = [];
    if ($groupByDay) {
        $byDay = [];
        foreach ($listSource as $item) {
            $startsAt = $item['starts_at'] ?? '';
            if ($startsAt === '') {
                continue;
            }
            $byDay[Carbon::parse($startsAt)->setTimezone($tz)->format('Y-m-d')][] = $item;
        }
        foreach ($byDay as $dayKey => $dayItems) {
            $groups[] = ['heading' => $renderDayHeading($dayKey), 'items' => $dayItems];
        }
    } else {
        $groups[] = ['heading' => null, 'items' => $listSource];
    }

    // Carousel data (only built for the carousel path) — from listSource so the
    // featured event isn't duplicated in the carousel.
    $renderedCards = array_map($renderCard, $listSource);
    $pages = array_chunk($renderedCards, $perPage);
    $listingData = json_encode([
        'cards'       => $renderedCards,
        'items'       => $listSource,
        'columns'     => $columns,
        'perPage'     => $perPage,
        'sortDefault' => $sortDefault,
        'effect'      => $effect,
        'gap'         => $gap,
    ]);

    // ItemList / Event schema (canonical + OG are page-level). Includes the
    // featured event.
    $ld = ['@context' => 'https://schema.org', '@type' => 'ItemList', 'itemListElement' => []];
    $pos = 1;
    foreach ($items as $item) {
        if (($item['starts_at'] ?? '') === '') {
            continue;
        }
        $ev = ['@type' => 'Event', 'name' => $item['title'] ?? '', 'startDate' => $item['starts_at']];
        if (($item['url'] ?? '') !== '') {
            $ev['url'] = $item['url'];
        }
        $ldImg = $heroImgFor($item);
        if ($ldImg !== '') {
            $ev['image'] = $ldImg;
        }
        $ldLoc = ($item['event_location'] ?? '') ?: ($item['location'] ?? '');
        if ($ldLoc !== '') {
            $ev['location'] = ['@type' => 'Place', 'name' => $ldLoc];
        }
        $ld['itemListElement'][] = ['@type' => 'ListItem', 'position' => $pos++, 'item' => $ev];
    }

    $hasControls = ($showSearch && ! $isStatic) || $showFilterControl;
    $rootClass = 'widget-events-listing' . ($isStatic ? ' widget-events-listing--list' : '');
@endphp

<div
    class="{{ $rootClass }}"
    @unless ($isStatic)
        x-data="NPWidgets.eventsListing()"
        x-effect="if (cfg && search !== undefined) { typeFilter; search; rebuildSlides() }"
    @endunless
>
    @unless ($isStatic)
        <script x-ref="listingData" type="application/json">{!! $listingData !!}</script>
    @endunless

    <div class="site-container">
        @include('widget-shared.inline-prose', ['tag' => 'h2', 'class' => 'widget-events-listing__heading', 'key' => 'heading', 'type' => 'text', 'value' => $heading, 'label' => 'Heading'])

        @if ($featured)
            @php
                $featImg     = $heroImgFor($featured);
                $featBadges  = $badgesFor($featured);
                $featExcerpt = Str::limit(trim(strip_tags((string) ($featured['description'] ?? ''))), 220);
                $featUrl     = $featured['url'] ?? '';
            @endphp
            <article class="events-featured" data-tour="events-index.featured">
                <a class="events-featured__media" @if ($featUrl !== '') href="{{ $featUrl }}" @endif>
                    @if ($featImg !== '')
                        <img src="{{ $featImg }}" alt="{{ $featured['title'] ?? '' }}" loading="lazy">
                    @else
                        <span class="events-featured__placeholder" aria-hidden="true">{{ $featured['event_date'] ?? '' }}</span>
                    @endif
                </a>
                <div class="events-featured__body">
                    <p class="events-featured__eyebrow">Featured event</p>
                    <h3 class="events-featured__title">
                        @if ($featUrl !== '')
                            <a href="{{ $featUrl }}">{{ $featured['title'] ?? '' }}</a>
                        @else
                            {{ $featured['title'] ?? '' }}
                        @endif
                    </h3>
                    <p class="events-featured__meta">{{ $featured['event_date'] ?? '' }}@if (($featured['event_time'] ?? '') !== '') · {{ $featured['event_time'] }}@endif</p>
                    @if (($featured['location'] ?? '') !== '')
                        <p class="events-featured__location">{{ $featured['location'] }}</p>
                    @endif
                    @if (count($featBadges))
                        <p class="events-list__badges">
                            @foreach ($featBadges as $badge)
                                <span class="events-list__badge events-list__badge--{{ $badge['mod'] }}">{{ $badge['label'] }}</span>
                            @endforeach
                        </p>
                    @endif
                    @if ($featExcerpt !== '')
                        <p class="events-featured__excerpt">{{ $featExcerpt }}</p>
                    @endif
                </div>
            </article>
        @endif

        @if ($hasControls)
            <div class="widget-events-listing__controls">
                @if ($showSearch && ! $isStatic)
                    <input
                        type="search"
                        x-model.debounce.300ms="search"
                        placeholder="Search events..."
                        class="widget-events-listing__search"
                        aria-label="Search events"
                    >
                @endif

                @if ($showFilterControl)
                    <select
                        class="widget-events-listing__filter"
                        data-tour="events-index.filters"
                        aria-label="Filter by event type"
                        @if ($isStatic) data-type-filter @else x-model="typeFilter" @endif
                    >
                        <option value="">All event types</option>
                        @foreach ($filterTags as $slug => $name)
                            <option value="{{ $slug }}">{{ $name }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
        @endif

        @if ($isStatic)
            <div class="events-list" data-tour="events-index.list">
                @if (count($listSource) === 0)
                    <p class="events-list__empty">No upcoming events. Check back soon.</p>
                @else
                    @foreach ($groups as $group)
                        <section class="events-list__group" @if ($group['heading'] !== null) data-day-group @endif>
                            @if ($group['heading'] !== null)
                                <div class="events-list__day-heading">{!! $group['heading'] !!}</div>
                            @endif

                            @if ($sideBySide)
                                <ul class="events-list__rows" style="grid-template-columns: repeat({{ $columns }}, 1fr);">
                                    @foreach ($group['items'] as $item)
                                        @include('widgets::EventsListing.row', ['item' => $item])
                                    @endforeach
                                </ul>
                            @else
                                <div class="content-grid" style="grid-template-columns: repeat({{ $columns }}, 1fr);">
                                    @foreach ($group['items'] as $item)
                                        <article class="content-card" data-event-row data-tags="{{ $tagSlugs($item) }}">{!! $renderCard($item) !!}</article>
                                    @endforeach
                                </div>
                            @endif
                        </section>
                    @endforeach
                @endif
                <p class="events-list__empty" data-filter-empty hidden>No events match this filter.</p>
            </div>
        @else
            <div x-ref="swiperEl" class="swiper widget-events-listing__swiper" data-tour="events-index.list">
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

            <p x-ref="emptyMsg" class="widget-events-listing__empty" style="display:none;">No upcoming events. Check back soon.</p>
        @endif

        @if (count($ld['itemListElement']))
            <script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif
    </div>
</div>
