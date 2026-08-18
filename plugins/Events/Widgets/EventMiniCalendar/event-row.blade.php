@php
    // One expandable event row. The summary is a toggle (caret + time/date +
    // title); the detail panel is a rail-peek of the facts we hold. Every event
    // has a landing page, so the "Event page" link renders whenever a URL is
    // present.
    $e               = $e ?? [];
    $showDescription = $showDescription ?? true;
    $showDate        = $showDate ?? false;

    $hasDescription = $showDescription && trim(strip_tags((string) ($e['description'] ?? ''))) !== '';

    $tags = [];
    if (! empty($e['is_free']))        $tags[] = 'Free';
    if (! empty($e['is_in_person']))   $tags[] = 'In person';
    if (! empty($e['is_virtual']))     $tags[] = 'Virtual';
    if (! empty($e['is_at_capacity'])) $tags[] = 'Sold out';
@endphp
<li class="emc-event">
    <button type="button" class="emc-event__toggle" aria-expanded="false">
        <span class="emc-event__caret" aria-hidden="true"></span>
        <span class="emc-event__summary">
            @if ($showDate && ! empty($e['datelabel']))<span class="emc-events__date">{{ $e['datelabel'] }}</span>@endif
            @if (! empty($e['time']))<span class="emc-events__time">{{ $e['time'] }}</span>@endif
            <span class="emc-event__title">{{ $e['title'] }}</span>
        </span>
    </button>

    <div class="emc-event__detail" hidden>
        @if (! empty($e['event_date']))
            <p class="emc-event__fact">{{ $e['event_date'] }}@if (! empty($e['time'])) · {{ $e['time'] }}@endif</p>
        @endif
        @if (! empty($e['location']))
            <p class="emc-event__fact">{{ $e['location'] }}</p>
        @endif
        @if (! empty($tags))
            <p class="emc-event__tags">
                @foreach ($tags as $tag)<span class="emc-event__tag">{{ $tag }}</span>@endforeach
            </p>
        @endif
        @if ($hasDescription)
            <div class="emc-event__desc">{!! \App\Services\Media\InlineImageRenderer::process($e['description']) !!}</div>
        @endif
        @if (! empty($e['url']))
            <a class="emc-event__link" href="{{ $e['url'] }}">Event page &rarr;</a>
        @endif
        @if (! empty($e['register_url']))
            <a class="emc-event__link" href="{{ $e['register_url'] }}">Register &rarr;</a>
        @endif
    </div>
</li>
