<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fleet agent app-layer gate (contract v2.7.0, Security S2).
 *
 * The second, independent lock on the five FM /api/* endpoints, sitting behind
 * the nginx mTLS termination. Before this, each endpoint hung on a single
 * per-location nginx `if ($ssl_client_verify != "SUCCESS")` — one regression,
 * misconfig, or location-shadow silently exposed the whole donor DB
 * (/api/backup/blob) or an admin credential reset (/api/admin/recover). This
 * gate makes two things have to hold, not one.
 *
 * The credential is a per-install shared secret (config('fleet.gate.secret'),
 * from FLEET_GATE_SECRET) that Fleet Manager sends as the `X-Fleet-Gate-Key`
 * header. It is NOT derived from the client cert — a separate credential on a
 * separate channel — which is exactly what makes it independent of the TLS
 * layer: an nginx misconfiguration that lets an un-certed request reach PHP
 * still fails here, because the secret never passes through nginx's cert
 * machinery. This backstops mTLS; it does not replace it.
 *
 * Absent / empty secret = the gate is INERT (return $next unchanged), so the
 * endpoints behave exactly as at v2.6.0 and a v2.7.0 image is a no-op on every
 * running node until Fleet Manager provisions a secret — additive by
 * construction, same shape as SUSPENSION_STATE (absent = none). See
 * docs/fleet-manager-agent-contract.md § App-layer second gate.
 */
class VerifyFleetAgent
{
    public const HEADER = 'X-Fleet-Gate-Key';

    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('fleet.gate.secret');

        // Not provisioned → gate inert → mTLS is the only lock (v2.6.0 behaviour).
        if ($secret === '') {
            return $next($request);
        }

        $provided = $request->header(self::HEADER);

        if (! is_string($provided) || ! hash_equals($secret, $provided)) {
            return response()->json([
                'error'   => 'fleet_gate_unauthorized',
                'message' => 'missing or invalid Fleet Manager gate credential',
            ], 401);
        }

        return $next($request);
    }
}
