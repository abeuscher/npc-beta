@php
    $prev = null;
    $next = null;

    if ($pageContext->currentPage && $pageContext->currentPage->type === 'post') {
        $allPosts = $pageContext->posts(); // DESC order — index 0 = newest
        $idx = $allPosts->search(fn ($p) => $p->id === $pageContext->currentPage->id);

        if ($idx !== false) {
            $next = $idx > 0                        ? $allPosts->get($idx - 1) : null; // newer
            $prev = $idx < $allPosts->count() - 1   ? $allPosts->get($idx + 1) : null; // older
        }
    }
@endphp

@php
    $thumbFor = function ($post) {
        return $post->getFirstMediaUrl('post_thumbnail', 'webp')
            ?: $post->getFirstMediaUrl('post_thumbnail');
    };
@endphp

@if ($prev || $next)
    <nav class="widget-blog-pager" aria-label="Post navigation">
        <span class="pager-link">
            @if ($next)
                @php $thumb = $thumbFor($next); @endphp
                <a href="/{{ $next->slug }}" rel="next" class="pager-link__anchor">
                    @if ($thumb)
                        <img src="{{ $thumb }}" alt="" class="pager-link__thumb" loading="lazy">
                    @endif
                    <span class="pager-link__title">&larr; {{ $next->title }}</span>
                </a>
                <small class="pager-meta">{{ $next->author?->name }} | {{ ($next->published_at ?? $next->created_at)->format('F j, Y') }}</small>
            @endif
        </span>

        <span class="pager-link pager-link--next">
            @if ($prev)
                @php $thumb = $thumbFor($prev); @endphp
                <a href="/{{ $prev->slug }}" rel="prev" class="pager-link__anchor">
                    @if ($thumb)
                        <img src="{{ $thumb }}" alt="" class="pager-link__thumb" loading="lazy">
                    @endif
                    <span class="pager-link__title">{{ $prev->title }} &rarr;</span>
                </a>
                <small class="pager-meta">{{ $prev->author?->name }} | {{ ($prev->published_at ?? $prev->created_at)->format('F j, Y') }}</small>
            @endif
        </span>
    </nav>
@endif
