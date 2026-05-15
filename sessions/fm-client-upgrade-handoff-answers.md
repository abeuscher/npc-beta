# Client-upgrade hand-off — CRM-side canonical answers

Companion artifact authored by the CRM repo (session 292 companion, 2026-05-15) in response to FM's `Client-upgrade — cross-repo coordination (FM → CRM)` hand-off. **Not a contract edit. No agent-contract field, endpoint, or version changes here.** Sources are the canonical CRM code: `.github/workflows/deploy.yml` (the authoritative deploy procedure), `app/Http/Controllers/Api/Fleet/HealthController.php`, `config/fleet.php`, `app/Console/Commands/BuildPublicAssets.php` + `app/Services/AssetBuildService.php`, the migration layout (`database/schema/pgsql-schema.sql` + 6 incremental migrations), and `docs/fleet-manager-agent-contract.md` (governance §, lines 419–422).

The single most load-bearing correction: **FM's premise for Q2 is already out of date.** As of CRM session 291 (current `main`; this companion runs on 292), `GET /api/health` already reports the `0.<session>.<iteration>` pseudo-version, not a git SHA, and the build pipeline already tags GHCR images with that immutable string. 2a and 2b are **shipped today**, not future work. FM can build the strict check now.

---

## 1. Failed-upgrade recovery & migration reversibility

**1a. Is there a supported downgrade path? — No. Migrations are forward-only in operational practice.** The schema is a squashed baseline (`database/schema/pgsql-schema.sql`, loaded by `migrate` on a fresh DB) plus a small rolling set of incremental migrations on top (6 at time of writing). The incremental files do carry `down()` methods, but `migrate:rollback` is **not** a path the CRM maintains, tests, or runs anywhere — the deploy pipeline only ever runs `migrate --force` (forward). Rollback across the squash boundary is impossible by construction. Treat down-migration as unsupported.

**1b. Will the old image run against an already-migrated DB? — No, not guaranteed; do not rely on it.** The `0.` major version is the explicit pre-Beta signal that there is no backward/forward-compatibility guarantee. A migration in an upgrade may add a NOT NULL column, drop/rename a column, or change a type the old code still references. Re-pinning the old image against a forward-migrated schema is undefined behavior pre-Beta. FM must not treat "re-pin old image" as a recovery path once any migration in the batch has applied.

**1c. Is restore-from-pre-upgrade-backup onto a clean node the floor? — Yes, and it is the only guaranteed safe recovery.** This is consistent with the contract's own design intent: v2.3.0 added `/api/backup/blob` precisely so FM can fetch the freshest backup zip; restore itself is **manual `pg_restore` onto a fresh node** (the contract's A2(c) framing — "CRM-side restore primitives: restore stays manual `pg_restore`"). There is no CRM-side automated rollback or restore primitive and none is planned pre-Beta. FM's current design (build no automated rollback; treat restore-from-backup as the floor) is **correct and ratified**.

**1d. Recoverable vs unrecoverable:**
- **Recoverable by re-pinning the old image (no restore needed):** the failure occurs at or before step 4 (image pull / `up --wait`), i.e. **`migrate --force` has not been invoked at all**. Nothing in the DB changed; re-pin the previous `IMAGE_TAG`, `up -d --wait`, done.
- **Unrecoverable in place — restore from the pre-upgrade backup, do not attempt repair:** `migrate --force` was invoked and **any** migration in the batch began applying. Laravel wraps each migration file in its own transaction but does **not** wrap the whole batch — so a multi-file batch can be left partially applied (migrations 1–3 committed, 4 failed). A partially- or fully-applied batch with the upgrade aborted is restore-from-backup territory. There is no safe in-place repair drill.

**Decision tree for FM's runbook:** fail before `migrate` invoked → re-pin old image. `migrate` invoked at all (success or partial) and the upgrade is being aborted → restore the pre-upgrade backup onto a fresh node via manual `pg_restore`. There is no third branch.

---

## 2. Version identifier semantics

**Scheme: ratified as written.** `0.<session>.<iteration>`, `<session>`/`<iteration>` = the CRM repo's session number and `/N` iteration at merge to `main`, `0.` major fixed pre-Beta, valid semver, FM treats it as opaque-but-orderable. This is exactly what the CRM already implements (`deploy.yml` validates the `VERSION` file against `^0\.[0-9]+\.[0-9]+$`; current value on `main` is `0.292.1`). No amendment.

**2a. Immutable published GHCR tag, `latest` separate moving pointer — confirmed, shipped (CRM s291).** `deploy.yml`'s "Enforce immutable version tag" step runs `docker manifest inspect` on the target tag and **fails the build** if it already exists ("Version tags are immutable — bump VERSION before merging"). The image is pushed as both `nonprofitcrm-app:<VERSION>` and `nonprofitcrm-app:latest`; `latest` moves, the version tag never does and is never the upgrade target.

**2b. Running app reports the exact same string on `/api/health` — confirmed, shipped (CRM s291).** `deploy.yml` bakes `APP_VERSION=<VERSION>` into the image; `config/fleet.php` resolves `fleet.agent.app_version` from the baked `/var/cache/app/VERSION` file (falling back to the literal `dev` for local/unstamped builds); `HealthController` returns that value verbatim as the top-level `version` field. So `health.version === pinned image tag` holds exactly on any image built by the pipeline. FM can assert `running == requested tag` and render a true tag→tag before/after **now**.

**2c. Is `version` the git SHA today? — No. It is already the pseudo-version, as of CRM session 291.** FM's premise is stale. There is **no transition to wait for**: 2a and 2b are in place on current `main`. The only residue is documentation prose — `docs/fleet-manager-agent-contract.md` still contains the literal phrase "seven-character git SHA" inside a 291 changelog *explanation of what was corrected* (a CRM-side doc-hygiene cleanup, separately flagged on the CRM side; it does not affect runtime behavior or the contract surface). **FM should build the strict check (`version == expected tag`) directly; the loose "version changed" fallback is unnecessary against any pipeline-built image.** (A locally-built/unstamped image reports `dev` — only relevant if a client somehow runs an unstamped image, which the pipeline prevents.)

---

## 3. Maintenance mode

**3a. Is a maintenance mode engaged around an upgrade? — No.** Laravel's built-in `php artisan down`/`up` exists in the codebase (it is a stock Laravel app), but it is **not** part of the upgrade procedure and the CRM does not engage it anywhere in deploy.

**3b. n/a** (no maintenance mode in the procedure).

**3c. Running the migrate + asset-rebuild window without maintenance mode is acceptable pre-Beta — ratified.** This is not a concession; it is exactly what the canonical production deploy does. `deploy.yml`'s on-droplet script runs, in order, with the container live the whole time: `compose pull` → `up -d --wait` → `migrate --force` → `storage:link` → `view:clear` → `config:clear` → `build:public`. No `down`/`up` bracketing. The brief mid-change window at near-zero pre-Beta stakes is the accepted, sanctioned posture. FM's current design (no maintenance mode, accept the window) **matches the canonical deploy** — keep it. If FM ever wants belt-and-suspenders it may `php artisan down` before step 4 and `php artisan up` after step 8, in which case `php artisan up` becomes a mandatory step in every abort path — but this is optional and the CRM does not do it.

---

## 4. Asset rebuild — the two-build process, re-run safety, failure surface

**4a. One command, not two — and only one of the "two builds" is FM's to run.** There genuinely are two asset surfaces, but they are produced at different times:
- **Admin/app front-end (Vite) bundle** — compiled at Docker **image build time** (in the image build, manifest at `public/build/manifest.json`). It is **baked into the image** and arrives with `docker compose pull` in step 3/4. **FM runs no command for this**; it ships in the image.
- **Public widget bundle** — compiled at **runtime** by `php artisan build:public` (a single artisan entrypoint → `AssetBuildService`, which calls the remote build server and writes `public/build/widgets/{public-widgets-<hash>.css,.js}` + `public/build/widgets/manifest.json`). **This is the only asset command FM runs**, and it is the single command `php artisan build:public`. No second command, no ordering between two commands.

So: step 7 is exactly `php artisan build:public`, run once. The "two-build" framing in the FM thread is accurate as a description of the system but does **not** translate to two FM steps.

**4b. Idempotent / safe to re-run — yes.** Output filenames are a content hash of the collected sources, so re-running with unchanged sources reproduces identical filenames and rewrites the manifest; superseded bundles are pruned by the service. Re-running `build:public` from the top on a retry is safe and is exactly what the canonical `deploy.yml` does (`build:public || true`, non-fatal, re-runnable).

**4c. A failed/partial build leaves the site serving the OLD assets, not broken ones.** `AssetBuildService` only writes the new bundles and the manifest **after** the remote build returns success with content; every failure path (build server unreachable, non-2xx, `success:false`, empty/undecodable content) returns early **before any file or manifest write**. The previous hashed bundles and the previous `manifest.json` remain in place untouched, and the app keeps referencing them. There is no half-written manifest and no missing-asset window. A failed step 7 is therefore non-destructive and retry-safe.

---

## 5. A verification fingerprint FM can check

**5a. `/api/health` exposes no asset/build fingerprint.** Its subchecks are `app`, `database`, `redis`, `disk`, `last_backup_at`, `version`. `version` fingerprints the running **image** (good for proving the new code is live — see Q2) but says nothing about the asset rebuild specifically.

**5b. Use the build manifests over HTTPS — both are stable, web-served artifacts.**
- **Public widget rebuild (step 7's actual output):** `GET /build/widgets/manifest.json`. JSON containing `css`/`js` (content-hashed filenames), `libs`, and a `built_at` ISO-8601 timestamp. This is the **direct, purpose-built fingerprint** for step 7: capture it before, capture it after; the rebuild succeeded iff `built_at` advanced and/or the `css`/`js` hashed filenames changed. (If sources were identical the filenames legitimately won't change — `built_at` advancing is the reliable "the build actually ran and succeeded" signal; absence of change after a failure is consistent with 4c.)
- **Admin/app (Vite) bundle, ships in the image:** `GET /build/manifest.json` — the Vite manifest; its hashed entries change whenever the image's front-end changed. Compare before/after the image swap if FM wants to fingerprint that surface too.

Both paths are under the `public/` web root, so reachable over the same HTTPS the instance already serves. FM's proposed fallback (fetch rendered admin + front-end pages and assert the referenced bundles resolve 200) is also sound as a liveness check, but the manifests are the more stable, lighter, and more precise artifact — prefer them. The single most stable thing to watch for step 7 is `built_at` in `/build/widgets/manifest.json`.

---

## 6. Step-ordering constraints

**Order is confirmed safe — it is exactly the canonical CRM production deploy order.** `deploy.yml` on-droplet: pull → `up -d --wait` (new image, gated on container health) → `migrate --force` → `storage:link` → `view:clear` → `config:clear` → `build:public`.

**6a. New image up, then migrate — safe, pre-Beta.** The new image briefly serving against the old schema (the gap between `up --wait` and `migrate` completing) is exactly what the canonical deploy does and is accepted at pre-Beta stakes. There is no requirement that migrations run before the new code serves traffic.

**6b. Migrate before asset rebuild.** Assets do not depend on schema, but the canonical order is `migrate` then `build:public`; follow it. Also: run `view:clear` + `config:clear` between `migrate` and `build:public` (the canonical deploy does), so the swapped code doesn't serve stale cached views/config.

**6c. One thing FM's procedure omits: `php artisan storage:link`.** The canonical deploy runs it after `migrate` (idempotent; ensures the public storage symlink exists). FM's step list (1–8) does not mention it. Recommend FM add `storage:link` in the same step group as `view:clear`/`config:clear` (after migrate, before `build:public`). It is safe to run every upgrade. Otherwise FM's order matches the sanctioned procedure exactly.

---

## 7. Contract-version changes across an upgrade

**7a. Yes — an upgrade can change the reported `contract_version`, and that is normal pre-Beta.** Confirmed.

**7b. FM's success bar is correct, with the major-bump caveat exactly as FM states it.** Per the contract's own governance (`docs/fleet-manager-agent-contract.md` §, lines 419–422): FM stays forward-compatible **within a major** (a FM that understands `2.0` can poll any `2.X`, X>0 — new fields are additive within a major); a **major bump (e.g. `3.0`) is breaking and is handled out-of-band — the FM build is updated to the new shape before that contract version ever reaches an install.** So: post-upgrade success = `/api/health` reports `status: green`/healthy **and** `version` changed to the expected tag (Q2). A minor/patch `contract_version` change is **logged, not failed on**. A major `contract_version` change should never be observed post-upgrade in the first place under correct governance (FM is updated first); if FM ever does observe an unexpected major bump it is a governance violation and a legitimate hard failure/alert, not a normal upgrade outcome. FM is not missing a constraint.

---

## Answer log

| Q | Answer (decisive) | Decided by | Date |
|---|---|---|---|
| 1a | No supported downgrade path. Squashed-baseline + incremental migrations; forward-only in practice; `migrate:rollback` unsupported/untested/never run. | CRM (s292 companion) | 2026-05-15 |
| 1b | No — not guaranteed; pre-Beta has no back/forward-compat guarantee. Do not re-pin old image once any migration applied. | CRM (s292 companion) | 2026-05-15 |
| 1c | Yes — restore pre-upgrade backup onto a fresh node via manual `pg_restore` is the floor and the only guaranteed safe recovery. No CRM automated rollback/restore primitive, none planned pre-Beta. | CRM (s292 companion) | 2026-05-15 |
| 1d | Recoverable by re-pin only if `migrate` was never invoked. Any migration applied (full or partial — batch is not atomic) → restore from backup; no in-place repair. | CRM (s292 companion) | 2026-05-15 |
| 2 (scheme) | Ratified as written, unamended — it is what the CRM already implements. | CRM (s292 companion) | 2026-05-15 |
| 2a | Confirmed — shipped CRM s291. Immutable version tag enforced in `deploy.yml` (`docker manifest inspect` guard); `latest` is a separate moving pointer, never the target. | CRM (s292 companion) | 2026-05-15 |
| 2b | Confirmed — shipped CRM s291. `APP_VERSION` baked → `config/fleet.php` → `/api/health` `version` reports the exact tag string. | CRM (s292 companion) | 2026-05-15 |
| 2c | `version` is **already the pseudo-version, not the git SHA** (since s291). No transition to wait for; FM should build the strict `version == expected tag` check now. Only residue is a stale doc phrase in the contract changelog (CRM doc-hygiene, separately flagged; no runtime/contract effect). | CRM (s292 companion) | 2026-05-15 |
| 3 | No maintenance mode in the procedure (3a: no). Running the migrate+asset window without one is ratified — it is exactly what the canonical production deploy does (3c). Optional belt-and-suspenders `down`/`up` allowed but then `up` is mandatory in every abort path (3b). | CRM (s292 companion) | 2026-05-15 |
| 4 | One command: `php artisan build:public`, run once (4a) — rebuilds only the public widget bundle; the admin/Vite bundle is baked into the image and arrives via the pull, no FM command. Idempotent/re-run-safe (4b). A failed/partial build is non-destructive: it writes nothing and the site keeps serving the previous assets (4c). | CRM (s292 companion) | 2026-05-15 |
| 5 | No fingerprint on `/api/health`. Use `GET /build/widgets/manifest.json` (`built_at` + hashed `css`/`js`) as the step-7 before/after fingerprint; `GET /build/manifest.json` for the image's Vite bundle. FM's page-fetch fallback is sound but the manifests are preferred; watch `built_at`. | CRM (s292 companion) | 2026-05-15 |
| 6 | Order confirmed safe — it is the canonical deploy order (image up → migrate → cache clears → build:public). New image vs old schema gap is accepted pre-Beta (6a). Migrate before asset rebuild, with `view:clear`/`config:clear` between (6b). FM's procedure omits `php artisan storage:link` — add it after migrate, before build:public; idempotent (6c). | CRM (s292 companion) | 2026-05-15 |
| 7 | 7a confirmed (a `contract_version` change across an upgrade is normal pre-Beta). 7b confirmed: success = healthy + version changed; minor/patch `contract_version` change logged not failed; a major bump is governance-handled (FM updated before the image reaches any install) and should never be observed post-upgrade — if observed, that is a hard alert, not a normal outcome. | CRM (s292 companion) | 2026-05-15 |
