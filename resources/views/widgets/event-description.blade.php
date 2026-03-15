@isset($event)
    @if ($event->description)
        <div class="event-description">
            {!! $event->description !!}
        </div>
    @endif
@endisset
