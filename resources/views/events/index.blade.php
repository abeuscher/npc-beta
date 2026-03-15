@extends('layouts.public')

@section('content')
<main>
    <h1>{{ $title }}</h1>

    @if ($dates->isEmpty())
        <p>No upcoming events. Check back soon.</p>
    @else
        @foreach ($dates as $date)
            <article>
                <h2>
                    <a href="{{ route('events.show', [$date->event->slug, $date->id]) }}">
                        {{ $date->event->title }}
                    </a>
                </h2>

                <p>
                    <time datetime="{{ $date->starts_at->toIso8601String() }}">
                        {{ $date->starts_at->format('D, F j, Y \a\t g:i A') }}
                    </time>
                    @if ($date->ends_at)
                        &ndash;
                        <time datetime="{{ $date->ends_at->toIso8601String() }}">
                            {{ $date->ends_at->format('g:i A') }}
                        </time>
                    @endif
                </p>

                <p>
                    @php $loc = $date->effectiveLocation(); @endphp
                    @if ($loc['is_in_person'] && $loc['is_virtual'])
                        In-person + Virtual
                        @if ($loc['city'])
                            &mdash; {{ $loc['city'] }}@if($loc['state']), {{ $loc['state'] }}@endif
                        @endif
                    @elseif ($loc['is_in_person'])
                        @if ($loc['city'])
                            {{ $loc['city'] }}@if($loc['state']), {{ $loc['state'] }}@endif
                        @else
                            In-person
                        @endif
                    @elseif ($loc['is_virtual'])
                        Virtual
                    @endif
                </p>

                <p>
                    @if ($date->event->is_free)
                        <span>Free</span>
                    @endif
                    @if (! $date->event->registration_open)
                        <span>Registration closed</span>
                    @elseif ($date->isAtCapacity())
                        <span>Sold out</span>
                    @endif
                </p>

                <footer>
                    <a href="{{ route('events.show', [$date->event->slug, $date->id]) }}">
                        View event &rarr;
                    </a>
                </footer>
            </article>
        @endforeach

        {{ $dates->links() }}
    @endif
</main>
@endsection
