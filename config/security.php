<?php

return [

    /*
    |--------------------------------------------------------------------------
    | HTTP Strict Transport Security
    |--------------------------------------------------------------------------
    |
    | Emitted by the SecurityHeaders middleware only on secure (HTTPS) requests,
    | so a plain-HTTP dev session on http://localhost never pins HSTS. Nginx also
    | sets it at the TLS edge in prod.conf; the middleware value is the app-layer
    | belt-and-suspenders for any non-nginx deployment.
    |
    */

    'hsts' => [
        'max_age'            => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
        'include_subdomains' => (bool) env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', true),
        'preload'            => (bool) env('SECURITY_HSTS_PRELOAD', false),
    ],

    'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),

    // Deny-by-default powerful features. Kept short deliberately (S1 out-of-scope
    // note: no deep permissions-policy tuning this session).
    'permissions_policy' => env(
        'SECURITY_PERMISSIONS_POLICY',
        'camera=(), microphone=(), geolocation=(), browsing-topics=()'
    ),

    /*
    |--------------------------------------------------------------------------
    | Content-Security-Policy
    |--------------------------------------------------------------------------
    |
    | The public/portal surface is enforced from day one. The admin panel ships
    | Report-Only (Filament + Alpine + Livewire CSP compatibility is unproven —
    | flip SECURITY_CSP_ADMIN_REPORT_ONLY=false once validated). Both are env
    | toggles so the posture can move without a code change.
    |
    | style-src carries 'unsafe-inline' by design: the widget appearance layer
    | composes inline style attributes pervasively. The win fought for is a
    | strict script-src (nonce, no 'unsafe-inline').
    |
    | The `extra` host lists are the per-node escape valve for the operator
    | code-snippet feature (Google Tag Manager / Analytics live in the CMS
    | head/body snippet fields and load external script hosts). A stock node
    | ships the strict policy with empty lists; a node that uses analytics opts
    | its specific hosts in via env — the protection layer stays strict, the
    | friction moves to per-node configuration.
    |
    */

    'csp' => [
        'public_report_only' => (bool) env('SECURITY_CSP_PUBLIC_REPORT_ONLY', false),
        'admin_report_only'  => (bool) env('SECURITY_CSP_ADMIN_REPORT_ONLY', true),

        'extra' => [
            'script_src'  => env('SECURITY_CSP_SCRIPT_SRC_EXTRA', ''),
            'style_src'   => env('SECURITY_CSP_STYLE_SRC_EXTRA', ''),
            'img_src'     => env('SECURITY_CSP_IMG_SRC_EXTRA', ''),
            'font_src'    => env('SECURITY_CSP_FONT_SRC_EXTRA', ''),
            'connect_src' => env('SECURITY_CSP_CONNECT_SRC_EXTRA', ''),
            'frame_src'   => env('SECURITY_CSP_FRAME_SRC_EXTRA', ''),
        ],
    ],
];
