@php
    $items = $widgetData['items'] ?? [];
    $daysAhead = (int) ($config['days_ahead'] ?? 7);
@endphp
<div class="np-this-weeks-events">
    <h3 class="np-this-weeks-events__heading">This Week's Events</h3>
    @if (empty($items))
        <p class="np-this-weeks-events__empty">No events in the next {{ $daysAhead }} days.</p>
    @else
        <ul class="np-this-weeks-events__list">
            @foreach ($items as $event)
                @php
                    $startsAt = ! empty($event['starts_at']) ? \Illuminate\Support\Carbon::parse($event['starts_at']) : null;
                    $locationParts = array_filter([
                        $event['address_line_1'] ?? null,
                        $event['city'] ?? null,
                        $event['state'] ?? null,
                    ]);
                    $location = implode(', ', $locationParts);
                    if ($location === '' && ! empty($event['meeting_label'])) {
                        $location = $event['meeting_label'];
                    }
                @endphp
                <li class="np-this-weeks-events__item">
                    @if ($startsAt)
                        <p class="np-this-weeks-events__date">{{ \App\Support\DateFormat::format($startsAt, \App\Support\DateFormat::EVENT_COMPACT) }}</p>
                    @endif
                    @if (! empty($event['title']))
                        <h4 class="np-this-weeks-events__title">{{ $event['title'] }}</h4>
                    @endif
                    @if ($location !== '')
                        <p class="np-this-weeks-events__location">{{ $location }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
