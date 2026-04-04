@php
    $limit  = isset($config['limit']) && $config['limit'] !== '' ? (int) $config['limit'] : null;
    $events = $pageContext->collection('events', $limit);
@endphp

@if (!empty($config['heading']))
    <h2>{{ $config['heading'] }}</h2>
@endif

@if (empty($events))
    <p class="text-muted">No upcoming events. Check back soon.</p>
@else
    <div class="widget-events-listing">
        @foreach ($events as $event)
            <article class="event-card">
                <h2 class="event-card__title"><a href="{{ $event['url'] }}">{{ $event['title'] }}</a></h2>

                <p class="event-card__date">
                    <time datetime="{{ $event['starts_at'] }}">
                        {{ \Carbon\Carbon::parse($event['starts_at'])->format('D, F j, Y \a\t g:i A') }}
                    </time>
                    @if (!empty($event['ends_at']))
                        &ndash;
                        <time datetime="{{ $event['ends_at'] }}">
                            {{ \Carbon\Carbon::parse($event['ends_at'])->format('g:i A') }}
                        </time>
                    @endif
                </p>

                @if ($event['is_free'])
                    <span class="event-card__badge">Free</span>
                @endif

                <footer>
                    <a href="{{ $event['url'] }}" class="event-card__link">View event &rarr;</a>
                </footer>
            </article>
        @endforeach
    </div>
@endif
