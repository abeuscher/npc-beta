<?php

return [

    'agent' => [
        'app_version' => is_readable('/var/cache/app/VERSION')
            ? trim((string) file_get_contents('/var/cache/app/VERSION'))
            : 'dev',
    ],

    /*
    |--------------------------------------------------------------------------
    | Suspension state (client billing, contract v2.6.0)
    |--------------------------------------------------------------------------
    |
    | The node's currently-enforced suspension state, pushed by Fleet Manager as
    | a single env key over its existing config-push channel (the same machinery
    | that sets one `.env` key over SSH and recreates containers). One of
    | `none` / `admin_locked` / `site_off`.
    |
    | Absent = `none`, so the bump is additive by construction — every existing
    | install behaves identically before and after this ships. An unrecognized
    | value fails safe to `none` (and logs a warning) in SuspensionState::resolve()
    | — a typo in a pushed key must never brick a paying client's admin.
    |
    */
    'suspension' => [
        'state' => env('SUSPENSION_STATE', 'none'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fleet agent app-layer gate (contract v2.7.0, Security S2)
    |--------------------------------------------------------------------------
    |
    | An independent second lock behind the nginx mTLS termination on the five
    | FM /api/* endpoints. Fleet Manager sends this per-install shared secret as
    | the `X-Fleet-Gate-Key` header on every request; VerifyFleetAgent compares
    | it constant-time. It is NOT derived from the client cert — a separate
    | credential on a separate channel — so an nginx regression / misconfig /
    | location-shadow that lets an un-certed request through still fails here.
    |
    | Absent / empty = the gate is INERT: requests reach the controllers under
    | mTLS alone, exactly as at v2.6.0. So a v2.7.0 image is a no-op on every
    | running node until Fleet Manager pushes a secret (same additive shape as
    | SUSPENSION_STATE absent = none). Provisioned per-install over FM's existing
    | single-key `.env` config-push channel; one secret per install, never
    | shared. See docs/fleet-manager-agent-contract.md § App-layer second gate.
    |
    */
    'gate' => [
        'secret' => env('FLEET_GATE_SECRET'),
    ],

];
