# Fleet Manager Agent Contract

**Contract Version:** `2.0.0`
**Status:** active
**Owner repo:** [npc-beta](https://github.com/abeuscher/npc-beta) (CRM)
**Consumer repo:** Fleet Manager (separate repo, to be created)
**Canonical URL:** `https://raw.githubusercontent.com/abeuscher/npc-beta/main/docs/fleet-manager-agent-contract.md`

---

## What this document is

The single source of truth for the HTTP contract between **NonprofitCRM installations** (which expose a mutually-authenticated health endpoint) and the **Fleet Manager** operational tool (which polls every install on a schedule).

Both the CRM and Fleet Manager implement against this contract. The CRM emits the surface; Fleet Manager consumes it. When the contract changes, both sides update before the next boundary-touching session in either repo. See `sessions/fleet-manager-planning-spec.md` ("Two-Repo Coordination Protocol" section) for the discipline.

---

## Endpoint

```
GET /api/health
```

- One route, GET only.
- No request body, no query parameters.
- Stateless — no session cookie, no CSRF token, not in the web middleware group.
- Rate-limited at the application layer: **60 requests per minute per IP** (Laravel `throttle:60,1`). Independent of auth — the throttle protects against polling-loop bugs whether or not the cert handshake succeeded.

## Auth

```
mTLS — terminated by nginx at the TLS layer.
```

- Authentication happens during the TLS handshake. Nginx is configured with `ssl_verify_client optional` at the server level and a per-location `if ($ssl_client_verify != "SUCCESS") { return 403; }` strict-gate on `/api/health`. Public routes (admin, portal, marketing pages) stay reachable without a client cert; only `/api/health` is gated.
- Each CRM install trusts **exactly one** specific FM-side cert, configured at nginx via `ssl_client_certificate` pointed at `/etc/nginx/certs/fm-client.crt`. No CA, no PKI tooling, no chain. Direct trust against the per-install cert.
- The FM operator pastes the trusted cert into the CRM droplet at `/opt/nonprofitcrm/nginx-certs/fm-client.crt` (bind-mounted into the nginx container). Restart nginx to apply.
- The application sees no auth signal. If the request reached the `HealthController`, nginx already validated the client-cert presentation. PHP does not authenticate, does not read the cert, does not derive identity from it. The discipline is "trust the connection."
- Authentication failures are emitted by nginx, not the application. With `ssl_verify_client optional` at the server level, the TLS handshake completes either way; nginx then returns an HTTP error before the request ever reaches PHP. The specific code depends on the failure mode:
  - **No client cert presented:** the per-location `if ($ssl_client_verify != "SUCCESS") { return 403; }` gate fires → `403 Forbidden`.
  - **A client cert is presented but does not match the trusted cert:** nginx's SSL error path fires → `400 Bad Request` with body "The SSL certificate error".
  In both cases the body is plain HTML emitted by nginx, not a JSON envelope. There is no application-layer JSON `401` envelope in v2.0.0; consumers should not expect one for auth failures.
- **One cert per install in v2.x.** Reusing the same cert across multiple CRM installs is forbidden — Fleet Manager treats each install as a distinct credential boundary, and a leaked shared keypair would compromise the entire fleet at once. Multi-FM-instance support (one CRM trusting multiple FM-side certs) would land as an additive v2.x bump.

## Response — `200 OK` (success, including subcheck failures)

```json
{
  "status": "green|yellow|red",
  "version": "abc1234",
  "timestamp": "2026-04-30T15:42:00+00:00",
  "contract_version": "2.0.0",
  "subchecks": {
    "app":            { "status": "green", "value": "responding",                "threshold": null,     "message": null },
    "database":       { "status": "green", "value": "reachable",                 "threshold": null,     "message": null },
    "redis":          { "status": "green", "value": "reachable",                 "threshold": null,     "message": null },
    "disk":           { "status": "green", "value": 42,                          "threshold": [80, 95], "message": null },
    "last_backup_at": { "status": "green", "value": "2026-04-29T01:30:00+00:00", "threshold": [24, 36], "message": null },
    "version":        { "status": "green", "value": "abc1234",                   "threshold": null,     "message": null }
  }
}
```

### Top-level fields

| Field              | Type   | Description                                                                                                          |
|--------------------|--------|----------------------------------------------------------------------------------------------------------------------|
| `status`           | string | Overall — derived from all subcheck statuses (see worst-of rule). Always one of `green`, `yellow`, `red`. **Top-level `status` is never `unknown`** — `unknown` is a subcheck-level value only. |
| `version`          | string | Application version (e.g., the seven-character git SHA, or `dev` locally).                                           |
| `timestamp`        | string | ISO 8601 server time at response composition.                                                                        |
| `contract_version` | string | Semver. Tells Fleet Manager which contract version this response speaks.                                             |
| `subchecks`        | object | Object keyed by subcheck name. Stable v1 keys listed below.                                                          |

### Subcheck shape

Every subcheck — present and future — has the same four keys:

| Field       | Type                | Description                                                                                                                                                |
|-------------|---------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `status`    | string              | One of `green`, `yellow`, `red`, or `unknown`. `unknown` means the subcheck cannot determine its state yet (e.g., the underlying mechanism has not yet produced a successful result). Subcheck-level only — `unknown` never propagates to the top-level `status` field. |
| `value`     | mixed (`null` ok)   | Subcheck-specific payload (string, integer percent, ISO timestamp, or `null`).                                                                             |
| `threshold` | mixed (`null` ok)   | The bound that drove the status, or `null` if the subcheck has no numeric threshold.                                                                       |
| `message`   | string \| null      | Optional human-readable note. **Never** carries internal paths or stack traces.                                                                            |

### Subchecks (v2.0.0 — six stable keys, unchanged from v1.2.0)

| Key              | `value` shape                | `threshold`         | Notes                                                                                                                                                                                              |
|------------------|------------------------------|---------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `app`            | `"responding"`               | `null`              | The fact that the controller produced this entry IS the check.                                                                                                                                     |
| `database`       | `"reachable"` / `"unreachable"` | `null`           | `red` on `PDOException`. `message` carries the exception class name only.                                                                                                                          |
| `redis`          | `"reachable"` / `"unreachable"` | `null`           | `red` on any `Redis::ping()` exception. `message` carries the exception class name only.                                                                                                           |
| `disk`           | integer (percent used)       | `[80, 95]`          | `yellow` at ≥80 %, `red` at ≥95 %. Measured against `/`. Returns `red` with `value: null` if usage cannot be read.                                                                                  |
| `last_backup_at` | ISO 8601 timestamp (`null` when status is `unknown`) | `[24, 36]` | Threshold-driven against the most recent successful backup. `green` < 24h, `yellow` 24–36h, `red` > 36h. `unknown` when no successful backup exists yet (pipeline installed but no run completed, or the success-record file is missing/empty/unparseable). Fleet Manager treats `unknown` as yellow-tier (don't alarm, but surface). |
| `version`        | string                       | `null`              | Always `green`. Mirrors top-level `version` field. A `dev` build is not a failure state.                                                                                                            |

### Overall status — worst-of rule

```
red     if any subcheck is red
yellow  if any subcheck is yellow OR unknown, and none are red
green   if all subchecks are green
```

`unknown` at the subcheck level ranks equivalently to `yellow` for the purposes of computing the top-level `status`. Top-level `status` is therefore always one of `{green, yellow, red}`; `unknown` never propagates to the top level.

The `unknown` ≡ `yellow` ranking is a v1.1.0 lean. Operational experience may show that missing data should rank above yellow (more concerning) or below yellow (less concerning); the ranking may be revisited in a future bump.

### Endpoint behaviour on subcheck failure

The endpoint returns **200** with the failing subcheck marked `red` in the body. The endpoint itself does not return `503` for subcheck failures — Fleet Manager always gets structured data to act on. `503` is reserved (see below).

## Response — auth-failure paths (nginx-emitted, not application)

When the request fails the mTLS gate, nginx emits the response without invoking PHP. The body is plain HTML (whatever nginx renders for the error code), not a JSON envelope.

- **`403 Forbidden`** — no client cert presented. The TLS handshake completes (because `ssl_verify_client` is `optional` at the server level so public routes stay reachable); the per-location gate on `/api/health` then fires `if ($ssl_client_verify != "SUCCESS") { return 403; }`. Fleet Manager seeing repeated `403`s should suspect FM-side config (the HTTP client is not configured to present its cert).
- **`400 Bad Request`** — body "The SSL certificate error". A cert was presented but did not match the trusted cert at `ssl_client_certificate`. Fleet Manager seeing repeated `400`s should suspect cert misalignment (the FM-side cert no longer matches what the CRM trusts; the operator may need to re-paste).

## Response — `429 Too Many Requests`

Standard Laravel rate-limiter response. Fleet Manager should back off with exponential jitter and retry. The 60-rpm cap defends against an FM-side bug polling every second; well-behaved consumers will never see this.

## Response — `503 Service Unavailable`

**Reserved.** Not emitted by v2.0.0. Future versions may use `503` to signal an explicit endpoint-disabled state (e.g., maintenance mode).

---

## Version negotiation

Every response carries `contract_version`. Fleet Manager reads it on every poll and:

- Compares against the canonical version it fetches via `WebFetch` against `https://raw.githubusercontent.com/abeuscher/npc-beta/main/docs/fleet-manager-agent-contract.md` at session-bootstrap time.
- Logs a drift warning if the install's `contract_version` does not match the fleet baseline.
- Stays forward-compatible within a major: a Fleet Manager that understands `2.0` can poll a CRM speaking `2.X` (X > 0) as long as the v2.0 fields it relies on are still present. New fields are additive within a major.
- Treats a major bump (`3.0`) as a breaking change — the Fleet Manager build is updated to the new shape before that contract version reaches any install.

The CRM-side reads its emitted `contract_version` from the `HealthController::CONTRACT_VERSION` constant. A version bump is a code change in the CRM repo plus a CHANGELOG entry in this doc plus the Cross-Repo block update in both repos' `sessions/session-outlines.md`.

---

## Security posture

- Authentication is enforced at the TLS layer by nginx. The application has no auth code path for `/api/health` — request arrival IS the auth proof.
- Per-install cert trust: each CRM install trusts exactly one FM-side cert. Compromise of one install's cert does not cascade across the fleet.
- The route is in the API middleware group — stateless, no session, no CSRF. The throttle middleware applies independently of cert presentation.
- No DB rows, user records, request payloads, exception messages, or stack traces appear in any response — only the documented subcheck shapes and exception **class names** in `message` fields where useful.
- The endpoint is rate-limited at the application layer; nginx can apply additional rate-limiting if needed.
- The cert at `ssl_client_certificate` has **no read access to anything** in the CRM beyond this endpoint. It is not a user credential, not a session bootstrap, not a webhook secret. The application doesn't even read the cert — nginx alone validates it.

---

## CHANGELOG

### `2.0.0` — 2026-04-30 (session 248)

**Breaking change — major bump.** Authentication scheme swaps from bearer token at the application layer to mTLS at the TLS layer. Nginx terminates the handshake; the application has no auth code path for `/api/health` after this version. The `AuthenticateFleetManagerAgent` middleware is retired; the per-install `FLEET_MANAGER_AGENT_KEY` env var no longer exists; auth failures no longer produce a `401` JSON envelope (handshake failures surface at the TLS layer instead, with no HTTP response).

The bearer path is removed in the same cutover — there is no backward-compat shim. Pre-Beta-1 / no-live-clients status makes a non-additive bump justifiable.

**Response shape — top-level fields, subchecks, status enum, worst-of derivation — is unchanged from v1.2.0.** Consumers that validate response shape only need refresh their cached spec; consumers performing application-layer auth (sending bearer tokens) need to reconfigure to present a client cert via TLS.

**Scope note:** the FM-raised question of `last_backup_at` threshold-derivation ownership (CRM-canonical vs FM-canonical for the derived `status`) was considered for v2.0.0 and **explicitly deferred** to a future v2.x bump (additive). v2.0.0 stays narrowly the auth-handshake change to keep the retrospective clean. FM continues to re-derive locally against its own threshold; the CRM continues to emit a derived `status` server-side. The two thresholds match by configuration today.

### `1.2.0` — 2026-04-28 (session 242)

Adds the threshold-driven `last_backup_at` semantics. `value` is now an ISO 8601 timestamp when a successful backup exists; `threshold` is `[24, 36]` (hours) — `green` < 24h, `yellow` 24–36h, `red` > 36h. `unknown` is now reserved for "pipeline exists but no successful run yet" (was "no pipeline at all" in v1.1.0); FM-side semantics are unchanged because `unknown` continues to rank as yellow-tier in the worst-of derivation.

Forward-compatible with v1.1.0 consumers: the subcheck shape (`status`, `value`, `threshold`, `message`) is unchanged; only the values within those keys change. Consumers reading the v1.1.0 `last_backup_at.value` as null can still treat null as "unknown — don't alarm" (the semantic still holds). Consumers reading numeric thresholds can now act on `last_backup_at.threshold` as a real `[low, high]` pair.

### `1.1.0` — 2026-04-28 (session 240)

Adds `unknown` as a valid subcheck-level status value. `last_backup_at` emits `unknown` (was `green`) when no backup pipeline exists — null `value` no longer reads as "things are fine," it reads as "we don't know yet." `unknown` ranks as `yellow` in the overall-status worst-of derivation; the top-level `status` enum is unchanged (`{green, yellow, red}`). Only `last_backup_at` emits `unknown` in v1.1.0; other subchecks (`app`, `database`, `redis`, `disk`, `version`) continue to emit `{green, yellow, red}` exclusively.

Forward-compatible with v1.0.0 consumers: top-level shape and values are unchanged. Consumers reading subcheck-level `status` should accept `unknown` as a fourth valid value.

### `1.0.0` — 2026-04-28 (session 238)

Initial contract. Endpoint, auth, response schema, version negotiation, error envelope all defined. CRM-side `/api/health` ships in this session; Fleet Manager repo can begin building its HTTP client against this contract from the canonical URL.

Subchecks: `app`, `database`, `redis`, `disk`, `last_backup_at`, `version`.

Known v1 caveat: `last_backup_at` returns `null` with a "not yet implemented" message. Fleet Manager treats null as "unknown — don't alarm." Threshold-driven status for this subcheck lands when the CRM-side backup pipeline does (Fleet Manager Agent — Phase 2).

### `0.0.0` — 2026-04-28 (session 237)

Stub created. Surface not yet authored. The CRM-side Fleet Manager Agent — Phase 1 session creates v1.0.0 with the actual HTTP contract.
