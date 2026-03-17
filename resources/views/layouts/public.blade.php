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

    @include(view()->exists('custom.header') ? 'custom.header' : 'components.site-header')

    @yield('content')

    @include(view()->exists('custom.footer') ? 'custom.footer' : 'components.site-footer')

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
