@php
    $limit = isset($config['limit']) && $config['limit'] !== '' ? (int) $config['limit'] : null;
    $posts = $pageContext->posts($limit);
@endphp

@if (!empty($config['heading']))
    <h2 class="text-2xl font-heading font-bold mb-4 text-gray-900 dark:text-gray-100">{{ $config['heading'] }}</h2>
@endif

@if ($posts->isEmpty())
    <p class="text-gray-600 dark:text-gray-400">No posts to display.</p>
@else
    <ul class="space-y-3 list-none pl-0">
        @foreach ($posts as $post)
            <li class="flex flex-col gap-0.5">
                <a href="/{{ $post->slug }}" class="text-primary font-medium hover:opacity-80">{{ $post->title }}</a>

                @if ($post->published_at)
                    <time datetime="{{ $post->published_at->toIso8601String() }}" class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $post->published_at->format('F j, Y') }}
                    </time>
                @endif
            </li>
        @endforeach
    </ul>
@endif
