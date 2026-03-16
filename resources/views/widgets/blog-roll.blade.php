@if (!empty($config['heading']))
    <h2>{{ $config['heading'] }}</h2>
@endif

@if (empty($data))
    <p>No posts to display.</p>
@else
    <ul>
        @foreach ($data as $post)
            <li>
                <a href="/{{ $post['slug'] }}">{{ $post['title'] }}</a>

                @if (!empty($config['show_excerpt']) && !empty($post['excerpt']))
                    <p>{{ $post['excerpt'] }}</p>
                @endif

                @if (!empty($post['published_at']))
                    <time datetime="{{ $post['published_at'] instanceof \Carbon\Carbon ? $post['published_at']->toIso8601String() : $post['published_at'] }}">
                        {{ $post['published_at'] instanceof \Carbon\Carbon ? $post['published_at']->format('F j, Y') : $post['published_at'] }}
                    </time>
                @endif
            </li>
        @endforeach
    </ul>
@endif
