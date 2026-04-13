@php $event = $pageContext->event($config['event_slug'] ?? null); @endphp
@if (isset($event))
    <h1>{{ $event->title }}</h1>

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

            if ($event->is_in_person && $event->is_virtual) {
                $location = 'In-person + Online';
                if ($event->city) {
                    $location .= ' (' . $event->city . ($event->state ? ', ' . $event->state : '') . ')';
                }
            } elseif ($event->is_in_person) {
                $location = $event->city ? $event->city . ($event->state ? ', ' . $event->state : '') : null;
            } elseif ($event->is_virtual) {
                $location = 'Online';
            } else {
                $location = null;
            }
        @endphp
        <p class="widget-event-description__date">
            <time datetime="{{ $event->starts_at->toIso8601String() }}">{{ $dateString }}</time>
            @if ($location)
                &mdash; {{ $location }}
            @endif
        </p>
    @endif

    @if ($event->description)
        <div class="widget-event-description__body">
            {!! \App\Services\Media\InlineImageRenderer::process($event->description) !!}
        </div>
    @endif
@endif
