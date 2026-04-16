@php
    $prev = null;
    $next = null;
    $hasPosts = false;

    if ($pageContext->currentPage && $pageContext->currentPage->type === 'post') {
        $allPosts = $pageContext->posts();
        $idx = $allPosts->search(fn ($p) => $p->id === $pageContext->currentPage->id);

        if ($idx !== false) {
            $next = $idx > 0                        ? $allPosts->get($idx - 1) : null;
            $prev = $idx < $allPosts->count() - 1   ? $allPosts->get($idx + 1) : null;
            $hasPosts = true;
        }
    }

    $defaultPrevTemplate = '<span class="pager-link__title">&larr; {{title}}</span><small>{{author}} | {{date}}</small>';
    $defaultNextTemplate = '<span class="pager-link__title">{{title}} &rarr;</span><small>{{author}} | {{date}}</small>';

    $prevTemplate = trim(strip_tags($config['prev_template'] ?? '')) !== '' ? ($config['prev_template'] ?? $defaultPrevTemplate) : $defaultPrevTemplate;
    $nextTemplate = trim(strip_tags($config['next_template'] ?? '')) !== '' ? ($config['next_template'] ?? $defaultNextTemplate) : $defaultNextTemplate;

    $resolveTokens = function (string $template, $post) {
        if (! $post) {
            return $template;
        }

        $thumbUrl = $post->getFirstMediaUrl('post_thumbnail', 'webp')
            ?: $post->getFirstMediaUrl('post_thumbnail');
        $imgHtml = $thumbUrl ? '<img src="' . e($thumbUrl) . '" alt="' . e($post->title) . '" class="pager-link__thumb" loading="lazy">' : '';

        $tokens = [
            '{{title}}'  => e($post->title),
            '{{url}}'    => url('/' . $post->slug),
            '{{date}}'   => $post->published_at?->format('F j, Y') ?? '',
            '{{author}}' => e($post->author?->name ?? ''),
            '{{image}}'  => $imgHtml,
        ];

        return str_replace(array_keys($tokens), array_values($tokens), $template);
    };

    $isPreview = ! $hasPosts;
@endphp

@if ($prev || $next || $isPreview)
    <nav class="widget-blog-pager" aria-label="Post navigation">
        <span class="pager-link">
            @if ($next)
                <a href="{{ url('/' . $next->slug) }}" rel="next" class="pager-link__anchor">
                    {!! $resolveTokens($nextTemplate, $next) !!}
                </a>
            @elseif ($isPreview)
                <span class="pager-link__anchor">
                    {!! $nextTemplate !!}
                </span>
            @endif
        </span>

        <span class="pager-link pager-link--next">
            @if ($prev)
                <a href="{{ url('/' . $prev->slug) }}" rel="prev" class="pager-link__anchor">
                    {!! $resolveTokens($prevTemplate, $prev) !!}
                </a>
            @elseif ($isPreview)
                <span class="pager-link__anchor">
                    {!! $prevTemplate !!}
                </span>
            @endif
        </span>
    </nav>
@endif
