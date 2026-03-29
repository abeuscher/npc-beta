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
            $cssVars[] = "--color-primary: {$primaryColor}";
        }
        if ($headingFont) {
            $cssVars[] = "--font-family-heading: {$headingFont}";
        }
        if ($bodyFont) {
            $cssVars[] = "--font-family-body: {$bodyFont}";
        }

        // Extract Google Font names from font-stack values and build the URL
        $googleFonts = ['Inter', 'Lato', 'Merriweather', 'Montserrat', 'Open Sans', 'Playfair Display', 'Raleway', 'Source Sans 3'];
        $fontsToLoad = [];
        foreach ([$headingFont, $bodyFont] as $fontStack) {
            if (!$fontStack) continue;
            foreach ($googleFonts as $gFont) {
                if (str_contains($fontStack, $gFont) && !in_array($gFont, $fontsToLoad)) {
                    $fontsToLoad[] = $gFont;
                }
            }
        }
        $googleFontsUrl = '';
        if ($fontsToLoad) {
            $families = collect($fontsToLoad)
                ->map(fn ($f) => 'family=' . str_replace(' ', '+', $f) . ':wght@400;600;700')
                ->implode('&');
            $googleFontsUrl = 'https://fonts.googleapis.com/css2?' . $families . '&display=swap';
        }

        // Scoped header/nav colour rules — also applied to footer nav/icons for consistency
        $headerBgColor  = \App\Models\SiteSetting::get('header_bg_color');
        $footerBgColor  = \App\Models\SiteSetting::get('footer_bg_color');
        $navLinkColor   = \App\Models\SiteSetting::get('nav_link_color');
        $navHoverColor  = \App\Models\SiteSetting::get('nav_hover_color');
        $navActiveColor = \App\Models\SiteSetting::get('nav_active_color');

        $scopedRules = [];
        if ($headerBgColor) $scopedRules[] = "header { background: {$headerBgColor}; }";
        if ($footerBgColor) $scopedRules[] = "footer { background: {$footerBgColor}; }";
        if ($navLinkColor) {
            $scopedRules[] = "header nav a, footer nav a, footer .theme-toggle { color: {$navLinkColor}; }";
        }
        if ($navHoverColor) {
            $scopedRules[] = "header nav a:hover, footer nav a:hover { color: {$navHoverColor}; }";
        }
        if ($navActiveColor) $scopedRules[] = 'header nav a[aria-current="page"] { color: ' . $navActiveColor . '; }';
    @endphp

    @if ($googleFontsUrl)
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="{!! $googleFontsUrl !!}">
    @endif

    @if ($cssVars)
        <style>:root { {!! implode('; ', $cssVars) !!}; }</style>
    @endif

    @if ($scopedRules)
        <style>{!! implode(' ', $scopedRules) !!}</style>
    @endif

    {{-- Inline CSS collected from active page widgets --}}
    @if (!empty($inlineStyles))
        <style>{!! $inlineStyles !!}</style>
    @endif

    @stack('styles')
</head>
<body class="min-h-screen flex flex-col font-body text-gray-800 dark:text-gray-200 dark:bg-gray-900 {{ $bodyClass ?? 'page-unknown' }}">

    @include(view()->exists('custom.header') ? 'custom.header' : 'components.site-header')

    <main class="flex-1">
        <div class="max-w-7xl mx-auto px-4 py-8">
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
