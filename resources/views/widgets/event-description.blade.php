@php $event = $pageContext->event($config['event_slug'] ?? null); @endphp
@isset($event)
    <h1 class="text-3xl font-heading font-bold mb-2 text-gray-900 dark:text-gray-100">{{ $event->title }}</h1>

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
        <p class="text-gray-600 dark:text-gray-400 mb-4">
            <time datetime="{{ $event->starts_at->toIso8601String() }}">{{ $dateString }}</time>
            @if ($location)
                &mdash; {{ $location }}
            @endif
        </p>
    @endif

    @if ($event->description)
        <div class="text-gray-800 dark:text-gray-200">
            {!! $event->description !!}
        </div>
    @endif
@endisset
