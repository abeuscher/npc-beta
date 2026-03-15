@if (!empty($config['heading']))
    <h2>{{ $config['heading'] }}</h2>
@endif

@if (empty($blog_posts))
    <p>No posts to display.</p>
@else
    @php $blogPrefix = config('site.blog_prefix', 'news'); @endphp
    <ul>
        @foreach ($blog_posts as $post)
            <li>
                <a href="/{{ $blogPrefix }}/{{ $post['slug'] }}">{{ $post['title'] }}</a>

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
