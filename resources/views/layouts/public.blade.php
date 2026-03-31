<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @php
        use App\Models\SiteSetting;
        use App\Models\Template;
        use App\Services\ChromeRenderer;
        use App\Services\SeoMetaGenerator;

        // Resolve template (shared by PageController, or fall back to default)
        $__tpl = $__template ?? Template::query()->default()->first();

        // Pre-compute chrome (header/footer) from template-resolved page IDs
        $__headerPageId = $__tpl?->resolved('header_page_id');
        $__footerPageId = $__tpl?->resolved('footer_page_id');

        $__chromeHeader = ! view()->exists('custom.header') && $__headerPageId
            ? ChromeRenderer::renderById($__headerPageId)
            : (! view()->exists('custom.header') ? ChromeRenderer::render('_header') : null);
        $__chromeFooter = ! view()->exists('custom.footer') && $__footerPageId
            ? ChromeRenderer::renderById($__footerPageId)
            : (! view()->exists('custom.footer') ? ChromeRenderer::render('_footer') : null);

        $siteName = SiteSetting::get('site_name', config('site.name', config('app.name')));
        $baseUrl  = rtrim(SiteSetting::get('base_url', config('app.url')), '/');
        $seo      = isset($page) ? SeoMetaGenerator::forPage($page) : null;

        // Title: meta_title → page title → site name
        $resolvedTitle = $title ?? ($seo['title'] ?? $siteName);

        // Description: meta_description → auto-extracted → site description
        $resolvedDescription = $description
            ?? ($seo['description'] ?? SiteSetting::get('site_description', ''));

        // OG image: per-page → first widget image → site default
        $resolvedOgImage = $seo['og_image'] ?? '';
        if (! $resolvedOgImage) {
            $defaultOgPath = SiteSetting::get('site_default_og_image', '');
            $resolvedOgImage = $defaultOgPath
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($defaultOgPath)
                : '';
        }

        // Canonical URL
        $canonicalUrl = $seo['canonical'] ?? $baseUrl;

        // OG type
        $ogType = $seo['og_type'] ?? 'website';

        // Favicon
        $faviconPath = SiteSetting::get('favicon_path', '');
        $faviconUrl  = $faviconPath
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($faviconPath)
            : '';
    @endphp

    <title>{{ $resolvedTitle }}</title>

    @if ($resolvedDescription)
        <meta name="description" content="{{ $resolvedDescription }}">
    @endif

    <meta property="og:title" content="{{ $resolvedTitle }}">
    @if ($resolvedDescription)
        <meta property="og:description" content="{{ $resolvedDescription }}">
    @endif
    @if ($resolvedOgImage)
        <meta property="og:image" content="{{ $resolvedOgImage }}">
    @endif
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:type" content="{{ $ogType }}">

    <link rel="canonical" href="{{ $canonicalUrl }}">

    @if (! empty($page) && $page->noindex)
        <meta name="robots" content="noindex">
    @endif

    @if ($faviconUrl)
        <link rel="icon" href="{{ $faviconUrl }}">
    @endif

    @if ($seo['json_ld'] ?? null)
        <script type="application/ld+json">{!! $seo['json_ld'] !!}</script>
    @endif

    @vite(['resources/scss/public.scss', 'resources/js/public.js'])

    @php
        $cssVars = [];

        $primaryColor = $__tpl?->resolved('primary_color');
        $headingFont  = $__tpl?->resolved('heading_font');
        $bodyFont     = $__tpl?->resolved('body_font');

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
        $headerBgColor  = $__tpl?->resolved('header_bg_color');
        $footerBgColor  = $__tpl?->resolved('footer_bg_color');
        $navLinkColor   = $__tpl?->resolved('nav_link_color');
        $navHoverColor  = $__tpl?->resolved('nav_hover_color');
        $navActiveColor = $__tpl?->resolved('nav_active_color');

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

    {{-- External CSS assets declared by widget types --}}
    @php
        $__widgetAssets = ['css' => [], 'js' => [], 'scss' => []];
        foreach (['css', 'js', 'scss'] as $__aType) {
            foreach (($widgetAssets[$__aType] ?? []) as $__aPath) {
                $__widgetAssets[$__aType][] = $__aPath;
            }
            if ($__chromeHeader) {
                foreach (($__chromeHeader['assets'][$__aType] ?? []) as $__aPath) {
                    if (! in_array($__aPath, $__widgetAssets[$__aType], true)) {
                        $__widgetAssets[$__aType][] = $__aPath;
                    }
                }
            }
            if ($__chromeFooter) {
                foreach (($__chromeFooter['assets'][$__aType] ?? []) as $__aPath) {
                    if (! in_array($__aPath, $__widgetAssets[$__aType], true)) {
                        $__widgetAssets[$__aType][] = $__aPath;
                    }
                }
            }
        }
    @endphp
    @foreach ($__widgetAssets['css'] as $__cssPath)
        <link rel="stylesheet" href="{{ $__cssPath }}">
    @endforeach

    {{-- Compiled SCSS from widget assets + template custom SCSS --}}
    @php
        $__scssInput = '';
        foreach ($__widgetAssets['scss'] ?? [] as $__scssPath) {
            $__fullPath = base_path($__scssPath);
            if (file_exists($__fullPath)) {
                $__scssInput .= file_get_contents($__fullPath) . "\n";
            }
        }

        // Append template-resolved custom SCSS
        $__templateScss = $__tpl?->resolved('custom_scss');
        if ($__templateScss) {
            $__scssInput .= $__templateScss . "\n";
        }

        $__compiledCss = '';
        if ($__scssInput) {
            $__cacheKey = 'widget_scss_' . md5($__scssInput);
            $__compiledCss = cache()->remember($__cacheKey, 3600, function () use ($__scssInput) {
                $compiler = new \ScssPhp\ScssPhp\Compiler();
                return $compiler->compileString($__scssInput)->getCss();
            });
        }
    @endphp
    @if ($__compiledCss)
        <style>{!! $__compiledCss !!}</style>
    @endif

    {{-- Inline CSS collected from active page widgets + chrome widgets --}}
    @php
        $__allInlineStyles = ($inlineStyles ?? '');
        if ($__chromeHeader) $__allInlineStyles .= $__chromeHeader['styles'];
        if ($__chromeFooter) $__allInlineStyles .= $__chromeFooter['styles'];
    @endphp
    @if ($__allInlineStyles)
        <style>{!! $__allInlineStyles !!}</style>
    @endif

    @stack('styles')

    {{-- Site-wide head snippet --}}
    {!! SiteSetting::get('site_head_snippet', '') !!}

    {{-- Per-page head snippet --}}
    @if (! empty($page) && $page->head_snippet)
        {!! $page->head_snippet !!}
    @endif
</head>
<body class="min-h-screen flex flex-col font-body text-gray-800 dark:text-gray-200 dark:bg-gray-900 {{ $bodyClass ?? 'page-unknown' }}">

    {{-- Site-wide body-open snippet --}}
    {!! SiteSetting::get('site_body_open_snippet', '') !!}

    @if (view()->exists('custom.header'))
        @include('custom.header')
    @elseif ($__chromeHeader)
        {!! $__chromeHeader['html'] !!}
    @else
        @include('components.site-header')
    @endif

    <main class="flex-1">
        <div class="max-w-7xl mx-auto px-4 py-8">
            @yield('content')
        </div>
    </main>

    @if (view()->exists('custom.footer'))
        @include('custom.footer')
    @elseif ($__chromeFooter)
        {!! $__chromeFooter['html'] !!}
    @else
        @include('components.site-footer')
    @endif

    <script>
    window.__site = {
        name: @json(config('site.name', config('app.name'))),
        blogPrefix: @json(config('site.blog_prefix', 'news')),
        contactEmail: @json(config('site.contact_email', '')),
    };
    </script>

    {{-- External JS assets declared by widget types --}}
    @foreach ($__widgetAssets['js'] as $__jsPath)
        <script src="{{ $__jsPath }}"></script>
    @endforeach

    {{-- Inline JS collected from active page widgets + chrome widgets --}}
    @php
        $__allInlineScripts = ($inlineScripts ?? '');
        if ($__chromeHeader) $__allInlineScripts .= $__chromeHeader['scripts'];
        if ($__chromeFooter) $__allInlineScripts .= $__chromeFooter['scripts'];
    @endphp
    @if ($__allInlineScripts)
        <script>{!! $__allInlineScripts !!}</script>
    @endif

    @stack('scripts')

    {{-- Site-wide body snippet --}}
    {!! SiteSetting::get('site_body_snippet', '') !!}

    {{-- Per-page body snippet --}}
    @if (! empty($page) && $page->body_snippet)
        {!! $page->body_snippet !!}
    @endif
</body>
</html>
