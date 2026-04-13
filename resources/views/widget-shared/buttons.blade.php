@php
    $alignment = $alignment ?? 'left';
    $siteHost = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
    $buttonStyles = \App\Models\SiteSetting::get('button_styles') ?? [];
@endphp

@if (!empty($buttons))
    <div class="btn-group btn-group--{{ $alignment }}">
        @foreach ($buttons as $btn)
            @if (!empty($btn['text']))
                @php
                    $url = $btn['url'] ?? '#';
                    $urlHost = parse_url($url, PHP_URL_HOST);
                    $isExternal = $urlHost && $urlHost !== $siteHost && ! str_ends_with($urlHost, '.' . $siteHost);
                    $style = $btn['style'] ?? 'primary';
                    $hover = $buttonStyles[$style]['hover'] ?? 'opacity';
                @endphp
                <a
                    href="{{ e($url) }}"
                    class="btn btn--{{ $style }}"
                    data-hover="{{ $hover }}"
                    @if ($isExternal) target="_blank" rel="noopener noreferrer" @endif
                >
                    {{ $btn['text'] }}
                    @if ($isExternal)
                        <svg class="btn__icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline;vertical-align:middle;margin-left:0.25em;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                    @endif
                </a>
            @endif
        @endforeach
    </div>
@endif
