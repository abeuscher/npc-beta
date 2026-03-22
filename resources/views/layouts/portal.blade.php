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

    <header class="portal-header">
        <div class="container">
            <nav>
                <strong>{{ config('site.name', config('app.name')) }} — Member Area</strong>
                <ul>
                    <li>{{ auth('portal')->user()->contact->first_name }}</li>
                    <li>
                        <form method="POST" action="{{ route('portal.logout') }}">
                            @csrf
                            <button type="submit" class="secondary outline">Log out</button>
                        </form>
                    </li>
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
