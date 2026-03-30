@php
    $limit  = isset($config['limit']) && $config['limit'] !== '' ? (int) $config['limit'] : null;
    $events = $pageContext->collection('events', $limit);
@endphp

@if (!empty($config['heading']))
    <h2 class="text-2xl font-heading font-bold mb-4 text-gray-900 dark:text-gray-100">{{ $config['heading'] }}</h2>
@endif

@if (empty($events))
    <p class="text-gray-600 dark:text-gray-400">No upcoming events. Check back soon.</p>
@else
    <div class="space-y-6">
        @foreach ($events as $event)
            <article class="border-b border-gray-200 dark:border-gray-700 pb-6 last:border-0">
                <h2 class="text-xl font-heading font-bold mb-1 text-gray-900 dark:text-gray-100"><a href="{{ $event['url'] }}" class="text-primary hover:opacity-80">{{ $event['title'] }}</a></h2>

                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
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
                    <span class="inline-block text-xs font-semibold uppercase tracking-wide bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 px-2 py-0.5 rounded mb-2">Free</span>
                @endif

                <footer>
                    <a href="{{ $event['url'] }}" class="text-primary text-sm font-medium hover:opacity-80">View event &rarr;</a>
                </footer>
            </article>
        @endforeach
    </div>
@endif
