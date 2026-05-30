@php
    use Illuminate\Support\Carbon;

    $rowUrl    = $item['url'] ?? '';
    $rowThumb  = $thumbFor($item);
    $rowBadges = $badgesFor($item);
@endphp
<li class="events-list__row" data-event-row data-tags="{{ $tagSlugs($item) }}">
    <a class="events-list__link" @if ($rowUrl !== '') href="{{ $rowUrl }}" @endif>
        <span class="events-list__thumb">
            @if ($rowThumb !== '')
                <img src="{{ $rowThumb }}" alt="{{ $item['title'] ?? '' }}" loading="lazy">
            @else
                <span class="events-list__placeholder" aria-hidden="true">{{ Carbon::parse($item['starts_at'])->setTimezone($tz)->format('M j') }}</span>
            @endif
        </span>
        <span class="events-list__detail">
            @if (($item['event_time'] ?? '') !== '')
                <span class="events-list__time">{{ $item['event_time'] }}</span>
            @endif
            <span class="events-list__title">{{ $item['title'] ?? '' }}</span>
            @if (($item['location'] ?? '') !== '')
                <span class="events-list__location">{{ $item['location'] }}</span>
            @endif
            @if (count($rowBadges))
                <span class="events-list__badges">
                    @foreach ($rowBadges as $badge)
                        <span class="events-list__badge events-list__badge--{{ $badge['mod'] }}">{{ $badge['label'] }}</span>
                    @endforeach
                </span>
            @endif
        </span>
    </a>
</li>
