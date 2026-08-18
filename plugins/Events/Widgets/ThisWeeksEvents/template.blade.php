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
                    @if (! empty($event['event_date']))
                        <p class="np-this-weeks-events__date">{{ $event['event_date'] }} · {{ $event['event_time'] }}</p>
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
