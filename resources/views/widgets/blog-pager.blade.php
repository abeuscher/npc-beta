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
    <nav class="flex justify-between items-start gap-4 border-t border-gray-200 dark:border-gray-700 pt-6" aria-label="Post navigation">
        <span class="flex flex-col gap-0.5">
            @if ($next)
                <a href="/{{ $next->slug }}" rel="next" class="text-primary hover:opacity-80">&larr; {{ $next->title }}</a>
                <small class="text-sm text-gray-500 dark:text-gray-400">{{ $next->author?->name }} | {{ ($next->published_at ?? $next->created_at)->format('F j, Y') }}</small>
            @endif
        </span>

        <span class="flex flex-col gap-0.5 text-right">
            @if ($prev)
                <a href="/{{ $prev->slug }}" rel="prev" class="text-primary hover:opacity-80">{{ $prev->title }} &rarr;</a>
                <small class="text-sm text-gray-500 dark:text-gray-400">{{ $prev->author?->name }} | {{ ($prev->published_at ?? $prev->created_at)->format('F j, Y') }}</small>
            @endif
        </span>
    </nav>
@endif
