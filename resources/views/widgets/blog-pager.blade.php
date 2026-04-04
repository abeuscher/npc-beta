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

@if ($prev || $next)
    <nav class="widget-blog-pager" aria-label="Post navigation">
        <span class="pager-link">
            @if ($next)
                <a href="/{{ $next->slug }}" rel="next">&larr; {{ $next->title }}</a>
                <small class="pager-meta">{{ $next->author?->name }} | {{ ($next->published_at ?? $next->created_at)->format('F j, Y') }}</small>
            @endif
        </span>

        <span class="pager-link pager-link--next">
            @if ($prev)
                <a href="/{{ $prev->slug }}" rel="prev">{{ $prev->title }} &rarr;</a>
                <small class="pager-meta">{{ $prev->author?->name }} | {{ ($prev->published_at ?? $prev->created_at)->format('F j, Y') }}</small>
            @endif
        </span>
    </nav>
@endif
