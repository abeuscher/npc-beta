@isset($event)
    @if ($event->starts_at)
        <ul class="event-dates-list">
            <li>
                <time datetime="{{ $event->starts_at->toIso8601String() }}">
                    {{ $event->starts_at->format('F j, Y') }}
                </time>
                @if ($event->is_in_person && $event->is_virtual)
                    &mdash; In-person + Online
                    @if ($event->city) ({{ $event->city }}@if($event->state), {{ $event->state }}@endif) @endif
                @elseif ($event->is_in_person)
                    @if ($event->city) &mdash; {{ $event->city }}@if($event->state), {{ $event->state }}@endif @endif
                @elseif ($event->is_virtual)
                    &mdash; Online
                @endif
            </li>
        </ul>
    @else
        <p>No upcoming dates scheduled.</p>
    @endif
@endisset
