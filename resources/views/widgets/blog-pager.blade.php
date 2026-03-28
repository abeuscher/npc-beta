@php
    $prev = null;
    $next = null;

    if (isset($currentPage) && $currentPage->type === 'post') {
        $sortValue = ($currentPage->published_at ?? $currentPage->created_at)->toDateTimeString();

        $prev = \App\Models\Page::where('type', 'post')
            ->where('is_published', true)
            ->whereRaw("COALESCE(published_at, created_at) < ?", [$sortValue])
            ->orderByRaw('COALESCE(published_at, created_at) DESC')
            ->first();

        $next = \App\Models\Page::where('type', 'post')
            ->where('is_published', true)
            ->whereRaw("COALESCE(published_at, created_at) > ?", [$sortValue])
            ->orderByRaw('COALESCE(published_at, created_at) ASC')
            ->first();
    }
@endphp

@if (config('app.debug'))
    <div style="background:#fef3c7;border:1px solid #d97706;padding:8px;font-size:12px;font-family:monospace;margin:8px 0">
        <strong>blog-pager debug</strong><br>
        currentPage: {{ isset($currentPage) ? $currentPage->slug . ' (type=' . $currentPage->type . ')' : 'NOT SET' }}<br>
        sortValue: {{ isset($sortValue) ? $sortValue : 'NOT SET' }}<br>
        prev: {{ $prev ? $prev->slug : 'none' }}<br>
        next: {{ $next ? $next->slug : 'none' }}<br>
        total published posts: {{ \App\Models\Page::where('type','post')->where('is_published',true)->count() }}
    </div>
@endif

@if ($prev || $next)
    <nav class="blog-pager" aria-label="Post navigation">
        @if ($prev)
            <a href="/{{ $prev->slug }}" rel="prev">&larr; {{ $prev->title }}</a>
        @endif

        @if ($next)
            <a href="/{{ $next->slug }}" rel="next">{{ $next->title }} &rarr;</a>
        @endif
    </nav>
@endif
