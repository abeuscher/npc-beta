@php
    $limit  = isset($config['limit']) && $config['limit'] !== '' ? (int) $config['limit'] : null;
    $events = $pageContext->collection('events', $limit);
@endphp

@if (!empty($config['heading']))
    <h2>{{ $config['heading'] }}</h2>
@endif

@if (empty($events))
    <p>No upcoming events. Check back soon.</p>
@else
    @foreach ($events as $event)
        <article>
            <h2><a href="{{ $event['url'] }}">{{ $event['title'] }}</a></h2>

            <p>
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
                <span>Free</span>
            @endif

            <footer>
                <a href="{{ $event['url'] }}">View event &rarr;</a>
            </footer>
        </article>
    @endforeach
@endif
