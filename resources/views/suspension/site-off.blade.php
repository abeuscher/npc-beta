@php
    // Self-contained branded shell (see suspension/admin-locked for the rationale).
    // This is the manual, operator-pushed "nuclear" shutoff (site_off): the whole
    // public site + member portal render this static 503 notice. Only Fleet
    // Manager's /api/* endpoints stay up (a shut-off node is still monitored and
    // recoverable). Deliberately static — no self-cure affordance, because only a
    // human operator pushes and lifts this state.
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
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-suspension-notice="site-off">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Temporarily offline · {{ $__siteName }}</title>
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
        .offline-card {
            width: 100%;
            max-width: 32rem;
            text-align: center;
        }
        .offline-brand {
            font-size: 0.875rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--brand);
            margin: 0 0 1.5rem;
        }
        .offline-title {
            font-size: 1.75rem;
            font-weight: 800;
            margin: 0 0 0.85rem;
            color: #111827;
        }
        .offline-message {
            font-size: 1rem;
            color: #4b5563;
            margin: 0;
        }
    </style>
</head>
<body>
    <main class="offline-card">
        <p class="offline-brand">{{ $__siteName }}</p>
        <h1 class="offline-title">We’ll be back soon</h1>
        <p class="offline-message">This site is temporarily offline for maintenance. Please check back shortly.</p>
    </main>
</body>
</html>
