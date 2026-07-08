@php
    // Self-contained branded shell — deliberately does NOT extend the public
    // layout (DB/ChromeRenderer/runtime-SCSS work that can fail on a degraded
    // node), mirroring the errors.layout defensiveness. Brand + site name are
    // read defensively so a healthy node renders on-brand and a degraded one
    // still renders on neutral defaults.
    $__brand    = '#0172ad';
    $__siteName = config('app.name', 'NonProfitCRM');
    try {
        $__brand    = \App\Services\ColorTokenResolver::load()['brand'] ?? $__brand;
        $__siteName = \App\Models\SiteSetting::get('site_name', $__siteName);
    } catch (\Throwable $e) {
        // DB / config unavailable — keep the neutral defaults above.
    }

    // Reason-appropriate copy. The billing-state document supplies $reason
    // (delinquent / trial_expired / canceled / manual); with no document it is
    // null and the generic copy applies. Framing per the grace model
    // (Stripe dunning → 14-day grace → lock): the public site, donations, and
    // member portal keep running — only the back office is paused.
    $__copy = match ($reason) {
        'delinquent' => [
            'title' => 'A billing issue has paused admin access',
            'body'  => 'We weren’t able to process a recent payment, so access to the admin area is paused. Your public site, donations, and member portal are still running — this only affects the back office. Settle the balance through the billing portal to restore access right away.',
        ],
        'trial_expired' => [
            'title' => 'Your trial has ended',
            'body'  => 'The trial period for this site has ended and the admin area is paused. Your public site and member portal are still running. Start a subscription to restore admin access — nothing has been deleted.',
        ],
        'canceled' => [
            'title' => 'Your subscription has ended',
            'body'  => 'This subscription has been canceled and the admin area is paused. Your public site stays online during the wind-down period. Get in touch to reactivate — nothing has been deleted.',
        ],
        'manual' => [
            'title' => 'Admin access is paused',
            'body'  => 'Access to the admin area has been paused for this site. Your public site, donations, and member portal are unaffected. Get in touch to restore access.',
        ],
        default => [
            'title' => 'Admin access is paused',
            'body'  => 'Access to the admin area is currently paused for a billing reason. Your public site, donations, and member portal are unaffected. Use the billing portal or contact us to restore access.',
        ],
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-suspension-notice="admin-locked">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Admin access paused · {{ $__siteName }}</title>
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
        .lock-card {
            width: 100%;
            max-width: 34rem;
            text-align: center;
        }
        .lock-brand {
            font-size: 0.875rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--brand);
            margin: 0 0 1.5rem;
        }
        .lock-title {
            font-size: 1.75rem;
            font-weight: 800;
            margin: 0 0 0.85rem;
            color: #111827;
        }
        .lock-message {
            font-size: 1rem;
            color: #4b5563;
            margin: 0 0 2rem;
        }
        .lock-portal {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            background: var(--brand);
            color: #ffffff;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9375rem;
        }
        .lock-portal:hover { opacity: 0.9; }
        .lock-contact {
            font-size: 0.9375rem;
            color: #6b7280;
            margin: 1.75rem 0 0;
        }
        .lock-contact a { color: var(--brand); }
    </style>
</head>
<body>
    <main class="lock-card">
        <p class="lock-brand">{{ $__siteName }}</p>
        <h1 class="lock-title">{{ $__copy['title'] }}</h1>
        <p class="lock-message">{{ $__copy['body'] }}</p>

        @if (! empty($portalUrl))
            <a href="{{ $portalUrl }}" class="lock-portal" rel="noopener noreferrer">Open the billing portal</a>
        @endif

        @if (! empty($billingContactEmail))
            <p class="lock-contact">
                Billing contact on file:
                <a href="mailto:{{ $billingContactEmail }}">{{ $billingContactEmail }}</a>
            </p>
        @endif
    </main>
</body>
</html>
