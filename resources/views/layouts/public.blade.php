<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @php
        use App\Models\SiteSetting;
        use App\Models\Template;
        use App\Services\Media\ChromeRenderer;
        use App\Services\SeoMetaGenerator;

        // Resolve template (shared by PageController, or fall back to default)
        $__tpl = $__template ?? Template::query()->default()->first();

        // Chrome slot resolution (session-301): per slot, independently —
        //   suppressed (no_header/no_footer) → none, WINS even if a page is set
        //   page_id set                      → the template's own chrome page
        //   page_id null                     → the theme header/footer
        // The page-id null=inherit semantic is unchanged; suppression is the
        // new, distinct "none" state. Mostly formalising existing inheritance.
        $__headerSlot = $__tpl?->chromeSlot('header') ?? ['suppressed' => false, 'page_id' => null];
        $__footerSlot = $__tpl?->chromeSlot('footer') ?? ['suppressed' => false, 'page_id' => null];

        $__suppressHeader = $__headerSlot['suppressed'];
        $__suppressFooter = $__footerSlot['suppressed'];
        $__headerPageId   = $__headerSlot['page_id'];
        $__footerPageId   = $__footerSlot['page_id'];

        $__chromeHeader = (! $__suppressHeader && ! view()->exists('custom.header')) && $__headerPageId
            ? ChromeRenderer::renderById($__headerPageId)
            : ((! $__suppressHeader && ! view()->exists('custom.header')) ? ChromeRenderer::render('_header') : null);
        $__chromeFooter = (! $__suppressFooter && ! view()->exists('custom.footer')) && $__footerPageId
            ? ChromeRenderer::renderById($__footerPageId)
            : ((! $__suppressFooter && ! view()->exists('custom.footer')) ? ChromeRenderer::render('_footer') : null);

        // Per-template scheme: request-time inline --np-color-* override for
        // the content region only, resolved from the Template via the single
        // shared resolver (the same call the page-builder preview makes — the
        // .np-site fidelity guarantee). Never bundled (session-295 lesson);
        // Default scheme = '' = the bundle's .np-site 297 defaults unchanged.
        // Applied to <main> only so the standard chrome keeps its vetted
        // colours (compose-not-bleed).
        $__contentSchemeVars = \App\Services\TemplateAppearanceResolver::inlineVars($__tpl);

        $siteName = SiteSetting::get('site_name', config('site.name', config('app.name')));
        $baseUrl  = rtrim(SiteSetting::get('base_url', config('app.url')), '/');
        $seo      = isset($page) ? SeoMetaGenerator::forPage($page) : null;

        // Title: meta_title → page title → site name
        $resolvedTitle = $title ?? ($seo['title'] ?? $siteName);

        // Description: meta_description → auto-extracted → site description
        $resolvedDescription = $description
            ?? ($seo['description'] ?? SiteSetting::get('site_description', ''));

        // OG image: SeoMetaGenerator owns the per-page → first widget image → site default chain.
        $resolvedOgImage = $seo['og_image'] ?? '';

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

    @php
        $__noindexGlobal = SiteSetting::get('noindex_global', 'false') === 'true';
        $__noindexPage   = ! empty($page) && $page->noindex;
    @endphp
    @if ($__noindexGlobal)
        <meta name="robots" content="noindex,nofollow">
    @elseif ($__noindexPage)
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

        // Brand / header / footer / nav colours are no longer per-template.
        // They are Theme tokens (--np-color-*) compiled into the public bundle
        // via AssetBuildService, scoped .np-site (see _base.scss) — the
        // session-297 relocation. No runtime inline colour <style> here.

        // Typography element CSS (per-breakpoint sizes + em rhythm) is compiled
        // into the public CSS bundle via AssetBuildService (see the build-server
        // <link> below), the same delivery path as the button overrides — no
        // runtime inline <style>. Only the font-family :root vars + the Google
        // Fonts <link> are resolved here at request time.
        $__typography    = \App\Services\TypographyResolver::load();
        $headingFamily   = $__typography['buckets']['heading_family'] ?? null;
        $bodyFamily      = $__typography['buckets']['body_family'] ?? null;
        if ($headingFamily) {
            $cssVars[] = "--font-family-heading: {$headingFamily}";
        }
        if ($bodyFamily) {
            $cssVars[] = "--font-family-body: {$bodyFamily}";
        }

        $fontsToLoad   = \App\Services\TypographyCompiler::googleFontsUsed($__typography);
        $googleFontsUrl = '';
        if ($fontsToLoad) {
            $families = collect($fontsToLoad)
                ->map(fn ($f) => 'family=' . str_replace(' ', '+', $f) . ':wght@400;600;700')
                ->implode('&');
            $googleFontsUrl = 'https://fonts.googleapis.com/css2?' . $families . '&display=swap';
        }
    @endphp

    @if ($googleFontsUrl)
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="{!! $googleFontsUrl !!}">
    @endif

    @if ($cssVars)
        <style>:root { {!! implode('; ', $cssVars) !!}; }</style>
    @endif

    {{-- Widget CSS/JS bundle from build server manifest --}}
    @php
        $__widgetManifest = null;
        $__manifestPath = public_path('build/widgets/manifest.json');
        if (file_exists($__manifestPath)) {
            $__widgetManifest = json_decode(file_get_contents($__manifestPath), true);
        }
    @endphp
    @if ($__widgetManifest && ! empty($__widgetManifest['css']))
        <link rel="stylesheet" href="/build/widgets/{{ $__widgetManifest['css'] }}">
    @endif

    {{-- Template custom SCSS — runtime compilation (moves to build server post-beta) --}}
    @php
        $__templateScss = $__tpl?->resolved('custom_scss');
        $__compiledCss = '';
        if ($__templateScss) {
            $__cacheKey = 'widget_scss_' . md5($__templateScss);
            $__compiledCss = cache()->remember($__cacheKey, 3600, function () use ($__templateScss) {
                $compiler = new \ScssPhp\ScssPhp\Compiler();
                return $compiler->compileString($__templateScss)->getCss();
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
<body class="np-site {{ $bodyClass ?? 'page-unknown' }}">

    {{-- Site-wide body-open snippet --}}
    {!! SiteSetting::get('site_body_open_snippet', '') !!}

    @php
        $__navStyle = '';
        if (!empty($__navOverlayLinkColor ?? '')) $__navStyle .= '--overlay-nav-link-color:' . $__navOverlayLinkColor . ';';
        if (!empty($__navOverlayHoverColor ?? '')) $__navStyle .= '--overlay-nav-hover-color:' . $__navOverlayHoverColor . ';';
    @endphp
    @unless ($__suppressHeader ?? false)
    <div class="site-nav-wrapper {{ ($__navOverlap ?? false) ? 'site-nav-wrapper--overlay' : '' }}" @if ($__navStyle) style="{{ $__navStyle }}" @endif>
        @if (view()->exists('custom.header'))
            @include('custom.header')
        @elseif ($__chromeHeader)
            <header class="site-header">{!! $__chromeHeader['html'] !!}</header>
        @else
            @include('components.site-header')
        @endif
    </div>
    @endunless

    <main @if ($__contentSchemeVars) style="{{ $__contentSchemeVars }}" @endif>
        @yield('content')
    </main>

    @unless ($__suppressFooter ?? false)
        @if (view()->exists('custom.footer'))
            @include('custom.footer')
        @elseif ($__chromeFooter)
            <footer class="site-footer">{!! $__chromeFooter['html'] !!}</footer>
        @else
            @include('components.site-footer')
        @endif
    @endunless

    <script>
    window.__site = {
        name: @json(config('site.name', config('app.name'))),
        blogPrefix: @json(config('site.blog_prefix', 'news')),
        contactEmail: @json(config('site.contact_email', '')),
    };
    </script>

    {{-- Widget JS bundle from build server manifest --}}
    @if ($__widgetManifest && ! empty($__widgetManifest['js']))
        <script src="/build/widgets/{{ $__widgetManifest['js'] }}"></script>
    @endif

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
