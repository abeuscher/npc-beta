@php $item = $widgetData['item'] ?? null; @endphp
@if ($item)
    <h1>{{ $item['title'] }}</h1>

    @if (! empty($item['starts_at']))
        <p class="widget-event-description__date">
            <time datetime="{{ $item['starts_at'] }}">{{ $item['event_date'] }}, {{ $item['event_time'] }}</time>
            @if (! empty($item['event_location']))
                &mdash; {{ $item['event_location'] }}
            @endif
        </p>
    @endif

    @if (! empty($item['description']))
        <div class="widget-event-description__body">
            {!! \App\Services\Media\InlineImageRenderer::process($item['description']) !!}
        </div>
    @endif
@endif
