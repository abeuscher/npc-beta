<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $title ?? (config('site.name', config('app.name')) . ' — Member Area') }}</title>

    @vite(['resources/scss/public.scss', 'resources/js/public.js'])

    @if (!empty($inlineStyles))
        <style>{!! $inlineStyles !!}</style>
    @endif

    @stack('styles')
</head>
<body class="portal {{ $bodyClass ?? '' }}">

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
        <div class="container">
            <div class="portal-header__top">
                <strong>{{ config('site.name', config('app.name')) }} — Member Area</strong>
                <div class="portal-header__user">
                    <span>{{ auth('portal')->user()->contact->first_name }}</span>
                    <form method="POST" action="{{ route('portal.logout') }}">
                        @csrf
                        <button type="submit" class="portal-logout-link">Log out</button>
                    </form>
                </div>
            </div>
            <nav class="portal-header__nav">
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

    <main>
        <div class="container">
            @yield('content')
        </div>
    </main>

    @if (!empty($inlineScripts))
        <script>{!! $inlineScripts !!}</script>
    @endif

    @stack('scripts')
</body>
</html>
