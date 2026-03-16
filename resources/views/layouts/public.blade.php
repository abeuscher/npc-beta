<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $title ?? config('site.name', config('app.name')) }}</title>

    @if (!empty($description))
        <meta name="description" content="{{ $description }}">
    @endif

    {{-- Alpine.js --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    {{-- Optional Pico CSS — enable via CMS Settings --}}
    @if (config('site.use_pico', false))
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    @endif

    {{-- Custom stylesheet stored via CMS Settings upload --}}
    @if (config('site.custom_css'))
        <link rel="stylesheet" href="{{ asset(config('site.custom_css')) }}">
    @endif

    {{-- Inline CSS collected from active page widgets --}}
    @if (!empty($inlineStyles))
        <style>{!! $inlineStyles !!}</style>
    @endif

    {{-- Custom stylesheet hook — push styles onto this stack from any view --}}
    @stack('styles')
</head>
<body>

    {{-- Public navigation --}}
    @php
        $navItems = \App\Models\NavigationItem::where('is_visible', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->with(['children', 'page'])
            ->get();
    @endphp

    <nav x-data="{ open: false }">
        <button
            x-on:click="open = !open"
            aria-label="Toggle navigation"
            aria-expanded="false"
            x-bind:aria-expanded="open.toString()"
        >&#9776;</button>

        <ul x-show="open || true" x-cloak>
            @foreach ($navItems as $item)
                <li>
                    @php
                        if ($item->page_id && $item->page) {
                            $href = url('/' . $item->page->slug);
                        } else {
                            $href = $item->url ?? '#';
                        }
                    @endphp
                    <a href="{{ $href }}" target="{{ $item->target ?? '_self' }}">{{ $item->label }}</a>

                    @if ($item->children->isNotEmpty())
                        <ul>
                            @foreach ($item->children as $child)
                                <li>
                                    @php
                                        if ($child->page_id && $child->page) {
                                            $childHref = url('/' . $child->page->slug);
                                        } else {
                                            $childHref = $child->url ?? '#';
                                        }
                                    @endphp
                                    <a href="{{ $childHref }}" target="{{ $child->target ?? '_self' }}">{{ $child->label }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>

    @yield('content')

    <script>
    window.__site = {
        name: @json(config('site.name', config('app.name'))),
        blogPrefix: @json(config('site.blog_prefix', 'news')),
        contactEmail: @json(config('site.contact_email', '')),
    };
    </script>

    {{-- Inline JS collected from active page widgets --}}
    @if (!empty($inlineScripts))
        <script>{!! $inlineScripts !!}</script>
    @endif

    @stack('scripts')
</body>
</html>
