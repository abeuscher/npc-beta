@php
    use Illuminate\Support\Carbon;

    $heading         = $config['heading'] ?? '';
    $listMode        = in_array($config['list_mode'] ?? 'day', ['day', 'month'], true) ? $config['list_mode'] : 'day';
    $showDescription = (bool) ($config['show_description'] ?? true);
    $items           = $widgetData['items'] ?? [];
    $tz              = config('app.timezone');

    // Optional operator-set scroll cap: blank = list grows with its content;
    // a pixel value caps the list and scrolls within it. Serves both the
    // no-scroll and no-jump preferences.
    $maxHeightPx = (int) ($config['events_max_height'] ?? 0);
    $eventsStyle = $maxHeightPx > 0 ? 'max-height:' . $maxHeightPx . 'px;overflow-y:auto;' : '';

    // Parse each event once to its app-tz day. Build the density map plus the
    // per-day and per-month event buckets — all server-side. Items arrive
    // ordered starts_at asc, so the buckets stay chronological.
    $density = [];
    $byDay   = []; // 'Y-m-d' => [ ['title','url','time'], ... ]
    $events  = []; // flat, chronological, with 'ym' + display bits
    foreach ($items as $item) {
        $startsAt = $item['starts_at'] ?? '';
        if ($startsAt === '') {
            continue;
        }
        $c = Carbon::parse($startsAt)->setTimezone($tz);
        $dayKey = $c->format('Y-m-d');
        $density[$dayKey] = ($density[$dayKey] ?? 0) + 1;
        $row = [
            'day'              => $dayKey,
            'ym'               => $c->format('Y-m'),
            'title'            => $item['title'] ?? '',
            'url'              => $item['url'] ?? '',
            'time'             => $item['event_time'] ?? '',
            'event_date'       => $item['event_date'] ?? '',
            'datelabel'        => $c->format('M j'),
            'location'         => $item['location'] ?? '',
            'is_in_person'     => (bool) ($item['is_in_person'] ?? false),
            'is_virtual'       => (bool) ($item['is_virtual'] ?? false),
            'is_free'          => (bool) ($item['is_free'] ?? false),
            'is_at_capacity'   => (bool) ($item['is_at_capacity'] ?? false),
            'description'      => (string) ($item['description'] ?? ''),
            'register_url'     => (string) ($item['external_registration_url'] ?? ''),
        ];
        $byDay[$dayKey][] = $row;
        $events[] = $row;
    }

    $tierFor = function (int $count): ?string {
        if ($count <= 0) {
            return null;
        }
        if ($count === 1) {
            return 'low';
        }
        if ($count === 2) {
            return 'med';
        }
        return 'high';
    };

    $todayKey = Carbon::now($tz)->format('Y-m-d');

    // Pre-rendered ±1-month window: previous, current, next.
    $base    = Carbon::now($tz)->startOfMonth();
    $sources = [
        $base->copy()->subMonthNoOverflow(),
        $base->copy(),
        $base->copy()->addMonthNoOverflow(),
    ];

    $months = [];
    foreach ($sources as $index => $monthStart) {
        $days = [];
        for ($d = 1; $d <= $monthStart->daysInMonth; $d++) {
            $key   = $monthStart->copy()->day($d)->format('Y-m-d');
            $count = $density[$key] ?? 0;
            $days[] = [
                'day'   => $d,
                'date'  => $key,
                'count' => $count,
                'tier'  => $tierFor($count),
                'today' => $key === $todayKey,
            ];
        }
        $months[] = [
            'index'   => $index,
            'ym'      => $monthStart->format('Y-m'),
            'label'   => $monthStart->format('F Y'),
            'leading' => $monthStart->copy()->day(1)->dayOfWeek,
            'days'    => $days,
        ];
    }

    // Per-month event lists (month mode).
    $monthEvents = [];
    foreach ($sources as $i => $m) {
        $ym = $m->format('Y-m');
        $monthEvents[$i] = array_values(array_filter($events, fn ($e) => $e['ym'] === $ym));
    }

    $weekdays    = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
    $interactive = $listMode === 'day'; // only day mode makes day cells clickable
@endphp

<div
    class="widget-event-mini-calendar"
    data-tour="events-index.mini-calendar"
    data-list-mode="{{ $listMode }}"
    data-today="{{ $todayKey }}"
    data-current-index="1"
    x-data="NPWidgets.eventMiniCalendar()"
>
    <div class="widget-event-mini-calendar__scale">
    @include('widget-shared.inline-prose', ['tag' => 'div', 'class' => 'widget-event-mini-calendar__heading', 'key' => 'heading', 'type' => 'richtext', 'value' => $heading, 'label' => 'Heading'])

    @foreach ($months as $month)
        <div class="emc-month" data-month-index="{{ $month['index'] }}" @if ($month['index'] !== 1) hidden @endif>
            <div class="emc-month__nav">
                <button type="button" class="emc-month__arrow emc-month__arrow--prev" aria-label="Previous month" @if ($month['index'] === 0) disabled @endif>&lsaquo;</button>
                <span class="emc-month__label">{{ $month['label'] }}</span>
                <button type="button" class="emc-month__arrow emc-month__arrow--next" aria-label="Next month" @if ($month['index'] === count($months) - 1) disabled @endif>&rsaquo;</button>
            </div>

            <div class="emc-month__grid" role="grid" aria-label="{{ $month['label'] }}">
                <div class="emc-month__weekdays" role="row">
                    @foreach ($weekdays as $w)
                        <span class="emc-month__weekday" role="columnheader">{{ $w }}</span>
                    @endforeach
                </div>

                <div class="emc-month__days">
                    @for ($i = 0; $i < $month['leading']; $i++)
                        <span class="emc-day emc-day--empty" aria-hidden="true"></span>
                    @endfor

                    @foreach ($month['days'] as $day)
                        @if ($day['count'] > 0 && $interactive)
                            <button
                                type="button"
                                class="emc-day emc-day--has-events emc-day--{{ $day['tier'] }} @if ($day['today']) emc-day--today @endif"
                                data-day="{{ $day['date'] }}"
                                aria-label="{{ $day['day'] }} — {{ $day['count'] }} {{ \Illuminate\Support\Str::plural('event', $day['count']) }}"
                            >
                                <span class="emc-day__num">{{ $day['day'] }}</span>
                                <span class="emc-day__dot" aria-hidden="true"></span>
                            </button>
                        @elseif ($day['count'] > 0)
                            <span class="emc-day emc-day--has-events emc-day--{{ $day['tier'] }} @if ($day['today']) emc-day--today @endif" aria-label="{{ $day['day'] }} — {{ $day['count'] }} {{ \Illuminate\Support\Str::plural('event', $day['count']) }}">
                                <span class="emc-day__num">{{ $day['day'] }}</span>
                                <span class="emc-day__dot" aria-hidden="true"></span>
                            </span>
                        @else
                            <span class="emc-day @if ($day['today']) emc-day--today @endif">
                                <span class="emc-day__num">{{ $day['day'] }}</span>
                            </span>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach

    @if ($listMode === 'day')
        <div class="emc-events emc-events--day" @if ($eventsStyle !== '') style="{{ $eventsStyle }}" @endif>
            @foreach ($byDay as $key => $dayRows)
                <div class="emc-events__day" data-day-events="{{ $key }}" hidden>
                    <h3 class="emc-events__heading">{{ \Illuminate\Support\Carbon::parse($key)->format('l, F j') }}</h3>
                    <ul class="emc-events__list">
                        @foreach ($dayRows as $e)
                            @include('widgets::EventMiniCalendar.event-row', ['e' => $e, 'showDescription' => $showDescription, 'showDate' => false])
                        @endforeach
                    </ul>
                </div>
            @endforeach
            <p class="emc-events__empty" hidden>No upcoming events.</p>
        </div>
    @else
        <div class="emc-events emc-events--month" @if ($eventsStyle !== '') style="{{ $eventsStyle }}" @endif>
            @foreach ($monthEvents as $i => $rows)
                <div class="emc-events__month" data-month-events="{{ $i }}" @if ($i !== 1) hidden @endif>
                    @if (count($rows) > 0)
                        <ul class="emc-events__list">
                            @foreach ($rows as $e)
                                @include('widgets::EventMiniCalendar.event-row', ['e' => $e, 'showDescription' => $showDescription, 'showDate' => true])
                            @endforeach
                        </ul>
                    @else
                        <p class="emc-events__empty">No events this month.</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
    </div>
</div>
