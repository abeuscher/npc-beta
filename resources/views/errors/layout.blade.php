@php
    // Self-contained branded shell. Deliberately does NOT extend the public
    // layout: that layout runs DB queries, ChromeRenderer, runtime SCSS and the
    // widget manifest, any of which can fail on a hard 500 and turn the error
    // page itself into an exception. Brand colour + site name are read
    // defensively so a healthy app renders fully on-brand while a degraded one
    // (DB down) still falls back to neutral defaults and renders.
    $__brand    = '#0172ad';
    $__siteName = config('app.name', 'NonProfitCRM');
    try {
        $__brand    = \App\Services\ColorTokenResolver::load()['brand'] ?? $__brand;
        $__siteName = \App\Models\SiteSetting::get('site_name', $__siteName);
    } catch (\Throwable $e) {
        // DB / config unavailable — keep the neutral defaults above.
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('code') · {{ $__siteName }}</title>
    <style>
        :root { --brand: {{ $__brand }}; }
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #1f2937;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            line-height: 1.5;
        }
        .error-card {
            width: 100%;
            max-width: 32rem;
            text-align: center;
        }
        .error-brand {
            font-size: 0.875rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--brand);
            margin: 0 0 1.5rem;
        }
        .error-code {
            font-size: 4.5rem;
            font-weight: 800;
            line-height: 1;
            color: var(--brand);
            margin: 0 0 0.5rem;
        }
        .error-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 0 0.75rem;
            color: #111827;
        }
        .error-message {
            font-size: 1rem;
            color: #4b5563;
            margin: 0 0 2rem;
        }
        .error-home {
            display: inline-block;
            padding: 0.7rem 1.4rem;
            border-radius: 0.5rem;
            background: var(--brand);
            color: #ffffff;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9375rem;
        }
        .error-home:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <main class="error-card">
        <p class="error-brand">{{ $__siteName }}</p>
        <p class="error-code">@yield('code')</p>
        <h1 class="error-title">@yield('title')</h1>
        <p class="error-message">@yield('message')</p>
        <a href="{{ url('/') }}" class="error-home">Back to home</a>
    </main>
</body>
</html>
