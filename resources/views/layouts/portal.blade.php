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
<body class="min-h-screen flex flex-col font-body text-gray-800 dark:text-gray-200 dark:bg-gray-900 portal {{ $bodyClass ?? '' }}">

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

    <header class="bg-gray-100 text-gray-900 border-b border-gray-300 dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700">
        <div class="max-w-7xl mx-auto px-4 py-2">
            <div class="flex items-center justify-between pb-1">
                <strong>{{ config('site.name', config('app.name')) }} — Member Area</strong>
                <div class="flex items-center gap-3 text-sm">
                    <span>{{ auth('portal')->user()->contact->first_name }}</span>
                    <form method="POST" action="{{ route('portal.logout') }}">
                        @csrf
                        <button type="submit" class="bg-transparent border-0 p-0 m-0 text-inherit text-sm cursor-pointer underline hover:opacity-75">Log out</button>
                    </form>
                </div>
            </div>
            <nav class="flex justify-end">
                <ul class="flex items-center gap-3 list-none m-0 p-0">
                    @foreach ($portalNav as $item)
                        @php
                            $href = ($item->page_id && $item->page) ? url('/' . $item->page->slug) : ($item->url ?? '#');
                        @endphp
                        <li>
                            <a href="{{ $href }}" {{ $currentUrl === $href ? 'aria-current="page"' : '' }} class="text-sm no-underline hover:underline">{{ $item->label }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </div>
    </header>

    <main class="flex-1">
        <div class="max-w-7xl mx-auto px-4 py-8">
            @yield('content')
        </div>
    </main>

    @if (!empty($inlineScripts))
        <script>{!! $inlineScripts !!}</script>
    @endif

    @stack('scripts')
</body>
</html>
