@php
    $limit = isset($config['limit']) && $config['limit'] !== '' ? (int) $config['limit'] : null;
    $posts = $pageContext->posts($limit);
@endphp

@if (!empty($config['heading']))
    <h2>{{ $config['heading'] }}</h2>
@endif

@if ($posts->isEmpty())
    <p class="text-muted">No posts to display.</p>
@else
    <ul class="post-list">
        @foreach ($posts as $post)
            <li class="post-list__item">
                <a href="/{{ $post->slug }}" class="post-list__link">{{ $post->title }}</a>

                @if ($post->published_at)
                    <time datetime="{{ $post->published_at->toIso8601String() }}" class="post-list__date">
                        {{ $post->published_at->format('F j, Y') }}
                    </time>
                @endif
            </li>
        @endforeach
    </ul>
@endif
