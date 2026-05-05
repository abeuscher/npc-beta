# Fleet Manager Agent Contract

**Contract Version:** `2.2.0`
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
```

- Three routes. `/api/health` and `/api/logs` are GET-only; `/api/backup/trigger` is POST-only.
- Stateless — no session cookie, no CSRF token, not in the web middleware group.
- Each route carries its own rate-limit bucket. `/api/health` and `/api/logs` are rate-limited at **60 requests per minute per IP** (Laravel `throttle:60,1`); `/api/backup/trigger` is rate-limited at **6 requests per minute per IP** (`throttle:6,1`) because backups are expensive to run. The throttles defend against polling-loop bugs and accidental retrigger storms on the FM side. Auth-failure storms cannot reach the throttle — nginx returns 403 / 400 before the request hits PHP, so the rate limiter only ever sees requests that already passed the mTLS gate.

`/api/health` is documented under `/api/health — Response`. `/api/logs` is documented under `/api/logs — Request` and `/api/logs — Response`. `/api/backup/trigger` is documented under `/api/backup/trigger — Request` and `/api/backup/trigger — Response`.

## Auth

```
mTLS — terminated by nginx at the TLS layer.
```

- Authentication happens during the TLS handshake. Nginx is configured with `ssl_verify_client optional` at the server level and per-location `if ($ssl_client_verify != "SUCCESS") { return 403; }` strict-gates on **all three** FM agent endpoints (`/api/health`, `/api/logs`, `/api/backup/trigger`). Public routes (admin, portal, marketing pages) stay reachable without a client cert; only the FM agent endpoints are gated.
- Each CRM install trusts **exactly one** specific FM-side cert, configured at nginx via `ssl_client_certificate` pointed at `/etc/nginx/certs/fm-client.crt`. No CA, no PKI tooling, no chain. Direct trust against the per-install cert.
- The FM operator pastes the trusted cert into the CRM droplet at `/opt/nonprofitcrm/nginx-certs/fm-client.crt` (bind-mounted into the nginx container). Restart nginx to apply.
- The application sees no auth signal. If the request reached the controller (any of the three), nginx already validated the client-cert presentation. PHP does not authenticate, does not read the cert, does not derive identity from it. The discipline is "trust the connection."
- Authentication failures are emitted by nginx, not the application. With `ssl_verify_client optional` at the server level, the TLS handshake completes either way; nginx then returns an HTTP error before the request ever reaches PHP. The specific code depends on the failure mode:
  - **No client cert presented:** the per-location `if ($ssl_client_verify != "SUCCESS") { return 403; }` gate fires → `403 Forbidden`.
  - **A client cert is presented but does not match the trusted cert:** nginx's SSL error path fires → `400 Bad Request` with body "The SSL certificate error".
  In both cases the body is plain HTML emitted by nginx, not a JSON envelope. There is no application-layer JSON `401` envelope in v2.0.0; consumers should not expect one for auth failures.
- **One cert per install in v2.x.** Reusing the same cert across multiple CRM installs is forbidden — Fleet Manager treats each install as a distinct credential boundary, and a leaked shared keypair would compromise the entire fleet at once. Multi-FM-instance support (one CRM trusting multiple FM-side certs) would land as an additive v2.x bump.

## `/api/health` — Response — `200 OK` (success, including subcheck failures)

```json
{
  "status": "green|yellow|red",
  "version": "abc1234",
  "timestamp": "2026-04-30T15:42:00+00:00",
  "contract_version": "2.2.0",
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

### Subchecks (v2.2.0 — six stable keys, unchanged from v1.2.0)

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
  "contract_version": "2.2.0",
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
  "contract_version": "2.2.0",
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

## Response — auth-failure paths (nginx-emitted, not application)

Applies to all three FM agent endpoints (`/api/health`, `/api/logs`, `/api/backup/trigger`). When the request fails the mTLS gate, nginx emits the response without invoking PHP. The body is plain HTML (whatever nginx renders for the error code), not a JSON envelope.

- **`403 Forbidden`** — no client cert presented. The TLS handshake completes (because `ssl_verify_client` is `optional` at the server level so public routes stay reachable); the per-location gate then fires `if ($ssl_client_verify != "SUCCESS") { return 403; }`. Fleet Manager seeing repeated `403`s should suspect FM-side config (the HTTP client is not configured to present its cert).
- **`400 Bad Request`** — body "The SSL certificate error". A cert was presented but did not match the trusted cert at `ssl_client_certificate`. Fleet Manager seeing repeated `400`s should suspect cert misalignment (the FM-side cert no longer matches what the CRM trusts; the operator may need to re-paste).

## Response — `429 Too Many Requests`

Applies to all three FM agent endpoints (each has its own throttle bucket). `/api/health` and `/api/logs` use `throttle:60,1` (60 rpm per IP); `/api/backup/trigger` uses `throttle:6,1` (6 rpm per IP) because backups are expensive. Standard Laravel rate-limiter response. Fleet Manager should back off with exponential jitter and retry. The throttles defend against polling-bug storms on the FM side; well-behaved consumers will never see this.

## Response — `503 Service Unavailable`

**Reserved.** Not emitted by v2.2.0. Future versions may use `503` to signal an explicit endpoint-disabled state (e.g., maintenance mode).

---

## Version negotiation

Every `/api/health` response carries `contract_version`. (`/api/logs` does not. `/api/backup/trigger` does — its envelope carries `contract_version` so FM-side trigger paths can verify version alignment without an extra `/api/health` round-trip. FM treats `/api/health`'s value as authoritative for the install on every poll.) Fleet Manager:

- Compares against the canonical version it fetches via `WebFetch` against `https://raw.githubusercontent.com/abeuscher/npc-beta/main/docs/fleet-manager-agent-contract.md` at session-bootstrap time.
- Logs a drift warning if the install's `contract_version` does not match the fleet baseline.
- Stays forward-compatible within a major: a Fleet Manager that understands `2.0` can poll a CRM speaking `2.X` (X > 0) as long as the v2.0 fields it relies on are still present. New fields are additive within a major.
- Treats a major bump (`3.0`) as a breaking change — the Fleet Manager build is updated to the new shape before that contract version reaches any install.

The CRM-side reads its emitted `contract_version` from the `HealthController::CONTRACT_VERSION` constant. A version bump is a code change in the CRM repo plus a CHANGELOG entry in this doc plus the Cross-Repo block update in both repos' `sessions/session-outlines.md`.

---

## Security posture

- Authentication is enforced at the TLS layer by nginx. The application has no auth code path for `/api/health` or `/api/logs` — request arrival IS the auth proof.
- Per-install cert trust: each CRM install trusts exactly one FM-side cert. Compromise of one install's cert does not cascade across the fleet.
- All three routes are in the API middleware group — stateless, no session, no CSRF. The throttle middleware applies independently of cert presentation.
- No DB rows, user records, request payloads, exception messages, or stack traces appear in any response — only the documented response shapes and exception **class names** in `message` fields where useful. The `/api/logs` body returns Laravel application log lines verbatim and inherits the team's "don't log secrets" discipline; the endpoint does **not** re-redact. If a future audit surfaces a leak, redaction lands as a separate session, not retroactively.
- All three endpoints are rate-limited at the application layer; nginx can apply additional rate-limiting if needed. The `/api/backup/trigger` cap (`throttle:6,1`) is intentionally tighter than the polling endpoints' (`throttle:60,1`) because backups are expensive to run.
- The cert at `ssl_client_certificate` has **no read access to anything** in the CRM beyond these two endpoints. It is not a user credential, not a session bootstrap, not a webhook secret. The application doesn't even read the cert — nginx alone validates it.
- `/api/logs` is read-only. There is no log-write, log-rotate, or log-delete affordance on the contract surface.

### Recovery posture and FM-side trust assumptions

These describe the v2.1.0 security posture; items carry status as either **shipped at this revision** (item 1) or **FM-side intended posture, Beta-1 scope** (items 2 + 3). FM-side absorption sessions promote the intended items to shipped status; this section updates without a contract bump as that happens.

1. **Break-glass recovery path.** Each CRM install trusts exactly one FM-side cert. If FM is compromised, recovery is operator SSH + cert swap on every CRM install, not a contract-level rotation flow. The CRM-side rotation script (`bin/rotate-fm-cert.sh`) and the recovery runbook (`docs/runbooks/fm-compromise-recovery.md`) document the per-install procedure.

2. **FM's off-filesystem-key posture.** FM does not store its master encryption key on disk. The key is bootstrapped at FM startup through an operator-presence step (mechanism FM-side) and held in process memory only. The CRM-side trust assumption follows: FM cannot self-decrypt the trusted-cert keypairs without an operator at the FM console. A stolen FM disk image does not unlock the fleet.

3. **Audit-sink discipline.** FM emits an external append-only audit log of every action against the CRM contract surface (poll dispatch, log fetch, install / restore actions when those land), mirrored to a write-only object-locked Spaces bucket FM cannot delete from after write. The CRM-side nginx access logs are the CRM-side complement — together the two form the cross-repo audit trail. (FM's broader admin-action audit posture is FM-side scope and not specified by this contract.)

---

## CHANGELOG

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
