@isset($event)
    @if ($event->starts_at)
        @php
            $startDate = $event->starts_at->format('F jS');
            $startTime = $event->starts_at->format($event->starts_at->minute === 0 ? 'ga' : 'g:ia');

            if ($event->ends_at) {
                $endTime = $event->ends_at->format($event->ends_at->minute === 0 ? 'ga' : 'g:ia');
                if ($event->starts_at->isSameDay($event->ends_at)) {
                    $dateString = $startDate . ', ' . $startTime . ' – ' . $endTime;
                } else {
                    $endDate    = $event->ends_at->format('F jS');
                    $dateString = $startDate . ', ' . $startTime . ' – ' . $endDate . ', ' . $endTime;
                }
            } else {
                $dateString = $startDate . ', ' . $startTime;
            }
        @endphp
        <p class="event-date">{{ $dateString }}</p>
    @endif

    @if ($event->description)
        <div class="event-description">
            {!! $event->description !!}
        </div>
    @endif
@endisset
