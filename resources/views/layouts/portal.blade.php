<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $title ?? (config('site.name', config('app.name')) . ' — Member Area') }}</title>

    @vite(['resources/scss/public.scss', 'resources/js/public.js'])

    {{-- Widget CSS bundle from the build-server manifest — same delivery path as
         layouts.public, so page-builder widgets placed on portal pages (e.g. the
         dashboard BarChart) get their compiled CSS/JS here too. --}}
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

    @if (!empty($inlineStyles))
        <style>{!! $inlineStyles !!}</style>
    @endif

    @stack('styles')
</head>
<body class="np-site portal {{ $bodyClass ?? '' }}">

    @php
        $portalMenu  = \App\Models\NavigationMenu::where('handle', 'portal')->first();
        $portalNav   = $portalMenu
            ? $portalMenu->items()
                ->where('is_visible', true)
                ->orderBy('sort_order')
                ->with('page')
                ->get()
            : collect();
        $currentUrl  = url()->current();
    @endphp

    <header class="portal-header">
        <div class="site-container portal-header__bar">
            <div class="portal-header__top">
                <strong>{{ config('site.name', config('app.name')) }} — Member Area</strong>
                <div class="portal-header__actions">
                    <span>{{ auth('portal')->user()->contact->first_name }}</span>
                    <form method="POST" action="{{ route('portal.logout') }}">
                        @csrf
                        <button type="submit" class="btn btn--link text-sm">Log out</button>
                    </form>
                </div>
            </div>
            <nav class="portal-nav" data-tour="portal.nav">
                <ul>
                    @foreach ($portalNav as $item)
                        @php
                            $href = ($item->page_id && $item->page) ? url('/' . $item->page->slug) : ($item->url ?? '#');
                        @endphp
                        <li>
                            <a href="{{ $href }}" {{ $currentUrl === $href ? 'aria-current="page"' : '' }}>{{ $item->label }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </div>
    </header>

    @php
        $portalPrefix = \App\Models\SiteSetting::get('portal_prefix', 'members');
        $isDashboard  = isset($page) && $page->slug === $portalPrefix;
        $heroTitle    = $page->title ?? 'Member Area';
        $heroFirstName = auth('portal')->user()?->contact?->first_name;
    @endphp
    <section class="portal-hero"@if ($isDashboard) data-tour="portal.dashboard"@endif>
        <div class="site-container">
            <h1 class="portal-hero__title">{{ $heroTitle }}</h1>
            @if ($isDashboard && $heroFirstName)
                <p class="portal-hero__subtitle">Welcome back, {{ $heroFirstName }}.</p>
            @endif
        </div>
    </section>

    <main>
        <div class="site-container portal-content">
            @yield('content')
        </div>
    </main>

    @if (!empty($inlineScripts))
        <script>{!! $inlineScripts !!}</script>
    @endif

    {{-- Widget JS bundle from the build-server manifest (mirrors layouts.public). --}}
    @if ($__widgetManifest && ! empty($__widgetManifest['js']))
        <script src="/build/widgets/{{ $__widgetManifest['js'] }}"></script>
    @endif

    @stack('scripts')
</body>
</html>
