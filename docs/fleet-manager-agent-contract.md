# Fleet Manager Agent Contract

**Contract Version:** `1.0.0`
**Status:** active
**Owner repo:** [npc-beta](https://github.com/abeuscher/npc-beta) (CRM)
**Consumer repo:** Fleet Manager (separate repo, to be created)
**Canonical URL:** `https://raw.githubusercontent.com/abeuscher/npc-beta/main/docs/fleet-manager-agent-contract.md`

---

## What this document is

The single source of truth for the HTTP contract between **NonprofitCRM installations** (which expose an authenticated health endpoint) and the **Fleet Manager** operational tool (which polls every install on a schedule).

Both the CRM and Fleet Manager implement against this contract. The CRM emits the surface; Fleet Manager consumes it. When the contract changes, both sides update before the next boundary-touching session in either repo. See `sessions/fleet-manager-planning-spec.md` ("Two-Repo Coordination Protocol" section) for the discipline.

---

## Endpoint

```
GET /api/health
```

- One route, GET only.
- No request body, no query parameters.
- Stateless — no session cookie, no CSRF token, not in the web middleware group.
- Rate-limited at the application layer: **60 requests per minute per IP** (Laravel `throttle:60,1`).

## Auth

```
Authorization: Bearer <token>
```

- Token is the per-install API key, set on the CRM droplet via the `FLEET_MANAGER_AGENT_KEY` environment variable.
- Comparison is **timing-safe** (`hash_equals`) — middleware fails closed on missing / empty / wrong tokens.
- **One key per install in v1.** Reusing the same key across multiple CRM installs is forbidden — Fleet Manager treats each install as a distinct credential boundary, and a leaked shared key would compromise the entire fleet at once.
- The CRM never echoes the submitted token or the expected token back to the caller.
- Auth failure responds `401` with body `{"error": "unauthorized"}`. Misconfiguration (no key set on the CRM at all) responds `500` with body `{"error": "misconfigured"}`.

## Response — `200 OK` (success, including subcheck failures)

```json
{
  "status": "green|yellow|red",
  "version": "abc1234",
  "timestamp": "2026-04-28T15:42:00+00:00",
  "contract_version": "1.0.0",
  "subchecks": {
    "app":            { "status": "green",  "value": "responding",  "threshold": null,     "message": null },
    "database":       { "status": "green",  "value": "reachable",   "threshold": null,     "message": null },
    "redis":          { "status": "green",  "value": "reachable",   "threshold": null,     "message": null },
    "disk":           { "status": "green",  "value": 42,            "threshold": [80, 95], "message": null },
    "last_backup_at": { "status": "green",  "value": null,          "threshold": null,     "message": "backup pipeline not yet implemented" },
    "version":        { "status": "green",  "value": "abc1234",     "threshold": null,     "message": null }
  }
}
```

### Top-level fields

| Field              | Type   | Description                                                                  |
|--------------------|--------|------------------------------------------------------------------------------|
| `status`           | string | Overall — worst of all subcheck statuses. One of `green`, `yellow`, `red`.   |
| `version`          | string | Application version (e.g., the seven-character git SHA, or `dev` locally).   |
| `timestamp`        | string | ISO 8601 server time at response composition.                                |
| `contract_version` | string | Semver. Tells Fleet Manager which contract version this response speaks.     |
| `subchecks`        | object | Object keyed by subcheck name. Stable v1 keys listed below.                  |

### Subcheck shape

Every subcheck — present and future — has the same four keys:

| Field       | Type                | Description                                                                          |
|-------------|---------------------|--------------------------------------------------------------------------------------|
| `status`    | string              | `green`, `yellow`, or `red`.                                                         |
| `value`     | mixed (`null` ok)   | Subcheck-specific payload (string, integer percent, ISO timestamp, or `null`).       |
| `threshold` | mixed (`null` ok)   | The bound that drove the status, or `null` if the subcheck has no numeric threshold. |
| `message`   | string \| null      | Optional human-readable note. **Never** carries internal paths or stack traces.      |

### Subchecks (v1 — six stable keys)

| Key              | `value` shape                | `threshold`         | Notes                                                                                                                       |
|------------------|------------------------------|---------------------|-----------------------------------------------------------------------------------------------------------------------------|
| `app`            | `"responding"`               | `null`              | The fact that the controller produced this entry IS the check.                                                              |
| `database`       | `"reachable"` / `"unreachable"` | `null`           | `red` on `PDOException`. `message` carries the exception class name only.                                                   |
| `redis`          | `"reachable"` / `"unreachable"` | `null`           | `red` on any `Redis::ping()` exception. `message` carries the exception class name only.                                    |
| `disk`           | integer (percent used)       | `[80, 95]`          | `yellow` at ≥80 %, `red` at ≥95 %. Measured against `/`. Returns `red` with `value: null` if usage cannot be read.           |
| `last_backup_at` | `null` (v1) / ISO timestamp  | `null`              | Always `green` in v1 with `value: null` and a "not yet implemented" message. Threshold-driven `yellow`/`red` lands in Phase 2. |
| `version`        | string                       | `null`              | Always `green`. Mirrors top-level `version` field. A `dev` build is not a failure state.                                    |

### Overall status — worst-of rule

```
red    if any subcheck is red
yellow if any subcheck is yellow and none are red
green  if all subchecks are green
```

### Endpoint behaviour on subcheck failure

The endpoint returns **200** with the failing subcheck marked `red` in the body. The endpoint itself does not return `503` for subcheck failures — Fleet Manager always gets structured data to act on. `503` is reserved (see below).

## Response — `401 Unauthorized`

```json
{ "error": "unauthorized" }
```

Returned when the bearer token is missing, empty, or does not match. The body never echoes the submitted or expected key.

## Response — `429 Too Many Requests`

Standard Laravel rate-limiter response. Fleet Manager should back off with exponential jitter and retry. The 60-rpm cap defends against an FM-side bug polling every second; well-behaved consumers will never see this.

## Response — `500 Internal Server Error`

```json
{ "error": "misconfigured" }
```

Returned by the auth middleware when `FLEET_MANAGER_AGENT_KEY` is unset on the CRM. Fleet Manager should treat repeated `500`s as a configuration alert, not a transient failure.

## Response — `503 Service Unavailable`

**Reserved.** Not emitted by v1.0.0. Future versions may use `503` to signal an explicit endpoint-disabled state (e.g., maintenance mode).

---

## Version negotiation

Every response carries `contract_version`. Fleet Manager reads it on every poll and:

- Compares against the canonical version it fetches via `WebFetch` against `https://raw.githubusercontent.com/abeuscher/npc-beta/main/docs/fleet-manager-agent-contract.md` at session-bootstrap time.
- Logs a drift warning if the install's `contract_version` does not match the fleet baseline.
- Stays forward-compatible within a major: a Fleet Manager that understands `1.0` can poll a CRM speaking `1.X` (X > 0) as long as the v1.0 fields it relies on are still present. New fields are additive within a major.
- Treats a major bump (`2.0`) as a breaking change — the Fleet Manager build is updated to the new shape before that contract version reaches any install.

The CRM-side reads its emitted `contract_version` from the `HealthController::CONTRACT_VERSION` constant. A version bump is a code change in the CRM repo plus a CHANGELOG entry in this doc plus the Cross-Repo block update in both repos' `sessions/session-outlines.md`.

---

## Security posture

- Bearer-token comparison is timing-safe (`hash_equals`).
- Auth fails closed on every misconfiguration scenario.
- The route is in the API middleware group — stateless, no session, no CSRF.
- No DB rows, user records, request payloads, exception messages, or stack traces appear in any response — only the documented subcheck shapes and exception **class names** in `message` fields where useful.
- The endpoint is rate-limited at the application layer; it is also expected to sit behind nginx, which can apply additional rate-limiting if needed.
- The agent API key has **no read access to anything** in the CRM beyond this endpoint. It is not a user credential, not a session bootstrap, not a webhook secret.

---

## CHANGELOG

### `1.0.0` — 2026-04-28 (session 238)

Initial contract. Endpoint, auth, response schema, version negotiation, error envelope all defined. CRM-side `/api/health` ships in this session; Fleet Manager repo can begin building its HTTP client against this contract from the canonical URL.

Subchecks: `app`, `database`, `redis`, `disk`, `last_backup_at`, `version`.

Known v1 caveat: `last_backup_at` returns `null` with a "not yet implemented" message. Fleet Manager treats null as "unknown — don't alarm." Threshold-driven status for this subcheck lands when the CRM-side backup pipeline does (Fleet Manager Agent — Phase 2).

### `0.0.0` — 2026-04-28 (session 237)

Stub created. Surface not yet authored. The CRM-side Fleet Manager Agent — Phase 1 session creates v1.0.0 with the actual HTTP contract.
