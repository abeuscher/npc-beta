@php $item = $widgetData['item'] ?? null; @endphp
@if ($item)
    <h1>{{ $item['title'] }}</h1>

    @if (! empty($item['starts_at']))
        @php
            $startsAt  = \Illuminate\Support\Carbon::parse($item['starts_at']);
            $endsAt    = ! empty($item['ends_at']) ? \Illuminate\Support\Carbon::parse($item['ends_at']) : null;
            $startDate = \App\Support\DateFormat::format($startsAt, \App\Support\DateFormat::EVENT_LIST_DATE);
            $startTime = \App\Support\DateFormat::format($startsAt, \App\Support\DateFormat::TIME_SMART);

            if ($endsAt) {
                $endTime = \App\Support\DateFormat::format($endsAt, \App\Support\DateFormat::TIME_SMART);
                if ($startsAt->isSameDay($endsAt)) {
                    $dateString = $startDate . ', ' . $startTime . ' – ' . $endTime;
                } else {
                    $endDate    = \App\Support\DateFormat::format($endsAt, \App\Support\DateFormat::EVENT_LIST_DATE);
                    $dateString = $startDate . ', ' . $startTime . ' – ' . $endDate . ', ' . $endTime;
                }
            } else {
                $dateString = $startDate . ', ' . $startTime;
            }

            if ($item['is_in_person'] && $item['is_virtual']) {
                $location = 'In-person + Online';
                if ($item['city']) {
                    $location .= ' (' . $item['city'] . ($item['state'] ? ', ' . $item['state'] : '') . ')';
                }
            } elseif ($item['is_in_person']) {
                $location = $item['city'] ? $item['city'] . ($item['state'] ? ', ' . $item['state'] : '') : null;
            } elseif ($item['is_virtual']) {
                $location = 'Online';
            } else {
                $location = null;
            }
        @endphp
        <p class="widget-event-description__date">
            <time datetime="{{ $startsAt->toIso8601String() }}">{{ $dateString }}</time>
            @if ($location)
                &mdash; {{ $location }}
            @endif
        </p>
    @endif

    @if (! empty($item['description']))
        <div class="widget-event-description__body">
            {!! \App\Services\Media\InlineImageRenderer::process($item['description']) !!}
        </div>
    @endif
@endif
