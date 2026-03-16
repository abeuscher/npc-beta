@if (!empty($config['heading']))
    <h2>{{ $config['heading'] }}</h2>
@endif

@if (empty($blog_posts))
    <p>No posts to display.</p>
@else
    <ul>
        @foreach ($blog_posts as $post)
            <li>
                <a href="/{{ $post['slug'] }}">{{ $post['title'] }}</a>

                @if (!empty($post['excerpt']))
                    <p>{{ $post['excerpt'] }}</p>
                @endif

                @if (!empty($post['published_at']))
                    <time datetime="{{ $post['published_at'] }}">
                        {{ \Carbon\Carbon::parse($post['published_at'])->format('F j, Y') }}
                    </time>
                @endif
            </li>
        @endforeach
    </ul>
@endif
