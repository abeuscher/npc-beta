@isset($event)
    @if ($dates->isNotEmpty())
        <ul class="event-dates-list">
            @foreach ($dates as $d)
                <li>
                    <time datetime="{{ $d->starts_at->toIso8601String() }}">
                        {{ $d->starts_at->format('F j, Y') }}
                    </time>
                    @php $loc = $d->effectiveLocation(); @endphp
                    @if ($loc['is_in_person'] && $loc['is_virtual'])
                        &mdash; In-person + Online
                        @if ($loc['city']) ({{ $loc['city'] }}@if($loc['state']), {{ $loc['state'] }}@endif) @endif
                    @elseif ($loc['is_in_person'])
                        @if ($loc['city']) &mdash; {{ $loc['city'] }}@if($loc['state']), {{ $loc['state'] }}@endif @endif
                    @elseif ($loc['is_virtual'])
                        &mdash; Online
                    @endif
                </li>
            @endforeach
        </ul>
    @else
        <p>No upcoming dates scheduled.</p>
    @endif
@endisset
