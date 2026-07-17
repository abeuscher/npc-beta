<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Perimeter security headers (session 370, Security S1).
 *
 * Sets the app-layer security-header baseline on every web/admin response:
 * X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy,
 * HSTS (secure requests only), and a Content-Security-Policy. The public/portal
 * surface is CSP-enforced; the admin panel ships Report-Only until Filament CSP
 * compatibility is proven (see config/security.php).
 *
 * The Fleet Manager /api/* contract surface is deliberately out of scope — the
 * `api` route group is not routed through this middleware, and the early return
 * below is a defensive backstop so a future route reusing it can never alter an
 * FM response.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Fix a per-request CSP nonce before the view renders, so `@vite` and the
        // legitimate inline framework <script> tags can carry it and satisfy a
        // script-src without 'unsafe-inline'.
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        if ($request->is('api/*')) {
            return $response;
        }

        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', (string) config('security.referrer_policy'));

        if ($permissionsPolicy = config('security.permissions_policy')) {
            $headers->set('Permissions-Policy', (string) $permissionsPolicy);
        }

        if ($request->secure()) {
            $headers->set('Strict-Transport-Security', $this->hstsValue());
        }

        [$cspHeader, $cspValue] = $this->contentSecurityPolicy($this->isAdminSurface($request), $nonce);
        $headers->set($cspHeader, $cspValue);

        return $response;
    }

    private function hstsValue(): string
    {
        $value = 'max-age=' . (int) config('security.hsts.max_age');

        if (config('security.hsts.include_subdomains')) {
            $value .= '; includeSubDomains';
        }

        if (config('security.hsts.preload')) {
            $value .= '; preload';
        }

        return $value;
    }

    /**
     * The admin panel lives under the ADMIN_PATH prefix (default "admin"); its
     * in-panel API groups sit under the same prefix. Everything else — public
     * pages, the member portal — takes the enforced public policy.
     */
    private function isAdminSurface(Request $request): bool
    {
        $adminPath = trim((string) env('ADMIN_PATH', 'admin'), '/');

        return $adminPath !== '' && $request->is($adminPath, $adminPath . '/*');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function contentSecurityPolicy(bool $isAdmin, string $nonce): array
    {
        // Extra host sources per directive, resolved from config (the env floor
        // plus any admin-edited hosts merged in by AppServiceProvider). Split on
        // commas, whitespace, and newlines so both the env (comma) and admin
        // textarea (one-per-line) forms parse uniformly.
        $extra = fn (string $key): array => array_values(array_filter(array_map(
            'trim',
            preg_split('/[\s,]+/', trim((string) config("security.csp.extra.$key"))) ?: []
        )));

        $scriptSrc = array_merge(["'self'", "'nonce-{$nonce}'"], $extra('script_src'));
        if ($isAdmin) {
            // Alpine evaluates x-data / x-bind expressions via new Function(),
            // which CSP gates behind 'unsafe-eval'. Filament's admin panel does
            // not function without it.
            $scriptSrc[] = "'unsafe-eval'";
        }

        // Inline styles are pervasive (widget appearance composition); accepted
        // trade-off at v1. Google Fonts stylesheet is loaded on the public site.
        $styleSrc = array_merge(
            ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'],
            $extra('style_src')
        );

        $imgSrc = array_merge(
            ["'self'", 'data:', 'blob:'],
            $this->mediaHosts(),
            $extra('img_src')
        );

        $fontSrc = array_merge(
            ["'self'", 'https://fonts.gstatic.com', 'data:'],
            $extra('font_src')
        );

        $connectSrc = array_merge(["'self'"], $extra('connect_src'));

        // VideoEmbed (YouTube/Vimeo) and MapEmbed (Google Maps) widgets embed
        // these origins in iframes.
        $frameSrc = array_merge([
            "'self'",
            'https://www.youtube-nocookie.com',
            'https://player.vimeo.com',
            'https://www.google.com',
            'https://maps.google.com',
        ], $extra('frame_src'));

        $directives = [
            "default-src 'self'",
            'script-src ' . implode(' ', array_unique($scriptSrc)),
            'style-src ' . implode(' ', array_unique($styleSrc)),
            'img-src ' . implode(' ', array_unique($imgSrc)),
            'font-src ' . implode(' ', array_unique($fontSrc)),
            'connect-src ' . implode(' ', array_unique($connectSrc)),
            'frame-src ' . implode(' ', array_unique($frameSrc)),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ];

        $reportOnly = $isAdmin
            ? (bool) config('security.csp.admin_report_only')
            : (bool) config('security.csp.public_report_only');

        return [
            $reportOnly ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy',
            implode('; ', $directives),
        ];
    }

    /**
     * Media may be served from a different origin in production (DigitalOcean
     * Spaces / a CDN). Derive that origin from the configured media disk so
     * img-src adapts per-node without a hand-maintained convention; local dev
     * serves media same-origin and yields nothing extra.
     *
     * @return array<int, string>
     */
    private function mediaHosts(): array
    {
        try {
            $disk = (string) config('media-library.disk_name', 'public');
            $url = Storage::disk($disk)->url('probe');
            $host = parse_url($url, PHP_URL_HOST);

            if (! $host || $host === parse_url((string) config('app.url'), PHP_URL_HOST)) {
                return [];
            }

            $scheme = parse_url($url, PHP_URL_SCHEME) ?: 'https';

            return [$scheme . '://' . $host];
        } catch (\Throwable) {
            return [];
        }
    }
}
