<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $title ?? config('site.name', config('app.name')) }}</title>

    @if (!empty($description))
        <meta name="description" content="{{ $description }}">
    @endif

    @vite(['resources/scss/public.scss', 'resources/js/public.js'])

    @php
        $cssVars = [];

        $primaryColor = \App\Models\SiteSetting::get('public_primary_color');
        $headingFont  = \App\Models\SiteSetting::get('public_heading_font');
        $bodyFont     = \App\Models\SiteSetting::get('public_body_font');

        if ($primaryColor) {
            $cssVars[] = "--pico-primary: {$primaryColor}";
        }
        if ($headingFont) {
            $cssVars[] = "--pico-font-family-heading: {$headingFont}";
        }
        if ($bodyFont) {
            $cssVars[] = "--pico-font-family-sans-serif: {$bodyFont}";
        }
    @endphp

    @if ($cssVars)
        <style>:root { {{ implode('; ', $cssVars) }}; }</style>
    @endif

    {{-- Inline CSS collected from active page widgets --}}
    @if (!empty($inlineStyles))
        <style>{!! $inlineStyles !!}</style>
    @endif

    @stack('styles')
</head>
<body class="{{ $bodyClass ?? 'page-unknown' }}">

    @include(view()->exists('custom.header') ? 'custom.header' : 'components.site-header')

    <main>
        <div class="container">
            @yield('content')
        </div>
    </main>

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
