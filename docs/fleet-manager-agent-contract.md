# Fleet Manager Agent Contract

**Contract Version:** `2.6.0`
**Status:** active
**Owner repo:** [npc-beta](https://github.com/abeuscher/npc-beta) (CRM)
**Consumer repo:** Fleet Manager (separate repo, to be created)
**Canonical URL:** `https://raw.githubusercontent.com/abeuscher/npc-beta/main/docs/fleet-manager-agent-contract.md`

---

## What this document is

The single source of truth for the HTTP contract between **NonprofitCRM installations** (which expose a mutually-authenticated health endpoint) and the **Fleet Manager** operational tool (which polls every install on a schedule).

Both the CRM and Fleet Manager implement against this contract. The CRM emits the surface; Fleet Manager consumes it. When the contract changes, both sides update before the next boundary-touching session in either repo. See `sessions/fleet-manager-planning-spec.md` ("Two-Repo Coordination Protocol" section) for the discipline.

---

## Endpoints

```
GET  /api/health
GET  /api/logs
POST /api/backup/trigger
GET  /api/backup/blob
POST /api/admin/recover
```

- Five routes. `/api/health`, `/api/logs`, and `/api/backup/blob` are GET-only; `/api/backup/trigger` and `/api/admin/recover` are POST-only.
- Stateless — no session cookie, no CSRF token, not in the web middleware group.
- Each route carries its own rate-limit bucket. `/api/health`, `/api/logs`, and `/api/backup/blob` are rate-limited at **60 requests per minute per IP** (Laravel `throttle:60,1`); `/api/backup/trigger` and `/api/admin/recover` are rate-limited at **6 requests per minute per IP** (`throttle:6,1`) — backups are expensive to run, and admin recovery is a sensitive auth-mutating operation that should never see legitimate burst traffic. The throttles defend against polling-loop bugs and accidental retrigger storms on the FM side. Auth-failure storms cannot reach the throttle — nginx returns 403 / 400 before the request hits PHP, so the rate limiter only ever sees requests that already passed the mTLS gate.

`/api/health` is documented under `/api/health — Response`. `/api/logs` is documented under `/api/logs — Request` and `/api/logs — Response`. `/api/backup/trigger` is documented under `/api/backup/trigger — Request` and `/api/backup/trigger — Response`. `/api/backup/blob` is documented under `/api/backup/blob — Request` and `/api/backup/blob — Response`. `/api/admin/recover` is documented under `/api/admin/recover — Request` and `/api/admin/recover — Response`.

## Auth

```
mTLS — terminated by nginx at the TLS layer.
```

- Authentication happens during the TLS handshake. Nginx is configured with `ssl_verify_client optional` at the server level and per-location `if ($ssl_client_verify != "SUCCESS") { return 403; }` strict-gates on **all five** FM agent endpoints (`/api/health`, `/api/logs`, `/api/backup/trigger`, `/api/backup/blob`, `/api/admin/recover`). Public routes (admin, portal, marketing pages) stay reachable without a client cert; only the FM agent endpoints are gated.
- Each CRM install trusts **exactly one** specific FM-side cert, configured at nginx via `ssl_client_certificate` pointed at `/etc/nginx/certs/fm-client.crt`. No CA, no PKI tooling, no chain. Direct trust against the per-install cert.
- The FM operator pastes the trusted cert into the CRM droplet at `/opt/nonprofitcrm/nginx-certs/fm-client.crt` (bind-mounted into the nginx container). Restart nginx to apply.
- The application sees no auth signal. If the request reached the controller (any of the five), nginx already validated the client-cert presentation. PHP does not authenticate, does not read the cert, does not derive identity from it. The discipline is "trust the connection."
- Authentication failures are emitted by nginx, not the application. With `ssl_verify_client optional` at the server level, the TLS handshake completes either way; nginx then returns an HTTP error before the request ever reaches PHP. The specific code depends on the failure mode:
  - **No client cert presented:** the per-location `if ($ssl_client_verify != "SUCCESS") { return 403; }` gate fires → `403 Forbidden`.
  - **A client cert is presented but does not match the trusted cert:** nginx's SSL error path fires → `400 Bad Request` with body "The SSL certificate error".
  In both cases the body is plain HTML emitted by nginx, not a JSON envelope. There is no application-layer JSON `401` envelope in v2.0.0; consumers should not expect one for auth failures.
- **One cert per install in v2.x.** Reusing the same cert across multiple CRM installs is forbidden — Fleet Manager treats each install as a distinct credential boundary, and a leaked shared keypair would compromise the entire fleet at once. Multi-FM-instance support (one CRM trusting multiple FM-side certs) would land as an additive v2.x bump.

## `/api/health` — Response — `200 OK` (success, including subcheck failures)

```json
{
  "status": "green|yellow|red",
  "version": "0.291.1",
  "timestamp": "2026-04-30T15:42:00+00:00",
  "contract_version": "2.6.0",
  "subchecks": {
    "app":            { "status": "green", "value": "responding",                "threshold": null,     "message": null },
    "database":       { "status": "green", "value": "reachable",                 "threshold": null,     "message": null },
    "redis":          { "status": "green", "value": "reachable",                 "threshold": null,     "message": null },
    "disk":           { "status": "green", "value": 42,                          "threshold": [80, 95], "message": null },
    "last_backup_at": { "status": "green", "value": "2026-04-29T01:30:00+00:00", "threshold": [24, 36], "message": null },
    "version":        { "status": "green", "value": "0.291.1",                   "threshold": null,     "message": null },
    "data_hygiene":   { "status": "green", "value": { "orphan_event_pages": 0, "scrub_records": 0, "orphan_media_dirs": 0, "dead_owner_media": 0 }, "threshold": 100, "message": null },
    "suspension":     { "status": "green", "value": { "state": "none", "billing_state_as_of": null }, "threshold": null, "message": null }
  }
}
```

### Top-level fields

| Field              | Type   | Description                                                                                                          |
|--------------------|--------|----------------------------------------------------------------------------------------------------------------------|
| `status`           | string | Overall — derived from all subcheck statuses (see worst-of rule). Always one of `green`, `yellow`, `red`. **Top-level `status` is never `unknown`** — `unknown` is a subcheck-level value only. |
| `version`          | string | Build-stamped application version. Pre-1.0 pseudo-version of the form `0.<session>.<iteration>` (e.g. `0.291.1`), set at image build time and immutable per published image; `dev` for local/unstamped builds. Semver-ordered so FM can compare before→after across upgrades. Not derived at runtime from git. |
| `timestamp`        | string | ISO 8601 server time at response composition.                                                                        |
| `contract_version` | string | Semver. Tells Fleet Manager which contract version this response speaks.                                             |
| `subchecks`        | object | Object keyed by subcheck name. Stable v1 keys listed below.                                                          |

### Subcheck shape

Every subcheck — present and future — has the same four keys:

| Field       | Type                | Description                                                                                                                                                |
|-------------|---------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `status`    | string              | One of `green`, `yellow`, `red`, or `unknown`. `unknown` means the subcheck cannot determine its state yet (e.g., the underlying mechanism has not yet produced a successful result). Subcheck-level only — `unknown` never propagates to the top-level `status` field. |
| `value`     | mixed (`null` ok)   | Subcheck-specific payload (string, integer percent, ISO timestamp, an object of named non-PII integer counts, or `null`).                                  |
| `threshold` | mixed (`null` ok)   | The bound that drove the status — a `[low, high]` pair, a single integer soft bound, or `null` if the subcheck has no numeric threshold.                    |
| `message`   | string \| null      | Optional human-readable note. **Never** carries internal paths or stack traces.                                                                            |

### Subchecks (v2.6.0 — eight keys; `suspension` added in v2.6.0, `data_hygiene` in v2.4.0, the prior six unchanged from v1.2.0)

| Key              | `value` shape                | `threshold`         | Notes                                                                                                                                                                                              |
|------------------|------------------------------|---------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `app`            | `"responding"`               | `null`              | The fact that the controller produced this entry IS the check.                                                                                                                                     |
| `database`       | `"reachable"` / `"unreachable"` | `null`           | `red` on `PDOException`. `message` carries the exception class name only.                                                                                                                          |
| `redis`          | `"reachable"` / `"unreachable"` | `null`           | `red` on any `Redis::ping()` exception. `message` carries the exception class name only.                                                                                                           |
| `disk`           | integer (percent used)       | `[80, 95]`          | `yellow` at ≥80 %, `red` at ≥95 %. Measured against `/`. Returns `red` with `value: null` if usage cannot be read.                                                                                  |
| `last_backup_at` | ISO 8601 timestamp (`null` when status is `unknown`) | `[24, 36]` | Threshold-driven against the most recent successful backup. `green` < 24h, `yellow` 24–36h, `red` > 36h. `unknown` when no successful backup exists yet (pipeline installed but no run completed, or the success-record file is missing/empty/unparseable). Fleet Manager treats `unknown` as yellow-tier (don't alarm, but surface). |
| `version`        | string                       | `null`              | Always `green`. Mirrors top-level `version` field. A `dev` build is not a failure state.                                                                                                            |
| `data_hygiene`   | object of four named integer counts | integer (soft yellow) | **Count-only, non-PII, informational** (added v2.4.0). `value` is `{orphan_event_pages, scrub_records, orphan_media_dirs, dead_owner_media}` — aggregate counts of accumulated derived/cruft data on the node, never raw rows. `green` while the total is under the soft `threshold`, `yellow` at/above it; **never `red`**. **Excluded from the worst-of overall status** — see the rule below. See § `data_hygiene` subcheck for the full semantics + privacy boundary. |
| `suspension`     | object `{state, billing_state_as_of}` | `null`              | **Informational** (added v2.6.0). `state` is the node's currently-*enforced* suspension state (`none` / `admin_locked` / `site_off`, from the pushed `SUSPENSION_STATE` flag); `billing_state_as_of` is the pushed billing-state document's `as_of` (`null` when no document). FM's read-back that a suspension push took effect. `green` when `state` is `none`, `yellow` otherwise; **never `red`**. **Excluded from the worst-of overall status** — a deliberately-suspended node is not an unhealthy node. State string + timestamp only — no email, no amounts, nothing personal. See § `suspension` subcheck. |

### `data_hygiene` subcheck (v2.4.0) — count-only, informational

Surfaces the node-local **Fleet Data Hygiene** audit (CRM-side `sessions/tracks/fleet-data-hygiene.md`) as an aggregate signal so Fleet Manager sees at a glance what derived/cruft data has piled up on each node. It is the FM-visible half of that track; the cleanup itself stays node-local (artisan commands).

- **`value` — four named integer counts, never raw rows.**

  ```json
  "value": {
    "orphan_event_pages": 12,
    "scrub_records": 0,
    "orphan_media_dirs": 3,
    "dead_owner_media": 0
  }
  ```

  - `orphan_event_pages` — `type=event` landing pages that no Event references.
  - `scrub_records` — rows still tagged `source=scrub_data` (synthetic test data left on a live box).
  - `orphan_media_dirs` — content-addressed media directories referenced by no live media row.
  - `dead_owner_media` — media rows whose owning model has been hard-deleted.

- **Privacy boundary (governing constraint).** Only these aggregate counts ever cross the wire — non-PII integers. **No record, slug, title, id, email, file path, or content is ever surfaced** through this subcheck or anywhere on the contract. The deep audit (the actual offending records) is **node-local + consent-gated** — run via `php artisan app:data-hygiene --deep` on a node the owner controls — and is **never** an FM-initiated capability. The contract is designed so FM *cannot* read node data, not merely policied not to. See § Data-access boundary under Security posture.

- **Status semantics — informational, never red.** `green` while the **total** across the four categories is under `threshold`; `yellow` at/above it. `threshold` is a single integer soft bound (default 100, CRM-side adjustable). A cruft pile — however large — is not a node-health emergency, so the subcheck **never emits `red`**. `message` is `null` when green; when yellow it carries a short count-only summary (e.g. `"147 items of accumulated cruft (counts only)"`), never a record identifier.

- **Excluded from the worst-of overall status.** Unlike every other subcheck, `data_hygiene` does **not** participate in the top-level `status` derivation (see the worst-of rule below). Its own `yellow` surfaces a per-node attention chip for FM to display, but benign cruft never drags a node's overall health to `yellow`. FM consumers should render the `data_hygiene` line on its own merits and not fold its status into a node's headline health.

- **Freshness.** The CRM computes the counts behind a short server-side cache (≈10-minute TTL) because the audit walks the media filesystem and scans the media table, and `/api/health` is polled frequently. The reported counts may therefore lag live state by up to the TTL — operationally irrelevant for data that accumulates over hours/days. FM should treat the counts as a recent-but-not-instantaneous snapshot.

### `suspension` subcheck (v2.6.0) — enforced-state read-back, informational

Surfaces the node's **currently-enforced client-billing suspension state** so Fleet Manager gets read-back verification that a suspension push took effect (the same verify-after-acting grain as upgrades). It is the node half of the client-billing suspension mechanism (§ Client billing — suspension flag + billing-state document); enforcement and the operator UI are FM-side.

- **`value` — a state string + a timestamp, nothing more.**

  ```json
  "value": {
    "state": "admin_locked",
    "billing_state_as_of": "2026-07-08T09:30:00+00:00"
  }
  ```

  - `state` — the node's currently-*enforced* suspension state, read from the pushed `SUSPENSION_STATE` env flag (`none` / `admin_locked` / `site_off`). This is the *enforced* value: an unrecognized flag value fails safe to `none` node-side, and the subcheck reports that same enforced reality (so a typo shows as `none` here, matching what the node actually does). Absent flag = `none`.
  - `billing_state_as_of` — the pushed billing-state document's `as_of` timestamp (ISO 8601), or `null` when no document has been pushed or it is unusable. Lets FM confirm the display document and the enforcement flag are both in place.

- **No PII on the wire.** Only the state string and the document timestamp cross the boundary — never the reason code, the billing email, plan names, or amounts (all of which live in the pushed document node-side). Within the contract's counts-only / no-PII wire discipline, same as `data_hygiene`.

- **Status semantics — informational, never red.** `green` when `state` is `none`; `yellow` for any active suspension (`admin_locked` / `site_off`) — an FM-side attention chip. A deliberately-suspended node is a business state, not a health emergency, so the subcheck **never emits `red`**. `threshold` is `null` (no numeric bound). `message` is `null` when green; a terse `"node suspension state: <state>"` when yellow — never a reason code, email, or amount.

- **Excluded from the worst-of overall status.** Like `data_hygiene`, `suspension` does **not** participate in the top-level `status` derivation (see the worst-of rule below). Its `yellow` surfaces a per-node attention chip; it never drags a suspended node's headline health to `yellow`.

### Overall status — worst-of rule

```
red     if any subcheck other than data_hygiene / suspension is red
yellow  if any subcheck other than data_hygiene / suspension is yellow OR unknown, and none are red
green   if all subchecks other than data_hygiene / suspension are green
```

**`data_hygiene` (v2.4.0) and `suspension` (v2.6.0) are both excluded from this derivation** — both are informational and never affect the top-level `status` (see their subcheck sections above). Every other subcheck rolls in as before. `unknown` at the subcheck level ranks equivalently to `yellow` for the purposes of computing the top-level `status`. Top-level `status` is therefore always one of `{green, yellow, red}`; `unknown` never propagates to the top level.

The `unknown` ≡ `yellow` ranking is a v1.1.0 lean. Operational experience may show that missing data should rank above yellow (more concerning) or below yellow (less concerning); the ranking may be revisited in a future bump.

### Endpoint behaviour on subcheck failure

The endpoint returns **200** with the failing subcheck marked `red` in the body. The endpoint itself does not return `503` for subcheck failures — Fleet Manager always gets structured data to act on. `503` is reserved (see below).

## `/api/logs` — Request

```
GET /api/logs
GET /api/logs?lines=N
```

| Parameter | Type    | Default | Range  | Description                                                                                              |
|-----------|---------|---------|--------|----------------------------------------------------------------------------------------------------------|
| `lines`   | integer | `500`   | 1–10000 | Tail length — the number of newline-delimited rows to return from the end of the log file. Values above the cap are clipped silently to the cap; non-positive or non-integer values return `422`. |

No request body. No other query parameters. The endpoint reads from a single source — `storage/logs/laravel.log` — and walks backward from EOF.

### "Line" semantics

A "line" in the response is one `\n`-delimited row in the file, **not a logical log entry**. Laravel writes a stack trace across many `\n`-delimited rows; a single ERROR-level entry can occupy 30+ lines once a multi-frame trace lands. `?lines=500` during a heavy-error period might surface as few as 3 logical entries. Fleet Manager-side UX should display these as raw rows; logical-entry parsing is out of scope at v2.1.

### Source — single channel, multi-container unification

The CRM today runs `LOG_CHANNEL=stack` with `LOG_STACK=single`, so all application log output lands in one ever-growing `storage/logs/laravel.log`. The endpoint returns lines from that single file. There is no `?date=` parameter at v2.1 because the file is not date-partitioned. If a future session migrates the install to `LOG_STACK=daily`, an additive v2.x bump can introduce `?date=YYYY-MM-DD`.

The `app` and `worker` services share `storage/logs/` via the docker-compose volume layout — locally via the `.:/var/www/html` bind-mount, in production via the `storage_data:/var/www/html/storage` named volume mounted on both services. **`/api/logs` therefore returns a unified view of both PHP-FPM and queue-worker output.** Concurrent appends from both processes are line-atomic at the OS level; concurrent readers may rarely see a partial trailing line — the contract does not engineer around this. A future docker-compose change that splits the log volume between services would be a contract-affecting concern.

### Scope clarification — what `/api/logs` is NOT

The endpoint returns the Laravel application log only. It does **not** surface:

- Nginx access or error logs (`/var/log/nginx/`).
- Queue-worker stderr beyond what Laravel logs through its own channel.
- Supervisord output.
- OS journals (`journalctl`).
- Database slow-query or query logs.

Operators reading the endpoint's name should not expect a full operational picture — `/api/logs` is the Laravel surface only. Other log sources stay accessible via operator SSH or future additive endpoints if the need arises.

## `/api/logs` — Response — `200 OK`

```json
{
  "lines": [
    "[2026-04-30 15:42:00] production.INFO: Backup completed successfully",
    "[2026-04-30 15:42:01] production.INFO: Cleaned old backups"
  ],
  "lines_returned": 2,
  "lines_truncated": false,
  "source": "laravel.log"
}
```

| Field             | Type           | Description                                                                                                                                                  |
|-------------------|----------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `lines`           | array of string | Tail of the source file, in chronological order (oldest first within the returned slice; the last element is the most recent line in the file).            |
| `lines_returned`  | integer        | `count(lines)`. Convenience for FM-side display.                                                                                                             |
| `lines_truncated` | boolean        | `true` if either the line cap (`?lines=N`) or the byte cap fired before the start of the file was reached. `false` if the response covers the entire file. |
| `source`          | string         | Source filename (`laravel.log`). Reserved for future multi-file shapes; FM should not assume it is always this exact value.                                  |

### Caps

- **Line cap.** `?lines=N` is honoured up to a hard max of **10000**. Requests above the cap clip silently and `lines_truncated` reflects that older lines were dropped.
- **Byte cap.** Raw line content is capped at **~850 KB** before JSON encoding (leaves headroom for envelope + escaping under a 1 MB encoded body ceiling). When the byte cap fires before the line cap, older lines are dropped and `lines_truncated` is `true`. The byte cap is a hard ceiling; a single line larger than the cap may cause the response to omit it entirely.
- **Truncation rule.** When either cap fires, **older** lines are dropped — the response always carries the tail. There is no way to fetch lines older than the most recent N within a single request.

### Read implementation note

The controller seeks from EOF and reads backward in 8 KB chunks, accumulating lines until a cap fires or the start of the file is reached. A single line longer than 8 KB (e.g., a SQL exception with an embedded query, a logged request payload) is read intact across chunk boundaries — the implementation does not silently truncate trailing partial lines.

## `/api/logs` — Response — `404 Not Found`

```json
{
  "error": "log_not_found",
  "message": "log file does not exist"
}
```

Returned when `storage/logs/laravel.log` does not exist (e.g., a fresh install before any request has fired). FM should distinguish this from "endpoint broken." The CRM creates the log on first write — once the install has handled at least one request, this state should not recur.

## `/api/logs` — Response — `422 Unprocessable Entity`

```json
{
  "error": "invalid_lines",
  "message": "lines must be a positive integer between 1 and 10000"
}
```

Returned for non-integer, zero, or negative `lines` values. Values above the cap clip silently — they do not produce 422.

## `/api/logs` — Response — `500 Internal Server Error`

```json
{
  "error": "log_unreadable",
  "message": "RuntimeException"
}
```

Returned for unexpected file I/O failures (file disappeared between existence check and open, OS-level read errors, etc.). The `message` carries the exception class name only — never the exception message, never a path, never a stack frame. Matches `HealthController` exception-message discipline.

## `/api/backup/trigger` — Request

```
POST /api/backup/trigger
```

- POST-only. Non-POST requests do not invoke the controller — they fall through Laravel's route table and typically resolve to `404 Not Found` via the public-site page-slug catchall. FM consumers should not depend on a specific non-POST status code; the operative invariant is that the backup pipeline does not run for non-POST requests.
- No request body. The endpoint takes no parameters. Any body sent is ignored.
- mTLS-gated at nginx (same `fm-client.crt` as `/api/health` and `/api/logs`).
- Throttled at **6 requests per minute per source IP** (`throttle:6,1`). Tighter than the polling endpoints' 60-rpm because each request runs a full backup pipeline.
- **Synchronous, blocking.** The request runs `php artisan backup:run` inside the request lifecycle and does not return until the backup completes (or fails). Plan for FM-side HTTP timeouts of 10 minutes or more.

### Operator semantics

Triggering this endpoint is "back up now and wait for the result," distinct from `/api/health`'s passive read. Calling FM-side code should:

- Use a per-request HTTP timeout of at least 600 seconds.
- Rely on the response envelope's `status` field as the authoritative outcome — *not* on HTTP status alone (the endpoint always returns `200`; failure is signalled in the body, mirroring `/api/health`'s pattern).
- Cross-check `last_backup_at` against the value `/api/health` reports immediately after the trigger; the integrity guard described under § Response semantics ensures the two sources agree on success.

### Timeout characteristics

- nginx applies a per-location `fastcgi_read_timeout 600;` override on `/api/backup/trigger` (other endpoints continue to use the server-wide 300s).
- The controller calls `set_time_limit(600)` defensively at the top of the action so PHP's `max_execution_time` does not cap a long-running backup short.
- PHP-FPM's `request_terminate_timeout` is unset (defaults to 0 — no PHP-FPM-imposed ceiling) on the upstream `php:8.4-fpm` image. The 10-minute ceiling is enforced by nginx + the in-PHP `set_time_limit(600)` only.
- A backup that runs longer than 600 seconds will be cut off at the nginx timeout; the FM-side will see a connection-level timeout, not the response envelope. Operators with backup durations approaching the ceiling should investigate (large media, slow Spaces uploads) — the v2.2.0 ceiling is a deliberate scope choice for pre-Beta-1 droplets.

## `/api/backup/trigger` — Response — `200 OK` (success)

```json
{
  "contract_version": "2.3.0",
  "status": "success",
  "last_backup_at": "2026-05-05T14:23:08+00:00",
  "duration_ms": 18742,
  "message": null
}
```

### Fields

| Field              | Type            | Description                                                                                                                                                                  |
|--------------------|-----------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `contract_version` | string          | Semver. Same value `/api/health` reports. Lets FM-side trigger paths verify version alignment without an extra `/api/health` round-trip.                                     |
| `status`           | string          | `"success"` when the backup completed and the success-record file moved forward; `"failed"` otherwise. Top-level enum is `{success, failed}`.                                |
| `last_backup_at`   | string \| null  | ISO 8601 timestamp of the just-completed backup (sourced from `storage/app/private/fleet/last-backup-at`, written by the `RecordBackupSuccess` listener).                    |
| `duration_ms`      | integer         | Wall-clock time the controller spent running `backup:run`, in milliseconds. Includes the time spent in `Artisan::call`, success-record reads, and envelope composition.       |
| `message`          | string \| null  | `null` on success. On failure, a sanitised single-line operator-readable error (see § Response — failed below).                                                              |

## `/api/backup/trigger` — Response — `200 OK` (failed)

```json
{
  "contract_version": "2.3.0",
  "status": "failed",
  "last_backup_at": "2026-05-04T14:00:00+00:00",
  "duration_ms": 412,
  "message": "Backup destination disk \"local\" is not writable | app/Services/Backup/Pipeline.php:74"
}
```

### Failure modes

The `failed` envelope is emitted under three distinct conditions:

1. **`backup:run` exits non-zero.** Source for `message`: `Artisan::output()` (potentially multi-line). Sanitised per the pipeline below.
2. **`Artisan::call` throws an exception.** Source for `message`: `\Throwable::getMessage()`. Same sanitisation pipeline.
3. **Integrity guard — success-record mtime cross-check fails.** `Artisan::call` returns 0 cleanly, but the success-record file's contents are missing OR older than the request's start time. `message`: `"backup:run exited cleanly but success record was not updated"`. This protects FM-side from showing a "success" panel when the listener silently failed (e.g., misconfigured event subscription, filesystem write failure inside the listener). FM consumers should treat the `status` field as authoritative even when the upstream artisan call exited cleanly.

### `last_backup_at` semantics on failure

- On non-zero exit OR exception: `last_backup_at` reports the **last *known* successful backup** — the value of the success-record file as read at the start of the request (or `null` if no successful backup has ever completed). FM-side displays continue to surface the last-known-good value rather than going blank when a trigger fails.
- On the integrity-guard branch (artisan exits 0 but cross-check fails): `last_backup_at` reports the (stale) value present in the success-record file at the moment of the post-call read — distinguishable in operator-facing UX as "the backup *says* it ran but our integrity guard caught a discrepancy."
- HTTP status remains `200` on all failure paths (matches `/api/health`'s pattern of returning 200 with degraded-status envelopes; nginx 5xx is reserved for transport-level failures).

### Error-message sanitisation pipeline

Both error sources (`Artisan::output()` on non-zero exit; `\Throwable::getMessage()` on caught exception) flow through the same pipeline before reaching the response body:

1. **Strip absolute application-root prefix** (e.g., `/var/www/html/`) so paths read app-root-relative (`app/Services/Foo.php` rather than `/var/www/html/app/Services/Foo.php`). Open-source application root means relative paths are public information; the absolute prefix strip is cosmetic, not security-critical.
2. **Collapse newlines** to ` | ` so the message is single-line for envelope hygiene.
3. **Cap at 500 characters**, truncating with a trailing `…` if longer.
4. **No stack traces.** Exceptions: take only the topmost `getMessage()`. Artisan output: as-emitted (the command's own logging is what reaches the operator).

The pipeline is intentionally lightweight — the goal is operator readability, not redaction. Application-root-relative paths and exception messages are public information for an open-source CRM.

## `/api/backup/blob` — Request

```
GET /api/backup/blob
```

- GET-only. Non-GET requests do not invoke the controller — they fall through Laravel's route table and typically resolve to `404 Not Found` via the public-site page-slug catchall (same path-shape behaviour as `/api/backup/trigger` against non-POST). FM consumers should not depend on a specific non-GET status code; the operative invariant is that the blob lookup does not run for non-GET requests.
- No request body. No query parameters at v2.3.0 (`?disk=`, `?date=`, blob enumeration are out of scope; see § Out of scope at the end of this section).
- mTLS-gated at nginx (same `fm-client.crt` as `/api/health` / `/api/logs` / `/api/backup/trigger`).
- Throttled at **60 requests per minute per source IP** (`throttle:60,1`). Matches `/api/logs` rather than `/api/backup/trigger`'s `throttle:6,1` because blob fetches are I/O-bound and cheap relative to the trigger endpoint's synchronous backup pipeline. The expected FM-side burst pattern is "one fetch after a successful trigger, with maybe one or two retries on transient failure" — 60/min is generous for that.
- nginx applies a per-location `fastcgi_read_timeout 600;` override on `/api/backup/blob` (mirroring `/api/backup/trigger`'s 600s ceiling). The default 300s server-wide timeout is the *between-packets* idle timeout, not total request time, but a multi-GB blob streaming over a slow FM-side network with PHP buffer backpressure can plausibly stall a single read window past 300s. 600s is cheap insurance with no downside on the success path.

### Disk-fallback rule — two layers, both load-bearing

The endpoint resolves the source disk through a deterministic two-layer rule. FM consumers do not pass a disk preference — the rule is server-authoritative — but the rule is documented here so FM consumers can trace which disk the blob came from when investigating an operator question.

- **Layer A — preference.** Read `BACKUP_DISKS` (already CSV-parsed in `config/backup.php`). If `local` appears anywhere in the list, it is moved to the front of the resolution order regardless of authored position. Remaining disks follow in their authored order. Empty / whitespace-only entries are dropped.
- **Layer B — fallthrough-on-empty.** Iterate the resolved order. For each disk, ask `BackupDestination::create($disk, config('backup.backup.name'))->backups()->newest()`. If non-null, stream from that disk. If null, continue to the next disk in the resolved order. The 404 envelope (see below) is returned only when all configured disks are exhausted.

The response does not expose which disk the blob came from — FM treats the blob as opaque-by-source. The disk choice is observable to the operator via the CRM's nginx access log and via direct disk inspection.

### Operator semantics

Calling `/api/backup/blob` is "give me the freshest blob the install knows about, from whichever disk the resolution rule lands on." Calling FM-side code should:

- Use a per-request HTTP timeout matching the 600s server ceiling.
- Treat HTTP status as authoritative for outcome classification (200 / 404 / 500 are distinct cases — see Response sections below).
- Verify the downloaded blob's `Content-Length` matches the actual byte count received (transport integrity); SHA verification is FM-side optional.

### Blob content — role-privilege-free dumps *(v2.5.0 revision, session 365 — no contract surface change, no version bump)*

The PostgreSQL dump inside the blob is produced with `pg_dump --no-privileges --no-owner` (set via the CRM's `config/database.php` pgsql connection `dump` options), so it carries **no `GRANT`/`REVOKE` privilege statements and no `ALTER … OWNER TO` ownership statements**. This makes the blob **portable across nodes**. A CRM install's dump would otherwise name that install's **per-node read-only DB role** (the role Fleet Manager mints per node at provision time, FM 037), and restoring such a dump on a *different* node aborts on the missing role — the failure that blocked cross-node baseline restore before this revision (surfaced at FM 042, 2026-06-05). Two consequences for FM-side restore orchestration:

- **Only blobs produced on/after the CRM image carrying this fix are portable.** A blob dumped by an older CRM image still carries role/ownership statements and will abort a cross-node restore. Re-record any baseline (including the demo baseline) from a fixed node before relying on it for a cross-node restore.
- **The restored node's own read-only role may need re-granting.** Because privileges are stripped from the dump, a restore carries no `GRANT … TO <read-only role>`. Whether the node's per-node read-only role still has `SELECT` on the restored tables afterward depends on the node's default-privilege configuration (the FM-037 provision machinery) and on which role runs the restore — verify node-side after a cross-node restore. If the role has lost `SELECT`, re-run the provision-time grant step. This is an FM-side runbook step, not a CRM-code concern; see the A2 runbook handoff for detail.

This is a dump-configuration change (`config/database.php`), not an HTTP-surface change; `CONTRACT_VERSION` stays `2.5.0`. FM consumers pick up this note on the next WebFetch refresh.

## `/api/backup/blob` — Response — `200 OK` (success)

The response body is the raw zip stream. Headers:

| Header                | Value                                                                                                                       |
|-----------------------|-----------------------------------------------------------------------------------------------------------------------------|
| `Content-Type`        | `application/zip`                                                                                                            |
| `Content-Disposition` | `attachment; filename="<spatie-blob-filename>"` (see filename pattern below)                                                  |
| `Content-Length`      | byte count of the zip on disk                                                                                                |
| `Cache-Control`       | `no-store` (defensive — prevents intermediate caching layers from retaining the blob; Laravel may append additional directives such as `private` per the API middleware group, which is harmless) |

### Filename pattern

The filename emitted in `Content-Disposition` is the spatie-default `Y-m-d-H-i-s.zip` — for example, `2026-05-08-12-30-00.zip`. There is **no `<backup-name>-` prefix**: spatie stores blobs at disk-relative path `<backup_name>/<filename>`, but the `Content-Disposition` filename is just the basename (the timestamp + `.zip`). Per the spatie default, `config/backup.php`'s `filename_prefix` is empty; if a future session sets a non-empty prefix, the filename emitted here changes accordingly.

FM-side stores blobs by this filename for round-trip parity; if FM stores blobs from multiple installs, FM-side prefixing (e.g., `<install-slug>-<filename>`) is FM's concern — the CRM emits what spatie generates.

## `/api/backup/blob` — Response — `404 Not Found`

```json
{
  "error": "no_backup_available",
  "message": "No backup found for backup name \"NonProfitCRM\" on any configured disk"
}
```

Returned when **all** configured disks (resolved per the disk-fallback rule above) have no recognized backup blobs. Recovery is operator-side: trigger a backup via `POST /api/backup/trigger` (or wait for the next scheduled run) and retry. FM consumers should classify this as recoverable.

The envelope shape mirrors the `/api/logs` 404 — `{error, message}` only, no `contract_version` field. v2.3.0 does not change the cross-endpoint error-envelope shape; if a future revision adds `contract_version` to error envelopes, that will be a deliberate cross-endpoint sweep.

## `/api/backup/blob` — Response — `500 Internal Server Error`

Two distinct cases, both with sanitised single-line `message` (same pipeline as `/api/backup/trigger`'s `message` field — application-root prefix strip, newline collapse to ` | `, 500-char cap with trailing `…`):

### `backup_destinations_not_configured`

```json
{
  "error": "backup_destinations_not_configured",
  "message": "BACKUP_DISKS env var resolves to an empty disk list"
}
```

Returned when no disk is configured (the `BACKUP_DISKS` env var is unset, empty, or contains only whitespace entries). Distinct from 404 because the operator action is different — a 500 here means the install is not configured for backup destinations at all (operator must set `BACKUP_DISKS`), while a 404 means the install is configured but no backups have been produced yet (trigger one and retry).

### `backup_disk_error`

```json
{
  "error": "backup_disk_error",
  "message": "<sanitised exception or driver-error message>"
}
```

Returned when the storage layer surfaces a synchronous exception during disk resolution or download invocation (e.g., a misconfigured S3 endpoint that throws on `mimeType()` or `size()`, a filesystem permission error during `download()`). Note: spatie's `BackupDestination` swallows some asynchronous errors internally (e.g., `allFiles()` exceptions during enumeration); those manifest as 404 with the no-backup-available envelope, not as a 500. The catch blocks defending the disk-lookup and `Storage::disk()->download()` paths handle the synchronous exception class.

### Status-code semantics for FM consumers

| Code  | Meaning                                                                              | Recovery                                                                         |
|-------|--------------------------------------------------------------------------------------|----------------------------------------------------------------------------------|
| `200` | Blob streamed.                                                                       | None.                                                                            |
| `404` | All configured disks are empty of backups.                                           | Trigger a backup via `/api/backup/trigger` (or wait for schedule) and retry.    |
| `500` `backup_destinations_not_configured` | Install has no backup disks configured.                            | Operator sets `BACKUP_DISKS` in the install's `.env` and reloads.               |
| `500` `backup_disk_error`                  | Storage driver surfaced a synchronous exception.                   | Operator action likely required (check disk credentials, paths, permissions).   |

## `/api/admin/recover` — Request

```
POST /api/admin/recover
```

- POST-only. Non-POST requests do not invoke the controller — they fall through Laravel's route table and typically resolve to `404 Not Found` via the public-site page-slug catchall (same path-shape behaviour as `/api/backup/trigger` against non-POST).
- mTLS-gated at nginx (same `fm-client.crt` as the other four endpoints; a per-location `if ($ssl_client_verify != "SUCCESS") { return 403; }` strict-gate ships on `/api/admin/recover` in both `docker/nginx/default.conf` and `docker/nginx/prod.conf`).
- Throttled at **6 requests per minute per source IP** (`throttle:6,1`) — matching `/api/backup/trigger` rather than the 60-rpm polling endpoints. Admin recovery is a rare, sensitive operation that should never see burst traffic.
- **Request body (JSON):**

  | Field     | Type            | Required | Description                                                                                   |
  |-----------|-----------------|----------|-----------------------------------------------------------------------------------------------|
  | `email`   | string          | yes      | Email of the locked-out admin to recover. Identifies the target `User` row.                   |
  | `actions` | array of string | yes      | One or more of `reset_2fa`, `reset_password`. Composable — FM asks for exactly what's needed. |

  - `reset_2fa` — clears the target's two-factor enrollment (`two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`) so the admin re-enrolls on next login. Password is unchanged and assumed known.
  - `reset_password` — sets a node-generated temporary password and returns it once in the response envelope (see the security note below). The admin signs in with it and changes it.

### Auth model — "trust the connection," identity verified out-of-band

This endpoint mutates an admin's authentication, but carries **no app-layer identity check** — it rides the same nginx mTLS gate as every other FM endpoint. The model: if the request arrived through the gate, FM presented the trusted client cert, and the operator has already verified the locked-out admin's identity out-of-band against their own external-vault recovery PIN ("telephone password"). **No recovery secret is stored, hashed, or verified anywhere in the CRM or FM** — the app holds no PIN. The audit records "operator reset 2FA/password for user Y via the endpoint," not "…after PIN verification": the identity check is a procedure the operator follows, not a control the system attests. This is the accepted trade-off for the current solo-operator, person-to-person trust model.

The endpoint can recover **any** admin, including the protected oldest super-admin the admin UI's delete-guard hides (`User::isProtected()`) — that account is the likeliest lockout victim, and reset is not delete, so the guard is deliberately not consulted here.

## `/api/admin/recover` — Response — `200 OK` (success)

```json
{
  "contract_version": "2.5.0",
  "status": "success",
  "email": "admin@example.org",
  "actions_applied": ["reset_2fa", "reset_password"],
  "temporary_password": "k7mPq2tRv9xLnB4dWf6h",
  "recovered_at": "2026-06-12T17:04:11+00:00",
  "message": null
}
```

### Fields

| Field                | Type            | Description                                                                                                                          |
|----------------------|-----------------|--------------------------------------------------------------------------------------------------------------------------------------|
| `contract_version`   | string          | Semver. Same value `/api/health` reports.                                                                                            |
| `status`             | string          | `"success"` when the requested actions were applied; `"failed"` otherwise. Enum `{success, failed}`. Always HTTP `200`.              |
| `email`              | string \| null  | The target admin's email (echoes the request; `null` only when a malformed request omitted `email`).                                |
| `actions_applied`    | array of string | The actions performed this call (subset of `reset_2fa`, `reset_password`). Empty on failure.                                        |
| `temporary_password` | string \| null  | The one-time generated password — present **only** when `reset_password` ran; `null` otherwise. See the security note.              |
| `recovered_at`       | string \| null  | ISO 8601 timestamp of the recovery; `null` on failure.                                                                              |
| `message`            | string \| null  | `null` on success; a sanitised single-line operator-readable reason on failure.                                                     |

### Security note — the temporary password crosses the wire once

When `reset_password` is requested, the node generates a fresh random password, sets it on the admin, and returns the **plaintext** value in `temporary_password` so the operator can relay it to the locked-out admin. It transits **once**, inside the mTLS-encrypted response, and is **not stored anywhere in plaintext** (the CRM persists only the hash). This is a deliberate, documented exception to the contract's general "no user records / secrets in any response" rule — scoped to this endpoint, justified by the operator-recovery use case, and protected by the same mTLS gate that guards every other endpoint. FM-side handling should treat the field as a secret: show it once to the operator, never log it.

## `/api/admin/recover` — Response — `200 OK` (failed)

```json
{
  "contract_version": "2.5.0",
  "status": "failed",
  "email": "nobody@example.org",
  "actions_applied": [],
  "temporary_password": null,
  "recovered_at": null,
  "message": "no admin found for that email"
}
```

### Failure modes

All return HTTP `200` with `status: "failed"` (mirrors `/api/health` and `/api/backup/trigger` — the body is authoritative, never HTTP status alone):

1. **No admin matches `email`.** `message: "no admin found for that email"`. Recoverable by retrying with the correct address.
2. **Malformed request.** Missing/invalid `email`, or `actions` empty or containing an unrecognised value. `message` is the first validation error; `email` echoes whatever string was supplied, or `null`.
3. **Unexpected exception during the reset.** `message` is a sanitised single-line string (same pipeline as `/api/backup/trigger`: application-root prefix strip, newline collapse to ` | `, 500-char cap). No stack traces.

There is no separate non-200 application status for this endpoint; nginx still emits `403` / `400` for mTLS-gate failures and the route's throttle emits `429`.

## Response — auth-failure paths (nginx-emitted, not application)

Applies to all five FM agent endpoints (`/api/health`, `/api/logs`, `/api/backup/trigger`, `/api/backup/blob`, `/api/admin/recover`). When the request fails the mTLS gate, nginx emits the response without invoking PHP. The body is plain HTML (whatever nginx renders for the error code), not a JSON envelope.

- **`403 Forbidden`** — no client cert presented. The TLS handshake completes (because `ssl_verify_client` is `optional` at the server level so public routes stay reachable); the per-location gate then fires `if ($ssl_client_verify != "SUCCESS") { return 403; }`. Fleet Manager seeing repeated `403`s should suspect FM-side config (the HTTP client is not configured to present its cert).
- **`400 Bad Request`** — body "The SSL certificate error". A cert was presented but did not match the trusted cert at `ssl_client_certificate`. Fleet Manager seeing repeated `400`s should suspect cert misalignment (the FM-side cert no longer matches what the CRM trusts; the operator may need to re-paste).

## Response — `429 Too Many Requests`

Applies to all five FM agent endpoints (each has its own throttle bucket). `/api/health`, `/api/logs`, and `/api/backup/blob` use `throttle:60,1` (60 rpm per IP); `/api/backup/trigger` and `/api/admin/recover` use `throttle:6,1` (6 rpm per IP) — backups are expensive, and admin recovery is a sensitive auth-mutating operation. Standard Laravel rate-limiter response. Fleet Manager should back off with exponential jitter and retry. The throttles defend against polling-bug storms on the FM side; well-behaved consumers will never see this.

## Response — `503 Service Unavailable`

**Reserved.** Not emitted by v2.3.0. Future versions may use `503` to signal an explicit endpoint-disabled state (e.g., maintenance mode).

---

## Version negotiation

Every `/api/health` response carries `contract_version`. (`/api/logs` does not. `/api/backup/trigger` does — its envelope carries `contract_version` so FM-side trigger paths can verify version alignment without an extra `/api/health` round-trip. `/api/backup/blob` does not — the success response is a binary stream and the error envelopes mirror `/api/logs`' shape; FM-side blob paths re-poll `/api/health` if version verification is wanted. FM treats `/api/health`'s value as authoritative for the install on every poll.) Fleet Manager:

- Compares against the canonical version it fetches via `WebFetch` against `https://raw.githubusercontent.com/abeuscher/npc-beta/main/docs/fleet-manager-agent-contract.md` at session-bootstrap time.
- Logs a drift warning if the install's `contract_version` does not match the fleet baseline.
- Stays forward-compatible within a major: a Fleet Manager that understands `2.0` can poll a CRM speaking `2.X` (X > 0) as long as the v2.0 fields it relies on are still present. New fields are additive within a major.
- Treats a major bump (`3.0`) as a breaking change — the Fleet Manager build is updated to the new shape before that contract version reaches any install.

The CRM-side reads its emitted `contract_version` from the `HealthController::CONTRACT_VERSION` constant. A version bump is a code change in the CRM repo plus a CHANGELOG entry in this doc plus the Cross-Repo block update in both repos' `sessions/session-outlines.md`.

---

## Security posture

- Authentication is enforced at the TLS layer by nginx. The application has no auth code path for any of the five FM endpoints — request arrival IS the auth proof.
- Per-install cert trust: each CRM install trusts exactly one FM-side cert. Compromise of one install's cert does not cascade across the fleet.
- All five routes are in the API middleware group — stateless, no session, no CSRF. The throttle middleware applies independently of cert presentation.
- No DB rows, user records, request payloads, exception messages, or stack traces appear in any response — only the documented response shapes and exception **class names** (or sanitised `message` strings on `/api/backup/trigger` and `/api/backup/blob`) where useful. The **one deliberate, documented exception** is `/api/admin/recover`, which returns a one-time `temporary_password` when `reset_password` runs (see that endpoint's security note) — scoped to the operator-recovery use case, transiting once over the mTLS-encrypted channel, never stored in plaintext. The `/api/logs` body returns Laravel application log lines verbatim and inherits the team's "don't log secrets" discipline; the endpoint does **not** re-redact. If a future audit surfaces a leak, redaction lands as a separate session, not retroactively.
- All five endpoints are rate-limited at the application layer; nginx can apply additional rate-limiting if needed. The `/api/backup/trigger` and `/api/admin/recover` caps (`throttle:6,1`) are intentionally tighter than the others' (`throttle:60,1`) — backups are expensive to run, and admin recovery is a sensitive auth-mutating operation; blob downloads are I/O-bound and cheap, hence the 60/min ceiling on `/api/backup/blob`.
- The cert at `ssl_client_certificate` has **no read access to anything** in the CRM beyond these five endpoints. It is not a user credential, not a session bootstrap, not a webhook secret. The application doesn't even read the cert — nginx alone validates it.
- `/api/logs` is read-only. `/api/backup/blob` is read-only. There is no log-write, log-rotate, or log-delete affordance on the contract surface; there is no blob-write, blob-delete, or blob-mutation affordance on `/api/backup/blob` (write is mediated by `/api/backup/trigger` only).

### Data-access boundary — counts only, never raw node data *(governs the `data_hygiene` subcheck)*

A standing boundary for all data-visibility work (the **Fleet Data Hygiene** track, CRM-side `sessions/tracks/fleet-data-hygiene.md`): **Fleet Manager must never be *built* to read a node's actual data — even though mTLS makes it technically possible.** This extends the "no DB rows in any response" rule above to the hygiene work:

- The additive `data_hygiene` subcheck on `/api/health` (**shipped at v2.4.0**) carries **aggregate counts only** (orphan-page / residual-scrub-record / orphan-media-directory / dead-owner-media counts) — non-PII integers, never rows, titles, emails, or contents. No raw-data / row-dumping endpoint is added to this contract. See § `data_hygiene` subcheck.
- **Deep audit** (reading actual records) is **node-local + consent-gated** — run via `php artisan app:data-hygiene --deep` on a node the owner controls, never an FM-initiated capability over the wire.
- FM gates any data-touching operation behind a **manual, user-editable per-node "maintenance / auditable" toggle** (default off) — an FM-side guardrail against accidental intrusion; defense-in-depth, the node may also refuse gated ops unless a node-side flag is set. **This toggle is Fleet Manager repo work** (the remaining Phase-2 half) — it pairs with the gated deep-audit / cleanup / remediation operations, not with the always-safe count-only subcheck, which needs no gate.

Shipped at v2.4.0 (the count-only subcheck); the FM-side toggle + subcheck consumption is the remaining FM-repo half.

### Recovery posture and FM-side trust assumptions

These describe the v2.1.0 security posture; items carry status as either **shipped at this revision** (item 1) or **FM-side intended posture, Beta-1 scope** (items 2 + 3). FM-side absorption sessions promote the intended items to shipped status; this section updates without a contract bump as that happens. v2.3.0 (this revision) adds the FM-side-readable half of the restore primitive via `/api/backup/blob` — restore execution itself remains manual `pg_restore` operator-side per the existing posture; FM 022 wraps the blob fetch + manual restore drill into an operator-facing affordance, but the CRM never executes restore on its own behalf.

1. **Break-glass recovery path.** Each CRM install trusts exactly one FM-side cert. If FM is compromised, recovery is operator SSH + cert swap on every CRM install, not a contract-level rotation flow. The CRM-side rotation script (`bin/rotate-fm-cert.sh`) and the recovery runbook (`docs/runbooks/fm-compromise-recovery.md`) document the per-install procedure.

2. **FM's off-filesystem-key posture.** FM does not store its master encryption key on disk. The key is bootstrapped at FM startup through an operator-presence step (mechanism FM-side) and held in process memory only. The CRM-side trust assumption follows: FM cannot self-decrypt the trusted-cert keypairs without an operator at the FM console. A stolen FM disk image does not unlock the fleet.

3. **Audit-sink discipline.** FM emits an external append-only audit log of every action against the CRM contract surface (poll dispatch, log fetch, install / restore actions when those land), mirrored to a write-only object-locked Spaces bucket FM cannot delete from after write. The CRM-side nginx access logs are the CRM-side complement — together the two form the cross-repo audit trail. (FM's broader admin-action audit posture is FM-side scope and not specified by this contract.)

### Demo-node reset coordination

*(v2.3.0 revision, session 335 — no contract surface change, no version bump. This documents an FM-driven coordination, not an HTTP endpoint; it lives here because this doc is the canonical CRM↔FM coordination surface FM WebFetches.)*

The single demo node (`APP_ENV=demo`, `isDemoMode()`) resets on a schedule by **restoring a curated baseline blob** — the `RestoreFromBlob` route flagged as the alternative in the CRM→FM demo-node handoff (CRM session 321), now **selected** over the local `demo:reset` reseed. The responsibility split:

- **FM owns the loop.** FM provisions the node, generates its `.env`, writes the reset schedule (cron), and **pushes the curated baseline blob onto the node through its provisioning channel** — the same channel that writes `.env`, **not** the runtime mTLS HTTP contract. The blob is *pushed*, never pulled: the node needs **no outbound egress** for the reset, so the demo node's egress firewall (deny outbound except DNS/NTP/GHCR, block SMTP — the one security control that matters) stays as tight as the local-cron route would have kept it. FM sets env-specific values (`base_url`, `IMAGE_TAG`) at provision time.
- **CRM provides the restore primitive.** A demo-mode-gated `demo:restore` artisan command restores the pushed blob (DB + media) and fixes up env-specific values from the node's own `.env`. It is **hard-gated to `isDemoMode()` at the code level** and refuses to run otherwise — a restore-from-arbitrary-blob primitive must never be reachable or runnable on a real customer node.

Consequences for FM-side coordination:

- **`IMAGE_TAG` unpin.** The demo node's `IMAGE_TAG` pin (handoff item 2, *"so rolling upgrades don't disrupt a live demo"*) is **lifted** — FM may upgrade the demo node like any other. To preserve the pin's original intent, schedule upgrades into the daily reset window (upgrade + restore together, off-peak) so an upgrade never interrupts a live prospect session.
- **Baseline alignment.** FM and CRM align on the canonical baseline blob — how it is produced (the operator authors the demo, then snapshots via `backup:run` or the existing `/api/backup/trigger` + `/api/backup/blob` endpoints) and handed to FM for distribution. This is the *"we'll align the baseline"* step the handoff names.
- **Faker / `--no-dev`.** The restore-from-blob route makes the demo's Faker dependency a non-issue on the node: synthetic data is generated in the authoring environment (dev-deps present) when the snapshot is built; the production demo node only ever restores a blob and never runs Faker.
- **Cross-node blob portability (session 365).** The baseline blob is authored on one install and restored on the demo node — a *cross-node* restore. Backups dumped before CRM session 365 carried the authoring node's DB role grants/ownership and aborted `demo:restore` on the demo node's missing read-only role (surfaced at FM 042, 2026-06-05). Session 365 fixes this dump-side (`pg_dump --no-privileges --no-owner`); baselines re-recorded from a fixed CRM image restore cleanly cross-node. **The existing demo baseline must be re-recorded from a fixed node before the live demo-restore loop can close.** See § Blob content — role-privilege-free dumps.

The CRM-side `demo:restore` command lands in the demo session following 335; this revision records the coordination decision so FM can start its half in parallel. No HTTP endpoint, no `CONTRACT_VERSION` change — the contract stays `2.3.0`.

### Client billing — suspension flag + billing-state document

*(v2.6.0, session 366 — the node half of client billing. Like § Demo-node reset coordination, the two artifacts below are **FM-driven pushes over FM's existing provisioning channel, not HTTP endpoints** — the node gains no sibling to its five mTLS endpoints and FM gains no inbound surface. They live here because this doc is the canonical CRM↔FM coordination surface FM WebFetches. Enforcement is CRM-side; the vendor-Stripe integration, the state derivation, the clocks, the pushes, and the operator UI are all **FM-repo work** — no vendor-Stripe credential, config key, SDK, or webhook exists CRM-side, by design.)*

Billing state crosses the boundary in exactly one direction — **Stripe → Fleet Manager → node** — as two pushed artifacts. The split is load-bearing: **enforcement rides the env flag; display rides the document.**

**1. The suspension flag — `SUSPENSION_STATE` (enforcement half).** A single env key FM pushes via its existing single-key config-push machinery (the machinery that already sets one `.env` key over SSH and recreates containers — shipped FM-side for the public-website flag). Read node-side into `fleet.suspension.state`.

| Value | Node effect |
|---|---|
| `none` (or **absent**) | No suspension. **Absent = `none`, so the bump is forward-compatible with every running node.** |
| `admin_locked` | Every admin-panel surface — the Filament panel, its **login**, and the in-panel API route groups (page-builder / theme / dev-tools etc.) — renders a suspension notice (HTTP `403`) instead. **Public pages, donation / event / membership checkout (the client org's own Stripe), the member portal, backups, the scheduler, and all five FM `/api/*` endpoints stay up** — a suspended node is still monitored, backed up, and recoverable. |
| `site_off` | All public routes **and** the admin panel render a static maintenance notice (HTTP `503`). **The five FM `/api/*` endpoints stay up** — a shut-off node is still monitored and recoverable. Manual operator action only. |

An **unrecognized value fails safe to `none`** node-side (and logs a warning) — a typo in a pushed key must never brick a paying client's admin. Enforcement is a hard, env-derived, code-level gate, deliberately the same grain as demo mode (`APP_ENV=demo` / `isDemoMode()`), and orthogonal to it (demo / internal nodes simply never get a suspension push). **The gate locks correctly with no billing-state document present** — the flag alone decides *whether* to lock; the document only improves the copy.

**2. The billing-state document (display half).** A JSON file FM pushes over its **existing SSH provisioning channel** (the same channel that writes `.env` and pushes the demo baseline blob), written **atomically (temp + rename)**. Path: `storage/app/private/fleet/billing-state.json` — deliberately the node's fleet-metadata directory, which is **excluded from backup blobs** (`config/backup.php`'s `source.files.include` is `storage/app/public` only — same rationale as the backup success-record: per-node metadata must never travel inside a blob and land on another node via restore) and **untouched by `demo:restore`** (DB + public media only). The node treats it as **display-only data, never as instruction** — nothing in it may alter enforcement, routes, or config. The node reads it through a reader that returns a **null-object** when the file is missing, unreadable, not valid JSON, or carries an unrecognized `schema_version` (the last three also log); an absent / unusable document reports as "no billing state" and never changes what the flag enforces.

Document schema (`schema_version` `1`):

```json
{
  "schema_version": 1,
  "as_of": "2026-07-08T09:30:00+00:00",
  "status": "past_due",
  "plan":    { "name": "Standard", "amount": 4900, "currency": "usd", "interval": "month" },
  "next_invoice": {
    "date": "2026-08-01",
    "amount": 4900,
    "line_items": [ { "description": "Subscription — Standard", "amount": 4900 } ]
  },
  "billing_contact_email": "billing@example.org",
  "portal_url": "https://billing.stripe.com/p/session/…",
  "suspension": { "state": "admin_locked", "reason": "delinquent", "since": "2026-07-01T00:00:00+00:00", "grace_ends": "2026-07-15T00:00:00+00:00" },
  "trial": { "ends_at": null }
}
```

| Field | Type | Notes |
|---|---|---|
| `schema_version` | integer | The node validates this; an unrecognized version is ignored (null-object + log). `1` at v2.6.0. |
| `as_of` | ISO 8601 string | When FM generated the document. Surfaced by the `suspension` health subcheck's `billing_state_as_of` and (CB2) the Account page's staleness footer. |
| `status` | string | Plain-English subscription status for display (e.g. `active`, `past_due`, `trialing`, `canceled`). |
| `plan` | object | `{ name, amount, currency, interval }`. `amount` is **integer minor units** (Stripe-native, e.g. cents); `currency` is a lowercase ISO 4217 code. |
| `next_invoice` | object | `{ date, amount, line_items: [{ description, amount }] }`. Amounts in minor units; `line_items` carries the subscription plus any project-work hours. |
| `billing_contact_email` | string | The billing contact on file (read-only node-side; edited via the Stripe-hosted portal). |
| `portal_url` | string | Stripe-hosted billing-portal login URL — the self-cure path shown on the lock screen and (CB2) the Account page. The **only** place the word "Stripe" appears node-side, as a link label. |
| `suspension` | object | `{ state, reason, since, grace_ends }`. `reason` is one of `delinquent` / `trial_expired` / `canceled` / `manual` — it selects the lock-screen wording. This is **display detail**; the *enforced* state is the `SUSPENSION_STATE` flag, not this field. |
| `trial` | object | `{ ends_at }` (ISO 8601 or `null`). |

**What is deliberately absent:** any node endpoint that returns billing (or any) client data to FM — billing flows FM→node only, and the standing privacy boundary ("FM is never *built* to read node data") holds. No card data, no Stripe key, no webhook, and no new inbound FM surface of any kind on the node. The node's read-back to FM is the count-free `suspension` health subcheck (§ above) — a state string and a timestamp, nothing more.

FM starts writing the document + pushing the flag at FM-side session **FM-B2** (per the Client Billing & Account track); this CRM revision ships the node-side reader, enforcement, and subcheck that consume them, inert on every install until that push arrives.

---

## CHANGELOG

### `2.6.0` — 2026-07-08 (session 366)

**Additive within v2 major.** Ships the **node half of client billing** — the enforcement and display surface a client organization's node needs for suspension and account state. Everything money-shaped stays FM-repo-only (the vendor Stripe integration, the state derivation, the clocks, the pushes, the operator UI); **no vendor-Stripe credential, config key, SDK, or webhook exists CRM-side, by design.** Realises draft entry CB1 of the Client Billing & Account track (`sessions/tracks/client-billing-and-account.md`). Three additions:

- **The `SUSPENSION_STATE` env flag** (new § Client billing — suspension flag + billing-state document). Pushed by FM via its existing single-key config-push machinery; read node-side into `fleet.suspension.state`. Values `none` / `admin_locked` / `site_off`; **absent = `none`, so every running node is unaffected.** `admin_locked` renders a `403` suspension notice on the whole admin surface (panel, login, in-panel API groups) while the public site, donation/event/membership checkout, the member portal, backups, the scheduler, and all five FM `/api/*` endpoints stay up; `site_off` renders a `503` maintenance notice on public + admin surfaces while the FM `/api/*` endpoints stay up. An **unrecognized value fails safe to `none`** (and logs) — a typo must never brick a paying client's admin. Hard, env-derived, code-level gate, same grain as demo mode; enforcement rides the flag alone (the gate locks with no document present).
- **The billing-state document** (same new §). A display-only JSON file (`schema_version` `1`) FM pushes over its existing SSH provisioning channel — **not** an HTTP endpoint; the node gains no sibling to its five mTLS endpoints and FM gains no inbound surface. Written atomically (temp + rename) at `storage/app/private/fleet/billing-state.json` — the backup-**excluded** fleet-metadata dir (per-node metadata must never restore onto another node), untouched by `demo:restore`. Fields: schema version, `as_of`, plan, status, next invoice (with line items), billing contact email, Stripe-hosted portal URL, suspension (state / reason / since / grace-ends), trial. The node reads it through a null-object reader — missing / unreadable / malformed / unknown-schema all report "no billing state" and never influence enforcement. Reason codes (`delinquent` / `trial_expired` / `canceled` / `manual`) select the lock-screen wording; the *enforced* state is the flag, not the document.
- **A new `/api/health` subcheck, `suspension`** (new § `suspension` subcheck). `value` is `{state, billing_state_as_of}` — the node's currently-*enforced* state (from the flag, fail-safe-resolved) plus the document's `as_of` (`null` when no document) — FM's read-back that a push took effect. `green` when `state` is `none`, `yellow` otherwise, **never `red`**, and **excluded from the worst-of overall status** exactly like `data_hygiene` — a deliberately-suspended node is not an unhealthy node. State string + timestamp only: no email, no amounts, no reason code — within the counts-only / no-PII wire discipline.
- `HealthController::CONTRACT_VERSION` bumps `2.5.0 → 2.6.0` (via the shared `HasContractVersion` trait; `BackupController` reads the same constant, so its `contract_version` field moves to `2.6.0` automatically). Eight `/api/health` subcheck keys now (`suspension` added; the prior seven unchanged).
- **Forward-compatible with v2.5.0 consumers.** A v2.5.0 consumer iterating the documented subchecks ignores the unknown `suspension` key and keeps working; a node with no pushed flag / document behaves exactly as before (absent flag = `none`, absent document = "no billing state").
- **Out of scope (FM-repo work / future).** Everything FM-side — the vendor Stripe sync, the billing model, the state derivation + clocks, the document/flag **pushes** themselves, the operator UI, the alerts — lands across FM-side sessions FM-B1…FM-B5. The node "My Account" page + `manage_account` permission + convention-drift guard cases are CRM session **CB2**; the demo-conversion cleanup command is **CB3**. **FM-side absorption pending at FM-B2** (refresh the cached contract copy to v2.6.0, then write the document + push the flag). No migration; no schema change; no new endpoint; no new inbound surface.

### `2.5.0` revision — 2026-07-07 (session 365)

**Documentation revision — no contract surface change, no version bump.** Records that the PostgreSQL dump inside a backup blob is now produced with `pg_dump --no-privileges --no-owner` (CRM `config/database.php` pgsql connection `dump` options), so blobs carry no role-privilege or ownership statements and are **portable across nodes**. Before this, a blob named the authoring node's per-node read-only DB role in `GRANT …` / `ALTER … OWNER` statements, so restoring it on a different node aborted on the missing role — the defect that blocked cross-node baseline restore since FM 042 (2026-06-05, `demo:restore` aborting on `role "crm_readonly_…" does not exist`). New sub-section § Blob content — role-privilege-free dumps under `/api/backup/blob`; consequence bullet added to § Demo-node reset coordination. **Old blobs stay non-portable — re-record baselines (including the demo baseline) from a fixed CRM image.** The node's own read-only role is re-granted node-side on provision, not carried in the dump; a cross-node restore may need the provision-time grant step re-run (FM-side runbook, see the A2 handoff). No HTTP surface change, no response-shape change; `HealthController::CONTRACT_VERSION` stays `2.5.0`. FM consumers pick this up on the next WebFetch refresh; no consumer-code change forced.

### `2.5.0` — 2026-06-12 (session 360)

**Additive within v2 major.** Adds a fifth endpoint, `POST /api/admin/recover`, the node half of **operator-mediated admin account recovery** — the Fleet-Manager-triggered reset for a locked-out admin. Promotes the session-304 "admin lockout has no recovery" flag, made urgent by session 359's mandatory admin TOTP 2FA (a correct password alone no longer gets a locked-out admin in). The FM control-panel UI that calls this endpoint is FM-repo work; this revision delivers the node endpoint it consumes (plus a node-local `admin:recover` break-glass artisan command, not part of the contract surface).

- New endpoint at `POST /api/admin/recover`. POST-only; JSON body `{ email, actions }` where `actions` is one or more of `reset_2fa` / `reset_password` (composable). Response is a JSON envelope `{ contract_version, status, email, actions_applied, temporary_password, recovered_at, message }`; `status` is `success` / `failed`; HTTP status is always `200` on application-level conditions (matches `/api/health` and `/api/backup/trigger`).
- `reset_2fa` clears the target's `two_factor_*` columns (re-enroll on next login); `reset_password` sets a node-generated temporary password and returns it **once** in `temporary_password` for the operator to relay (the **one documented exception** to the contract's "no user secrets in any response" rule — transits once over mTLS, only the hash is stored).
- Reuses the existing nginx-terminated mTLS gate — a per-location `if ($ssl_client_verify != "SUCCESS") { return 403; }` strict-gate ships on `/api/admin/recover` in both `docker/nginx/default.conf` (local) and `docker/nginx/prod.conf` (prod). Same trusted `fm-client.crt`; operators do not re-paste anything.
- **No app-layer identity check — "trust the connection."** Identity is verified out-of-band: the operator checks a recovery PIN against their own external vault before triggering, then the request rides the mTLS gate. **No recovery secret is stored, hashed, or verified in the CRM or FM.** Conscious trade-off (procedure-not-control) for the solo-operator trust model; the audit records "operator reset … for user Y," not "…after PIN verification."
- Recovers **any** admin, including the protected oldest super-admin (`User::isProtected()`) the admin UI's delete-guard hides — the likeliest lockout victim; reset is not delete, so the guard is deliberately bypassed.
- Throttle: `throttle:6,1` (6 rpm per source IP), matching `/api/backup/trigger` — admin recovery is rare and sensitive and should never see burst traffic.
- Every recovery is audited via the app's existing activity log (`ActivityLogger` → `activity_logs`; subject = the target `User`, event `admin_recovery`, meta `{actions, path}`, `path` = `endpoint` vs the CLI's `cli`), so the reset shows up in that user's activity timeline. No new table, no schema change. (En passant, the vestigial Spatie `config/activitylog.php` `table_name` default was corrected from the non-existent `activity_log` to the real `activity_logs`.)
- `HealthController::CONTRACT_VERSION` bumps `2.4.0 → 2.5.0` (via the shared `HasContractVersion` trait; `BackupController` reads the same constant, so its `contract_version` field moves to `2.5.0` automatically).
- **Forward-compatible with v2.4.0 consumers.** A v2.4.0 consumer that doesn't call the new endpoint continues working unchanged. FM-side consumers wanting the recovery affordance upgrade their HTTP client to POST the new shape and build the operator-facing control-panel UI.
- **Out of scope (FM-repo work / future).** The FM control-panel UI + the FM-side call; any in-app PIN storage/verification (the heavier hashed-in-app-PIN model is explicitly deferred); member/portal recovery; super-admin *creation/promotion* and force-password-change-on-next-login (omitted — the lockout victim already exists; the temp password stands until the admin changes it).

### `2.4.0` — 2026-06-10 (session 353)

**Additive within v2 major.** Adds a seventh `/api/health` subcheck, `data_hygiene`, surfacing the node-local **Fleet Data Hygiene** audit (CRM-side `sessions/tracks/fleet-data-hygiene.md`, Phase 2) as a **count-only, non-PII** signal so Fleet Manager sees per-node accumulated cruft at a glance. Realises the forward data-access boundary recorded in the 349 revision.

- New subcheck `data_hygiene` on `/api/health`. `value` is an object of four named integer counts — `{orphan_event_pages, scrub_records, orphan_media_dirs, dead_owner_media}` — sourced from `DataHygieneAudit::counts()`. **Aggregate counts only; no raw rows, slugs, titles, ids, emails, paths, or contents ever cross the wire.** The deep records mode stays node-local + consent-gated (`php artisan app:data-hygiene --deep`), never an FM-initiated capability.
- **Informational, never red.** `green` while the total across the four categories is under a single-integer soft `threshold` (default 100, CRM-side adjustable), `yellow` at/above it. `message` is `null` when green; a count-only summary when yellow.
- **Excluded from the worst-of overall status.** Unlike every other subcheck, `data_hygiene` does not participate in the top-level `status` derivation — a cruft pile is not a node-health emergency, so its `yellow` surfaces a per-node attention chip without dragging the node's headline health. The worst-of rule section is updated accordingly.
- **Freshness.** The CRM computes the counts behind a short server-side cache (≈10-minute TTL) — the audit walks the media filesystem and scans the media table, and `/api/health` is polled frequently. Counts may lag live state by up to the TTL (operationally irrelevant for hours/days-scale accumulation). No scheduled precompute is used (the worker runs no `schedule:work`).
- `value` and `threshold` shape notes generalised: `value` may now be an object of named non-PII integer counts; `threshold` may be a single integer soft bound (in addition to the existing `[low, high]` pair / `null` forms). The prior six subcheck keys are unchanged.
- `HealthController::CONTRACT_VERSION` bumps `2.3.0 → 2.4.0` (via the shared `HasContractVersion` trait; `BackupController` reads the same constant, so its `contract_version` field moves to `2.4.0` automatically).
- **Forward-compatible with v2.3.0 consumers.** A v2.3.0 consumer iterating the documented subchecks ignores the unknown `data_hygiene` key and keeps working unchanged. FM-side consumers wanting to surface the signal upgrade to read the new subcheck (object `value`, informational status, excluded-from-worst-of semantics).
- **Out of scope (FM-repo work / future).** The per-node "maintenance/auditable" toggle gating data-touching ops, and FM-side consumption/display of the subcheck, are Fleet Manager repo work — handed off, not in this revision. Bounded remediation (cleanup-on-upgrade + toggle-gated remediation trigger) is Phase 3, future. No migration; no schema change; no new endpoint.

### `2.3.0` revision — 2026-06-09 (session 349)

**Documentation revision — no contract surface change, no version bump.** Records a forward **data-access boundary** (new § Data-access boundary, under Security posture) governing the planned **Fleet Data Hygiene** work (CRM-side `sessions/tracks/fleet-data-hygiene.md`): Fleet Manager must never be *built* to read a node's actual data. The planned additive `data_hygiene` `/api/health` subcheck will carry **aggregate counts only** (orphaned-page / residual-scrub-record / orphan-media counts) — never raw rows or contents; deep audit stays node-local + consent-gated; a manual per-node "maintenance/auditable" toggle in FM gates any data-touching op. Nothing shipped yet — the subcheck is a future additive change that will bump `CONTRACT_VERSION` when it lands. `HealthController::CONTRACT_VERSION` stays `2.3.0`. FM-side consumers need no code change; they pick up the boundary note on the next WebFetch refresh.

### `2.3.0` revision — 2026-06-01 (session 335)

**Documentation revision — no contract surface change, no version bump.** Records the **demo-node reset coordination** decision (new § Demo-node reset coordination, under Security posture): the single demo node resets by restoring a curated baseline blob (`RestoreFromBlob`) rather than the local `demo:reset` reseed. FM owns the loop — provisions the node, writes the reset cron, and **pushes** the baseline blob onto the node via its provisioning channel (not the HTTP contract; the node needs no outbound egress, keeping the demo egress firewall tight). CRM provides a `demo:restore` artisan command, **hard-gated to `isDemoMode()`**, that restores the pushed blob (DB + media) and fixes env-specific values from the node's `.env`. The demo node's `IMAGE_TAG` pin is lifted (upgrades ride the daily reset window). The CRM-side `demo:restore` implementation lands in the demo session following 335; `HealthController::CONTRACT_VERSION` stays `2.3.0`. No HTTP surface change; FM-side consumers need no code change to the four endpoints — they pick up the coordination note on the next WebFetch refresh.

### `2.3.0` revision — 2026-05-15 (session 291)

**Documentation revision — no contract surface change, no version bump.** The `/api/health` `version` field's description and example values were corrected: it is a **build-stamped pre-1.0 pseudo-version of the form `0.<session>.<iteration>`** (e.g. `0.291.1`), set at image build time from the repo-root `VERSION` file, immutable per published image, semver-ordered so FM can compare before→after across per-client upgrades and roll back. Prior text wrongly described it as "the seven-character git SHA." Response shape, status enums, subcheck keys, and auth are unchanged; `HealthController::CONTRACT_VERSION` stays `2.3.0`. The CRM build pipeline now reads `VERSION`, bakes it as the `APP_VERSION` build-arg, and tags the published GHCR image with that exact string immutably (`latest` still moves; a re-used version tag fails the build rather than overwriting). FM-side consumers pick up the corrected field description on the next WebFetch refresh; no consumer-code change forced (FM was already reading `version` as an opaque string — it now carries ordered semantics it can act on).

### `2.3.0` — 2026-05-08 (session 268)

**Additive within v2 major.** Adds a fourth endpoint, `GET /api/backup/blob`, that streams the freshest backup zip from the configured backup destination disk to the FM caller. CRM-side prerequisite for FM 021 (`BackupBlobClient`) + FM 022 (operator-facing restore-to-fresh-node affordance) — together they satisfy the (c) cell of the Track A operations-parity success criterion in the CRM-side release plan.

- New endpoint at `GET /api/backup/blob`. GET-only; no request body; no query parameters at v2.3.0. Response on success is a raw zip stream with `Content-Type: application/zip`, `Content-Disposition: attachment; filename="<spatie-blob-filename>"`, `Content-Length: <bytes>`, `Cache-Control: no-store`.
- Reuses the existing nginx-terminated mTLS gate — a per-location `if ($ssl_client_verify != "SUCCESS") { return 403; }` strict-gate ships on `/api/backup/blob` in both `docker/nginx/default.conf` (local) and `docker/nginx/prod.conf` (prod). Same trusted `fm-client.crt`; operators do not re-paste anything.
- New per-location `fastcgi_read_timeout 600;` override on the blob location, mirroring `/api/backup/trigger`'s 600s ceiling. Defends against multi-GB blobs streaming through PHP buffer backpressure to slow FM-side networks (the default 300s ceiling is between-packets, not total request, but a single read window stalling past 300s is plausible and cheap to defend against).
- Throttle: `throttle:60,1` (60 rpm per source IP), matching `/api/logs` rather than `/api/backup/trigger`'s 6,1. Blob downloads are I/O-bound and cheap; the expected FM-side burst pattern is "one fetch after a successful trigger plus maybe one retry," and 60/min is generous for that.
- **Disk-fallback rule — two layers.** Layer A (preference): if `local` is present anywhere in `BACKUP_DISKS`, it is tried first regardless of authored order; remaining disks follow in their authored order. Layer B (fallthrough-on-empty): if the preferred disk has no blobs (`->newest()` returns null), resolution falls through to the next disk in the resolved order. The 404 envelope is emitted only when all configured disks are exhausted. The contract spec documents the rule so FM consumers can trace which disk's blob they're reading. The response itself does not expose the source disk — FM treats the blob as opaque-by-source.
- **Filename in `Content-Disposition` is the spatie default `Y-m-d-H-i-s.zip`** — for example, `2026-05-08-12-30-00.zip`. There is no `<backup-name>-` prefix at the spatie default (`config/backup.php`'s `filename_prefix` is empty); the `<backup_name>` portion is a directory inside the disk, not part of the filename. FM-side prefixing for multi-install storage is FM's concern.
- **Status-code semantics:**
  - `200` — blob streamed.
  - `404` `no_backup_available` — all configured disks empty; recoverable via `/api/backup/trigger` + retry.
  - `500` `backup_destinations_not_configured` — `BACKUP_DISKS` resolves to an empty list; operator action required (set `BACKUP_DISKS`).
  - `500` `backup_disk_error` — synchronous storage exception during disk resolution or download invocation; sanitised single-line `message` (same pipeline as `/api/backup/trigger`'s `message`: app-root prefix strip, newline collapse to ` | `, 500-char cap).
- Error envelopes (404 / 500) follow the `/api/logs` shape: `{error, message}` only, no `contract_version` field. v2.3.0 does not change cross-endpoint error-envelope shape; if a future revision adds `contract_version` to error envelopes, that lands as a deliberate cross-endpoint sweep.
- `HealthController::CONTRACT_VERSION` bumps `2.2.0 → 2.3.0` (the canonical field FM polls). `BackupController::CONTRACT_VERSION` bumps the same — its trigger envelope's `contract_version` field moves to `2.3.0` automatically. `/api/backup/blob`'s success response carries no envelope (raw stream); its error envelopes do not carry `contract_version`.
- Forward-compatible with v2.2.0 consumers. v2.2.0 consumers don't see the new endpoint and continue working unchanged. FM-side consumers wanting to use the new endpoint upgrade their HTTP client to handle the binary-stream + 404/500 envelope shape.
- **Implementation note — sibling method on `BackupController`.** The new action lives as `BackupController::blob()` next to `BackupController::trigger()`. The two share the existing `sanitise()` helper and `MAX_MESSAGE_LENGTH` constant; no new controller class, no new service.
- **Out of scope at v2.3:** historical blob enumeration (FM gets latest only; restore-from-an-older-blob remains a manual operator drill); per-disk targeting via query (`?disk=spaces`); progress streaming during long downloads; Range / resume support (HTTP 206 partial content); CRM-side restore primitives (restore stays manual `pg_restore` per A2(c) framing); cross-endpoint addition of `contract_version` to error envelopes.

**Scope note:** the v2.0.0-deferred FM-raised question of `last_backup_at` threshold-derivation ownership stays deferred — v2.3.0 is narrowly the blob-download addition. Threshold-derivation lands at a future v2.x bump when there's natural impetus.

### `2.2.0` — 2026-05-05 (session 263)

**Additive within v2 major.** Adds a third endpoint, `POST /api/backup/trigger`, that synchronously runs the existing `backup:run` artisan command and returns a JSON envelope reporting the new `last_backup_at` timestamp. Unblocks FM session 020 (operator-facing "Trigger backup now" affordance in the FM admin UI consuming this endpoint).

- New endpoint at `POST /api/backup/trigger`. POST-only; no request body. Response is a JSON envelope: `{ contract_version, status, last_backup_at, duration_ms, message }`. `status` is `success` or `failed`; HTTP status is always `200` on application-level conditions (matches `/api/health`'s 200-with-degraded-envelope pattern).
- Reuses the existing nginx-terminated mTLS gate — a per-location `if ($ssl_client_verify != "SUCCESS") { return 403; }` strict-gate ships on `/api/backup/trigger` in both `docker/nginx/default.conf` (local) and `docker/nginx/prod.conf` (prod). Same trusted `fm-client.crt`; operators do not re-paste anything.
- New per-location `fastcgi_read_timeout 600;` override on the trigger location so nginx does not kill the upstream connection at the default 300s while a backup is running. The default 300s on every other endpoint stays unchanged.
- Tighter throttle than the polling endpoints: `throttle:6,1` (6 rpm per source IP) vs `throttle:60,1` on `/api/health` and `/api/logs`. Each route has its own throttle bucket.
- 10-minute synchronous ceiling. The controller calls `set_time_limit(600)` defensively; PHP-FPM `request_terminate_timeout` is unset on the upstream `php:8.4-fpm` image (defaults to 0 — no PHP-FPM-imposed ceiling). The 600s wall is enforced by nginx + the in-PHP `set_time_limit(600)` only.
- **Integrity guard — success-record mtime cross-check.** When `Artisan::call('backup:run')` exits 0, the controller cross-checks the contents of `storage/app/private/fleet/last-backup-at` against the request's start time. If the recorded timestamp is null OR older than start, the response downgrades to `status: failed` with `message: "backup:run exited cleanly but success record was not updated"`. Prevents the misleading-success class of bug where the artisan command exits cleanly but the `RecordBackupSuccess` listener silently failed (event-subscription misconfig, filesystem write failure inside the listener, etc.). Distinct from the other two failure modes (non-zero exit, caught exception) and documented as such for FM consumers.
- **Error-message sanitisation pipeline.** Both error sources (`Artisan::output()` on non-zero exit, `\Throwable::getMessage()` on caught exception) flow through: strip absolute application-root prefix (`/var/www/html/`) → collapse newlines to ` | ` → cap at 500 characters with trailing `…` if longer → no stack traces. App-root-relative paths are kept; open-source-app threat model means they reveal nothing private.
- `HealthController::CONTRACT_VERSION` bumps `2.1.0 → 2.2.0`. The new `BackupController` carries its own `CONTRACT_VERSION = '2.2.0'` constant — its envelope includes `contract_version` so FM-side trigger paths can verify version alignment without an extra `/api/health` round-trip.
- Forward-compatible with v2.1.0 consumers. v2.1.0 consumers don't see the new endpoint and continue working unchanged. FM-side consumers wanting to use the new endpoint upgrade their HTTP client to handle the `/api/backup/trigger` shape.
- Out of scope at v2.2: async / queued backup execution; backup blob download; per-bucket / per-destination targeting from the API; backup-status streaming during long runs. The endpoint runs the configured backup pipeline; switching destinations stays an operator-side `.env` change.

**Scope note:** the v2.0.0-deferred FM-raised question of `last_backup_at` threshold-derivation ownership stays deferred — v2.2.0 is narrowly the backup-trigger addition. Threshold-derivation lands at a future v2.x bump when there's natural impetus.

### `2.1.0` revision — 2026-05-01 (session 253)

**Documentation revision — no contract surface change.** Security Posture section adds language naming the break-glass recovery path (CRM-side artifacts shipping in this revision: `bin/rotate-fm-cert.sh` + `docs/runbooks/fm-compromise-recovery.md`) and FM-side intended-posture statements for off-filesystem encryption keys + external append-only audit sink (FM Beta-1 scope per FM-side Security Posture Pivot at FM repo `sessions/session-outlines.md`). HTTP contract surface unchanged; consumers do not need to refresh.

### `2.1.0` — 2026-04-30 (session 251)

**Additive within v2 major.** Adds a second endpoint, `/api/logs`, that returns the tail of the Laravel application log so Fleet Manager (FM 013+) can surface logs from a CRM install without operator SSH.

- New endpoint at `GET /api/logs` with optional `?lines=N` (default 500, max 10000). Response is a JSON envelope: `{ lines, lines_returned, lines_truncated, source }`.
- Reuses the existing nginx-terminated mTLS gate — a per-location `if ($ssl_client_verify != "SUCCESS") { return 403; }` strict-gate ships on `/api/logs` in both `docker/nginx/default.conf` (local) and `docker/nginx/prod.conf` (prod). Same trusted `fm-client.crt`; operators do not re-paste anything.
- Reuses the existing `throttle:60,1` middleware shape. Each route has its own throttle bucket.
- Source is today's `LOG_CHANNEL=stack` / `LOG_STACK=single` posture: a single ever-growing `storage/logs/laravel.log` shared between the `app` (PHP-FPM) and `worker` (queue) containers via the docker-compose volume layout. Log lines from both containers are unified in the response. A future `daily` channel migration can introduce `?date=` via an additive v2.x bump.
- Caps: `?lines=N` clipped silently at 10000; raw line content capped at ~850 KB before JSON encoding (under a 1 MB encoded body ceiling). When either cap fires, **older** lines are dropped and `lines_truncated: true`. A single line longer than 8 KB is read intact across the controller's backward-read chunk boundaries.
- "Lines" in the response means newline-delimited rows in the file, **not logical log entries**. A single multi-line stack trace counts as N rows. Logical-entry parsing is out of scope at v2.1.
- Error envelopes (always JSON): `404 log_not_found`, `422 invalid_lines`, `500 log_unreadable` (exception class name only, matching `HealthController` discipline). nginx-emitted `403` / `400` for mTLS-gate failures stay plain HTML.
- Read-only endpoint. No log-write, log-rotate, or log-delete affordance.
- `HealthController::CONTRACT_VERSION` bumps `2.0.0 → 2.1.0`. The constant covers both endpoints; `/api/logs` does not carry its own version field — FM reads contract version from `/api/health`.
- Forward-compatible with v2.0.0 consumers. FM-side consumers wanting to use the new endpoint upgrade their HTTP client to handle the `/api/logs` shape.

**Scope note:** the v2.0.0-deferred FM-raised question of `last_backup_at` threshold-derivation ownership stays deferred — v2.1.0 is narrowly the log-fetch addition. Threshold-derivation lands at a future v2.x bump when there's natural impetus.

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
