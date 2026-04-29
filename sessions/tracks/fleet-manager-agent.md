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

**Last update:** 2026-04-28 (Phase 2 Backup Pipeline landed at session 242; track substantially complete).

**Complete:** Phase 1 — CRM-Side MVP + v1.0.0 Contract (session 238). Sub-phase: v1.1.0 contract bump introducing `unknown` subcheck-level status (session 240). Phase 2 — Backup Pipeline + v1.2.0 contract bump (session 242).

**Active:** none. Track substantially complete; Phase 2 closed at session 242. Carry-forwards listed below remain unscheduled.

**Cross-repo coordination state:**

- Agent contract version: `1.2.0` (live as of session 242).
- Spec doc: [`docs/fleet-manager-agent-contract.md`](../../docs/fleet-manager-agent-contract.md). Canonical URL for FM-repo `WebFetch`: `https://raw.githubusercontent.com/abeuscher/npc-beta/main/docs/fleet-manager-agent-contract.md`.
- Cross-Repo block in `sessions/session-outlines.md` (top of file, just below Active tracks) reflects v1.2.0 and last-boundary-touching session 242.
- FM-side cached copy at `/home/al/fleetmanager/docs/imported/fleet-manager-agent-contract.md` still reads v1.1.0 as of 242 close — refreshes on FM's next boundary-touching session per the Two-Repo Coordination Protocol; `StatusInterpreter` aligns to the new threshold-driven shape at the same time.
- The standing cross-repo flag in every session base prompt remains active — it fires whenever a session modifies `/api/health`, the auth middleware, the response schema, the VERSION pipeline, or anything in `app/Http/Controllers/Api/Fleet/*`.

**Carry-forwards (none scheduled):**

- A custom artisan command for generating the agent key (`php artisan fleet:generate-agent-key`) — operator currently uses `Str::random(64)` or `php -r 'echo bin2hex(random_bytes(32));'` directly. Lands if friction emerges.
- A Filament admin UI for managing the agent key — out of scope by design; key lives in `.env`.
- Multi-tenant API key support (one CRM serving multiple FM instances) — explicitly forbidden by the v1.0.0 contract. If multi-FM scenarios emerge, the surface change is additive (an array of accepted keys) and warrants a contract bump.
- Key rotation flow — manual replacement on the droplet for v1; automation deferred.
- Disk subcheck automated failure-path test — `disk_free_space` mocking requires scaffolding beyond v1 needs; integration-tested in dev only.
- `BackupHasFailed` event listener — failure is observed via timestamp aging into `yellow` then `red`; active failure-alerting (email, Slack) is a future concern.
- Backup-restore tooling — manual operator op for v1 (`pg_restore` + tarball-extract against a downloaded blob); not a click-button feature.
- Per-install retention configuration beyond the 14-day default — lands when a client has a different requirement.
- Scheduler runner on the worker container — the `worker` service in both `docker-compose.yml` and `docker-compose.prod.yml` runs `php artisan queue:work` only; no `schedule:work` or cron-driven `schedule:run` is in place. The 242 wiring registers `backup:clean` and `backup:run` in `bootstrap/app.php`'s `withSchedule()` block so they appear in `schedule:list`, but they will not fire automatically until a scheduler runner is added (separate worker variant, sidecar container, or host-level cron). Manual `php artisan backup:run` works today; production deployment requires the runner to be in place before the daily cadence takes effect.

---

## Phase Retrospectives

**Phase 2 — Backup Pipeline (session 242).** Greenfield CRM-side backup mechanism shipped. `spatie/laravel-backup` ^9.0 added to `require` (not require-dev — runs in production); `league/flysystem-aws-s3-v3` ^3.0 added alongside to make the s3-driver `spaces` disk usable. `pg_dump` + media library tarball uploaded daily to a per-install DigitalOcean Spaces bucket (one bucket per install; bucket-scoped Spaces access keys via `SPACES_KEY` / `SPACES_SECRET` / `SPACES_BUCKET` / `SPACES_REGION` / `SPACES_ENDPOINT`). Success record at `storage/app/fleet/last-backup-at` (flat file with ISO 8601 timestamp, written by `App\Listeners\RecordBackupSuccess` listening to `Spatie\Backup\Events\BackupWasSuccessful` via Laravel 11 type-hint auto-discovery — no `EventServiceProvider` needed). `HealthController::checkLastBackupAt()` flipped from hardcoded `unknown` to threshold-driven (`green` < 24h, `yellow` 24–36h, `red` > 36h, `unknown` if no successful run yet — file missing, empty, or unparseable, each with a distinct `message`). Contract bumped 1.1.0 → 1.2.0 (additive — `value`/`threshold` shapes filled, `unknown` semantic refined). Operator setup procedure documented in `docs/app-reference.md`. Local dev runs to local disk only via `BACKUP_DISKS=local` env var; production sets `BACKUP_DISKS=spaces`.

Load-bearing decisions: `spatie/laravel-backup` over rolling our own (battle-tested + same author family as other Spatie deps); server-side encryption via Spaces SSE (over client-side encryption from the planning spec — sufficient for v1; revisit on regulatory driver); flat file at `storage/app/fleet/last-backup-at` (over Redis cache key — survives Redis outages, no extra dependency, atomic via `Storage::disk('local')->put`); bucket-per-install (over shared bucket with prefix isolation — DO Spaces scoped keys are bucket-level not prefix-level, so per-bucket gives real isolation at zero marginal cost since DO Spaces is pooled-storage-priced).

Surfaced gap: the `worker` service in both `docker-compose.yml` and `docker-compose.prod.yml` runs `php artisan queue:work` only; nothing runs the scheduler. The 242 wiring registers the daily backup commands but they will not fire automatically until a scheduler runner is added (a separate worker variant, sidecar, or host-level cron). Documented in carry-forwards rather than fixed in scope per the session prompt's "do not implement scheduler-runner infrastructure in this session" rule. Manual `php artisan backup:run` works today and is the manual-test path.

Carry-forwards: backup-restore tooling (deferred — high-stakes manual op for v1, not a click-button feature); `BackupHasFailed` event listener (deferred — failure is observed via timestamp aging into yellow then red, that is the intended observation path); per-install retention tuning beyond the 14-day default (deferred until a client has a different requirement); scheduler runner on the worker container (named gap above).

**v1.1.0 contract bump — `unknown` status for `last_backup_at` (session 240).** Sub-phase under Phase 1; not a new full phase (Phase 2 still owns the eventual threshold-driven flip when the Backup Pipeline lands). The FM workstream flagged that `last_backup_at: { status: "green", value: null }` is semantically wrong — null means "we don't know," not "things are fine." Bump landed as additive `1.0.0 → 1.1.0`: introduced `unknown` as a fourth valid subcheck-level status, flipped `HealthController::checkLastBackupAt()` from `green` to `unknown`, and expanded the worst-of derivation so `unknown` ranks equivalent to `yellow` (top-level `status` enum stays `{green, yellow, red}` — `unknown` never propagates upward by construction). Spec doc body + JSON example + worst-of rule + subcheck table updated; CHANGELOG gained the v1.1.0 entry with the explicit forward-compat note for v1.0.0 consumers. Tests: three existing cases adjusted (contract_version literal, allowed-status set, healthy-env top-level expectation), four new cases added (literal-shape pinning, only-green-and-unknown→yellow, red-overrides-unknown→red, reflection-driven worst-of equivalence). Fast Pest 1674 → 1678 (+4 net). Sequencing rationale: landed v1.1.0 *before* the FM repo's next boundary-touching session (FM 004 — CRM Integration — Health Polling Skeleton + HTTP Client) so the FM contract validator builds against the final shape with no retrofit. Load-bearing decision: `unknown ≡ yellow` ranking is a v1.1.0 lean — operational experience may show missing data should rank above or below yellow; flagged in the spec doc for future revisit.

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
