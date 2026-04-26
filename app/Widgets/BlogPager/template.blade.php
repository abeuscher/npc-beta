@php
    $prev = null;
    $next = null;
    $hasPosts = false;

    if ($pageContext->currentPage && $pageContext->currentPage->type === 'post') {
        $items = $widgetData['items'] ?? [];
        $idx = false;
        foreach ($items as $i => $item) {
            if (($item['id'] ?? null) === $pageContext->currentPage->id) {
                $idx = $i;
                break;
            }
        }

        if ($idx !== false) {
            $next = $idx > 0                  ? ($items[$idx - 1] ?? null) : null;
            $prev = $idx < count($items) - 1  ? ($items[$idx + 1] ?? null) : null;
            $hasPosts = true;
        }
    }

    $defaultPrevTemplate = '<span class="pager-link__title">&larr; {{item.title}}</span><small>{{item.author_name}} | {{item.published_at_label}}</small>';
    $defaultNextTemplate = '<span class="pager-link__title">{{item.title}} &rarr;</span><small>{{item.author_name}} | {{item.published_at_label}}</small>';

    $prevTemplate = trim(strip_tags($config['prev_template'] ?? '')) !== '' ? ($config['prev_template'] ?? $defaultPrevTemplate) : $defaultPrevTemplate;
    $nextTemplate = trim(strip_tags($config['next_template'] ?? '')) !== '' ? ($config['next_template'] ?? $defaultNextTemplate) : $defaultNextTemplate;

    $resolveTokens = function (string $template, $item) {
        if (! $item) {
            return $template;
        }

        $rendered = $template;
        foreach ($item as $key => $value) {
            if ($key === 'image') {
                $thumbUrl = (string) ($value ?? '');
                $imgHtml = $thumbUrl ? '<img src="' . e($thumbUrl) . '" alt="' . e($item['title'] ?? '') . '" class="pager-link__thumb" loading="lazy">' : '';
                $rendered = str_replace('{{item.image}}', $imgHtml, $rendered);
            } else {
                $rendered = str_replace('{{item.' . $key . '}}', e((string) $value), $rendered);
            }
        }
        return $rendered;
    };

    $isPreview = ! $hasPosts;
@endphp

@if ($prev || $next || $isPreview)
    <nav class="widget-blog-pager" aria-label="Post navigation">
        <span class="pager-link">
            @if ($next)
                <a href="{{ $next['url'] ?? '' }}" rel="next" class="pager-link__anchor">
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
                <a href="{{ $prev['url'] ?? '' }}" rel="prev" class="pager-link__anchor">
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
