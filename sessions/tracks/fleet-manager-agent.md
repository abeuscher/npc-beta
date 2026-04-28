# Track: Fleet Manager Agent

The CRM-side companion to the Fleet Manager operational tool. Fleet Manager is a separate Laravel application in a separate repository that polls every CRM install on a schedule and surfaces health, version, and backup state in a single dashboard. This track owns the *CRM half* of that arrangement: the authenticated `/api/health` endpoint Fleet Manager polls, the version pipeline that feeds the response, and the backup mechanism whose success-record `last_backup_at` reports.

The canonical product spec for the entire two-repo system — purpose, scope, two-repo coordination protocol, local development scheme, resolved leans — lives at [`sessions/fleet-manager-planning-spec.md`](../fleet-manager-planning-spec.md). That doc is the seed for the Fleet Manager repo and the reference for both workstreams. This track doc carries CRM-side history and forward plan only.

This doc carries three things:

- **Status snapshot** — where the track is right now, what's next.
- **Phase Retrospectives** — compressed history of closed phases (sessions list, outcomes, key decisions, carry-forwards).
- **Forward plan** — the remaining phase, with design decisions, security posture, and known risks.

When a phase closes, its retrospective lands in this doc and its entry in the roadmap (`session-outlines.md`) collapses to a one-liner.

---

## Status snapshot

**Last update:** 2026-04-28 (Phase 1 closed at session 238).

**Complete:** Phase 1 — CRM-Side MVP + v1.0.0 Contract (session 238).

**Active:** none. Phase 2 (Backup Pipeline) is queued but not scheduled — it lands when a second client install is in flight or when Fleet Manager v1 approaches production launch and `last_backup_at = null` becomes operationally annoying.

**Cross-repo coordination state:**

- Agent contract version: `1.0.0` (live as of session 238).
- Spec doc: [`docs/fleet-manager-agent-contract.md`](../../docs/fleet-manager-agent-contract.md). Canonical URL for FM-repo `WebFetch`: `https://raw.githubusercontent.com/abeuscher/npc-beta/main/docs/fleet-manager-agent-contract.md`.
- Cross-Repo block in `sessions/session-outlines.md` (top of file, just below Active tracks) reflects v1.0.0 and last-boundary-touching session 238.
- The standing cross-repo flag in every session base prompt is now active (no longer dormant) — it fires whenever a session modifies `/api/health`, the auth middleware, the response schema, the VERSION pipeline, or anything in `app/Http/Controllers/Api/Fleet/*`.

**Carry-forwards (none scheduled):**

- A custom artisan command for generating the agent key (`php artisan fleet:generate-agent-key`) — operator currently uses `Str::random(64)` or `php -r 'echo bin2hex(random_bytes(32));'` directly. Lands if friction emerges.
- A Filament admin UI for managing the agent key — out of scope by design; key lives in `.env`.
- Multi-tenant API key support (one CRM serving multiple FM instances) — explicitly forbidden by the v1.0.0 contract. If multi-FM scenarios emerge, the surface change is additive (an array of accepted keys) and warrants a `1.1.0` contract bump.
- Key rotation flow — manual replacement on the droplet for v1; automation deferred.
- Disk subcheck automated failure-path test — `disk_free_space` mocking requires scaffolding beyond v1 needs; integration-tested in dev only.

---

## Phase Retrospectives

**Phase 1 — CRM-Side MVP + v1.0.0 Contract (session 238).** The inaugural fire of the cross-repo coordination flag, by design — every artifact the flag is meant to govern landed in one session.

- **Routes & middleware.** `bootstrap/app.php` opted into Laravel 11's `api:` route file (previously web + commands only). New `'fleet.agent'` middleware alias resolves to `App\Http\Middleware\AuthenticateFleetManagerAgent`, which compares the bearer token to `config('fleet.agent.api_key')` via `hash_equals()` (timing-safe) and fails closed: missing config → 500 `{"error":"misconfigured"}`; missing/empty/wrong token → 401 `{"error":"unauthorized"}`. Body never echoes submitted or expected key.
- **Controller + subchecks.** `App\Http\Controllers\Api\Fleet\HealthController::index()` composes the JSON response. Six private subcheck methods each return the canonical four-key shape `{status, value, threshold, message}`: `app` (always green; method running IS the check), `database` (`DB::connection()->getPdo()` in try/catch; red on `Throwable` with `class_basename($e)` only — never the exception message), `redis` (`Redis::ping()` same shape), `disk` (percent-used integer; yellow ≥80, red ≥95), `last_backup_at` (green/null/`'backup pipeline not yet implemented'` for v1 — green not yellow because "unknown without a pipeline" is not an alarming state), `version` (always green, mirrors `config('fleet.agent.app_version')`). Overall status is the worst-of (`red` if any red; else `yellow` if any yellow; else `green`). `CONTRACT_VERSION = '1.0.0'` exposed as a class constant.
- **Config.** New `config/fleet.php`. `agent.api_key` reads `FLEET_MANAGER_AGENT_KEY`. `agent.app_version` reads `/var/cache/app/VERSION` via `is_readable + file_get_contents + trim` with `'dev'` fallback — closure in the config file itself (config is evaluated once and cached via `config:cache` in production; no service provider needed). `.env.example` gained `FLEET_MANAGER_AGENT_KEY=` under a new section, no default value.
- **Version pipeline.** `Dockerfile` (`app` target) gained `ARG APP_VERSION=dev` at the top of the stage and a late `RUN mkdir -p /var/cache/app && echo "$APP_VERSION" > /var/cache/app/VERSION` (after the chown, so `COPY . .` invalidations don't bust the version-write layer position). `/var/cache/app` is outside `/var/www/html` so the local bind mount does not shadow the file at runtime. `docker-compose.yml`'s `app` and `worker` services' `build:` blocks gained `args: APP_VERSION: ${APP_VERSION:-dev}`. `.github/workflows/deploy.yml`'s build-args list gained `APP_VERSION=${{ steps.tag.outputs.IMAGE_TAG }}` (the SHA-7 already extracted earlier in the workflow). `docker-compose.prod.yml` deliberately untouched — it pulls pre-built images from GHCR; the version is baked at CI build time.
- **Contract spec doc — v1.0.0.** Replaced the v0.0.0 stub body with the actual surface: endpoint definition, auth handshake, response 200 schema with full subcheck table (one row per subcheck — name, value-shape, threshold, notes), 401 / 429 / 500 / 503 (reserved) error envelopes, overall-status worst-of rule, version-negotiation discipline (FM reads `contract_version` on every poll; forward-compat within a major; new fields are additive), security posture summary, complete CHANGELOG with both `1.0.0` and `0.0.0` entries preserved. `Contract Version:` field bumped 0.0.0 → 1.0.0; `Status:` field bumped `stub` → `active`.
- **Cross-Repo block.** `sessions/session-outlines.md` block updated: version `0.0.0 → 1.0.0`; last boundary-touching session in this repo `237 → 238`; pending boundary changes was *"v1.0.0 to be authored at session 238"*, now *"none"*.
- **Tests.** `tests/Feature/Api/Fleet/HealthEndpointTest.php` — 16 cases (15 fast + 1 `->group('slow')`). Auth (6): missing/empty/wrong/correct + misconfigured + reflection-based `hash_equals` source check. Response shape (4): top-level keys, contract_version literal, six subcheck keys, four-key subcheck shape with valid status. Happy paths (2): all green in test env, `version` mirrors config across both surfaces. Failure paths (2): `DB::shouldReceive('connection')` throws PDOException → red endpoint still 200 with `class_basename` message; same shape for Redis. Rate limit (2): fast source-grep assertion + slow 60-call exercise.
- **Manual testing.** Verified `/api/health` returns 200 with the documented JSON shape end-to-end. `version: dev` confirmed the file-absent fallback path (the local image had not been rebuilt with the build-arg yet; the closure correctly fell back). Rate-limit headers (`X-RateLimit-Limit: 60`, `X-RateLimit-Remaining: 59`) emitted on every response. 401 path with a wrong bearer token returns the documented JSON envelope, not Laravel's HTML error page — confirms the middleware fail-closed branch fires before any unhandled exception path.

**Load-bearing decisions that survived Phase 1:**

- **Bearer token in `Authorization` header, timing-safe `hash_equals` comparison.** Simpler than signed-request schemes; meets v1 needs.
- **Per-install API key, manual env-var bootstrap.** Each CRM install has its own key. Reuse across installs is forbidden by the contract. Automation deferred.
- **Endpoint returns 200 even when subchecks are red.** Fleet Manager always gets structured data to act on. 503 is reserved for a future "endpoint disabled" state, never emitted by v1.
- **Docker build-arg + image-baked VERSION file** (not git-rev-parse-at-boot, not env-var). Build-arg is sourced from `${GITHUB_SHA::7}` in CI; `/var/cache/app/VERSION` lives outside the bind-mount tree so local dev's `.:/var/www/html` doesn't shadow it. Local dev defaults the build-arg to `dev`.
- **Subcheck failure messages carry exception class names only, never `$e->getMessage()`.** Leak risk on unhandled exceptions.
- **`last_backup_at` is green-with-null in v1, not yellow.** "Unknown without a pipeline" is not an alarming state; the Backup Pipeline (Phase 2) flips this to threshold-driven status.
- **Spec doc is the canonical communication surface, not the live response.** The CRM emits the spec; the FM repo consumes it via WebFetch against the canonical raw-GitHub URL. No automatic notification — FM-side discipline is "fetch + diff against local cache on every boundary-touching session."

---

## Forward plan

### Phase 2 — Backup Pipeline (1 session, possibly 2; not yet scheduled)

Greenfield CRM-side backup mechanism. None exists today (verified during Phase 1 prompt drafting — `spatie/laravel-backup` not installed; no `pg_dump` script; no media backup runs). The agent endpoint's `last_backup_at` returns `null` until this lands.

**Scope:**

- **Database backup:** `pg_dump` against `nonprofitcrm_postgres`. Output is a compressed SQL dump. Encryption-at-rest decision deferred to scoping time — likely client-side encryption with a key stored in `.env`, with the encrypted blob shipped to object storage.
- **Media backup:** the Spatie media library tree (`storage/app/public/media-library/` and any disk volumes). Tar + gzip + ship; same encryption posture as the SQL dump.
- **Object storage target:** DigitalOcean Spaces (decided in the planning spec). Bucket-per-install or shared bucket with key-prefixing — decision deferred. Credentials via `.env`.
- **Scheduler cadence:** Laravel scheduler (`schedule:run` in cron). Daily likely; tunable per-install. Failure handling: log, retry with backoff, surface in the next agent poll.
- **Success-record location:** `last_backup_at` reads from a known location — likely a single-row config table or a cache key — written by the backup job on success. The shape needs to align with what the agent endpoint reports.
- **Retention:** client-side (the CRM keeps N days of backups in object storage; old ones are pruned by the same job that wrote them). Fleet Manager observes the most-recent timestamp; it does not orchestrate retention.
- **Threshold update on the agent endpoint:** once the pipeline lands, `checkLastBackupAt` flips from green/null/`'not yet implemented'` to threshold-driven: green if <24h, yellow at 24–36h, red at >36h. This is a contract bump (`1.0.0 → 1.1.0` or possibly `1.0.0 → 2.0.0` if the response shape changes incompatibly — likely 1.1.0 since adding a real value/threshold is additive).

**Cross-repo coordination:** Phase 2 is boundary-touching — the agent endpoint's `last_backup_at` field changes from "always green/null" to "threshold-driven." Spec doc bumps to 1.1.0; CHANGELOG entry; Cross-Repo block in `sessions/session-outlines.md` reflects the new last-boundary-touching session.

**Forcing function:** Phase 2 lands when a second client install is in flight (giving the operator a real reason to want backup observability beyond their own deploy) or when Fleet Manager v1 approaches production launch and the operator decides null-as-unknown is too sloppy. Until then, the contract caveat in the spec doc — *"Fleet Manager treats null as 'unknown — don't alarm'"* — covers the gap operationally.

### Out of scope for this track, by design

- **The Fleet Manager repo itself.** Separate workstream consuming v1.0.0 of this contract. Lives in a different repository; activates after the v1.0.0 spec doc is visible at the canonical raw-GitHub URL (i.e., after session-238/1 merges to main).
- **Public status page.** Lives entirely in the Fleet Manager repo.
- **Multi-channel alerting, performance metrics, remote-action capability.** All deferred per the planning spec to post-v1 of Fleet Manager.
- **SSL expiry CRM-side check.** Planning spec leans Fleet-Manager-side external check — more honest about reachability than CRM self-check (the CRM cannot reliably read its own cert in some failure modes).
- **Custom artisan command + Filament UI for agent key management.** Carry-forwards; land if friction emerges.

---

## Stance

- **Single operator, single product, no multi-tenancy.** Fleet Manager is not client-facing and is not a product. It is internal tooling. The contract surface is correspondingly tight: one key per install, one operator polling, one dashboard.
- **The CRM is the source of truth for the contract; FM is a consumer.** The locality-of-policy-to-surface decision: the side that defines the endpoint owns the spec doc. FM reads via WebFetch; FM never proposes contract changes directly — if FM needs a new subcheck, the request lands as a CRM-side issue and the CRM authors the change.
- **Boundary-touching sessions update the spec doc, the Cross-Repo block, and the CHANGELOG before the next boundary-touching session in either repo.** The protocol is in the planning spec ("Two-Repo Coordination Protocol" section); the prompt template's standing cross-repo flag enforces it on every session in either repo.
- **Operational simplicity beats sophistication for v1.** Manual env-var bootstrap; one key per install; no rotation flow; no admin UI. Each of those is a deferred-but-named carry-forward; landing them requires a forcing function the current scope does not have.
