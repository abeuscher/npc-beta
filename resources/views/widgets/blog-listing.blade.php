@php
    $limit = isset($config['limit']) && $config['limit'] !== '' ? (int) $config['limit'] : null;
    $posts = $pageContext->posts($limit);
@endphp

@if (!empty($config['heading']))
    <h2>{{ $config['heading'] }}</h2>
@endif

@if ($posts->isEmpty())
    <p>No posts to display.</p>
@else
    <ul>
        @foreach ($posts as $post)
            <li>
                <a href="/{{ $post->slug }}">{{ $post->title }}</a>

                @if ($post->published_at)
                    <time datetime="{{ $post->published_at->toIso8601String() }}">
                        {{ $post->published_at->format('F j, Y') }}
                    </time>
                @endif
            </li>
        @endforeach
    </ul>
@endif
