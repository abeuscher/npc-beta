<?php

namespace App\Http\Middleware;

use App\Services\Billing\BillingStateReader;
use App\Services\Billing\SuspensionState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Node-side enforcement of the pushed `SUSPENSION_STATE` flag (client billing,
 * contract v2.6.0). A single class, registered on two surfaces via a middleware
 * parameter so it can lock each correctly:
 *
 *   - `:admin`  — the Filament admin panel base stack (covers the panel, its
 *                 login, and the in-panel API route groups registered on the
 *                 panel). Locks under `admin_locked` and `site_off`.
 *   - `:public` — the `web` middleware group (public site + member portal).
 *                 Locks only under `site_off`; `admin_locked` deliberately leaves
 *                 it alone.
 *
 * It is NOT registered on the `api` group, so all five Fleet Manager `/api/*`
 * contract endpoints stay reachable under every state — a suspended node is still
 * monitored, backed up, and recoverable.
 *
 * ENFORCEMENT RIDES THE ENV FLAG; DISPLAY RIDES THE DOCUMENT. The state is
 * resolved from config alone (SuspensionState), so the gate locks correctly with
 * no billing-state document present (generic copy). The document only ever
 * improves the lock-screen copy — it never changes whether or what we lock.
 *
 * Grain deliberately mirrors demo mode (isDemoMode()): a hard, env-derived,
 * code-level gate. The two are orthogonal — demo/internal nodes simply never get
 * a suspension push.
 */
class EnforceSuspensionState
{
    public const SURFACE_ADMIN = 'admin';
    public const SURFACE_PUBLIC = 'public';

    public function handle(Request $request, Closure $next, string $surface = self::SURFACE_ADMIN): Response
    {
        $state = SuspensionState::current();

        // site_off — the manual "nuclear" shutoff. Both surfaces render the
        // static maintenance notice (503). Only the FM /api/* endpoints, which
        // never carry this middleware, stay up.
        if ($state === SuspensionState::SiteOff) {
            return response()->view('suspension.site-off', [], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        // admin_locked — the back office is locked; the public site, donation /
        // event / membership checkout, and the member portal stay up (the lock
        // punishes the org's back office, never its constituents).
        if ($state === SuspensionState::AdminLocked && $surface === self::SURFACE_ADMIN) {
            return $this->adminLockedResponse();
        }

        return $next($request);
    }

    /**
     * Render the admin suspension notice — reason-appropriate copy plus the
     * self-cure affordances (Stripe-hosted portal link + billing contact on
     * file), read from the pushed billing-state document. With no document, the
     * null-object yields generic copy and the affordances simply don't render.
     */
    private function adminLockedResponse(): Response
    {
        $billing = app(BillingStateReader::class)->read();

        return response()->view('suspension.admin-locked', [
            'reason' => $billing->reason(),
            'portalUrl' => $billing->portalUrl(),
            'billingContactEmail' => $billing->billingContactEmail(),
        ], Response::HTTP_FORBIDDEN);
    }
}
