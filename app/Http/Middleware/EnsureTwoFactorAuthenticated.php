<?php

namespace App\Http\Middleware;

use App\Filament\Pages\TwoFactorChallenge;
use App\Filament\Pages\TwoFactorSetup;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Post-login enforcement gate for mandatory admin two-factor authentication
 * (session 359, A5). Runs in the admin panel's authMiddleware stack, so it only
 * fires once the user is authenticated. It redirects:
 *   - an un-enrolled user → the enrollment page
 *   - an enrolled user whose session hasn't passed the challenge → the challenge
 * and lets everyone else through.
 *
 * Two carve-outs are designed in, not patched on:
 *   - Demo mode (APP_ENV=demo) bypasses entirely, so the single-button
 *     /demo/enter auto-login stays frictionless and the demo user accrues no
 *     2FA state. This is the load-bearing exemption.
 *   - The test environment bypasses by default so the existing actingAs()
 *     suite stays green; the dedicated 2FA tests opt in via enableInTesting().
 */
class EnsureTwoFactorAuthenticated
{
    /** Session flag marking that this session has cleared the second factor. */
    public const SESSION_KEY = 'two_factor_passed';

    /** Opt-in switch for the test environment (off by default — see class doc). */
    protected static bool $enabledInTesting = false;

    public static function enableInTesting(): void
    {
        static::$enabledInTesting = true;
    }

    public static function disableInTesting(): void
    {
        static::$enabledInTesting = false;
    }

    /** Mark the current session as having cleared the second factor. */
    public static function markPassed(): void
    {
        session()->put(self::SESSION_KEY, true);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Defensive: authMiddleware guarantees auth, but never gate an
        // unauthenticated request (the login form must stay reachable).
        if (! $user) {
            return $next($request);
        }

        // Demo mode: frictionless entry, no second factor, no 2FA state. The
        // demo role is only ever seeded under APP_ENV=demo, so this predicate is
        // the clean, non-leaky exemption — a real install never matches it.
        if (isDemoMode()) {
            return $next($request);
        }

        // Test suite: bypass by default so unrelated actingAs() tests don't get
        // redirected to enrollment; targeted 2FA tests flip enableInTesting().
        if (app()->environment('testing') && ! static::$enabledInTesting) {
            return $next($request);
        }

        // Never gate the 2FA flow pages or logout themselves, or we'd loop.
        if ($this->isExemptRoute($request)) {
            return $next($request);
        }

        if (! $user->hasConfirmedTwoFactor()) {
            return redirect()->guest(TwoFactorSetup::getUrl());
        }

        if (! $request->session()->get(self::SESSION_KEY)) {
            return redirect()->guest(TwoFactorChallenge::getUrl());
        }

        return $next($request);
    }

    protected function isExemptRoute(Request $request): bool
    {
        return in_array($request->route()?->getName(), [
            TwoFactorSetup::ROUTE,
            TwoFactorChallenge::ROUTE,
            'filament.admin.auth.logout',
        ], true);
    }
}
