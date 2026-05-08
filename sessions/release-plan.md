# Pre-Beta-1 Release Plan

The vetted set of sessions, rehearsals, and operational gate items between today and Beta-1 release. Produced at session 244 from `release-plan-outline.md` (now retired). This doc is the single source of truth for sequencing, success criteria, prerequisites, and artifacts. Each session in the plan reads it at start and updates it on close.

---

## How this doc is used

Every session that lands inside this plan reads the relevant working-set entry at start to know its prerequisites, success criterion, and required artifact — then writes its session prompt against that entry. The session does not relitigate scope inside its own prompt; the plan is canonical.

When a session closes, a checkmark lands on its working-set entry. The plan is closed when every entry carries a checkmark.

The 11 discipline rules below govern how sessions interact with the plan. Sessions that drift from the rules surface the drift to the user before continuing.

---

## Discipline rules

1. **Stub-then-rehearsal discipline.** When a rehearsal has a prerequisite stub, the stub ships first as its own session. The rehearsal session does NOT do feature-implementation work — it exercises an existing surface and writes the runbook.
2. **Audit-style absorption.** Audit-style rehearsals (C3 Permission audit, D3 Integration retest) MAY absorb small in-session fixes discovered during the audit. Feature-style rehearsals MUST NOT.
3. **Success criterion is the gate.** A session closes only when its written success criterion is met. If new evidence requires iterating the criterion, re-sign-off with the user before closing — do not adjust the criterion mid-session to fit what was actually achieved.
4. **Per-rehearsal artifact required.** Each rehearsal lands a runbook / sizing doc / playbook / matrix in a documented location (typically `docs/runbooks/` or referenced in `docs/app-reference.md`). "Green check" alone is not a close condition.
5. **Plan doc is source of truth.** Every session in the plan reads `release-plan.md` at start to know its prerequisites + success criterion + artifact location. Drift between session prompt and plan doc resolves against the plan.
6. **Plan doc is append-only in flight.** Checkmarks land on close. The plan doc is not edited mid-session except to record findings or surface a needed prerequisite that wasn't captured.
7. **Track A blocks Track B/C/D execution.** Operational foundations (Random Data Generator, Fleet Manager node ops, multi-node provisioning, Capsize runbook polish, 2FA) must be in place before rehearsals run. Rehearsals don't have a meaningful environment without them.
8. **Compatibility runs last.** D2 always runs against the surface as it'll ship. Items in Track E that affect mobile / typography / theme / column collapse must close before D2 starts.
9. **Integration retest runs absolutely last.** D3 runs against a near-final surface — it's the final tire-kicking pass before the terminal session.
10. **Code Review + Migration Squash is terminal.** T1 is the final session before Beta-1 release. Every other entry must close first.
11. **Session count is always flexible.** A session that surfaces unforeseen work splits into multiple sessions rather than overloading a single context window. The plan doc tracks the *work*, not the session count. When a session splits, update the execution-order list to reflect the new shape — do not compress work to hit a target count.

---

## Pre-release requirements register (non-session gate items)

Items that must be live before Beta-1 release but are tracked outside the session pipeline:

- **Privacy policy live on marketing site** — drafts in process with counsel.
- **Terms of Service live on marketing site** — drafts in process with counsel.
- **Operator master runbook / SOPs** — DEFERRED DECISION: TBD whether this lives in this project or a separate non-technical project. Revisit before Beta-1 release.

---

## Working set

Each entry carries: gate, prerequisites, success criterion, artifact, estimated time cost. All entries are gated on `release` only — investment-gate subset selection is a follow-up conversation against this doc; the structural slot exists per Rule 6 if/when it's wanted.

### Track A — Operational foundations

#### A1. Random Data Generator as Dashboard Widget ✅

- **gate:** release
- **prerequisites:** none
- **success criterion** *(corrected at 245 close — agreed Rule-6 carve-out)*: A super-admin-gated **contract widget** in the `dashboard_grid` slot that **generates AND wipes** synthetic CRM data — contacts / donations / events / registrations / memberships / blog posts / products in configurable counts, plus a Seed Widget Collections action. Generated rows tag `source = 'scrub_data'` (new value alongside the existing `Source::DEMO`); a new `EnforcesScrubInheritance` trait makes the source infectious downward through FK relationships so any row created in relationship to scrub data is itself scrub-tagged. Source-scoped wipe removes the entire scrub subgraph cleanly. Custom fields on contacts respect declared types when seeded. Per-action confirmation step prevents accidental clicks. The existing `APP_DEBUG_TOOLS`-flagged debug generator is retired.
- **artifact:** the widget itself; downstream rehearsals can lean on it for synthetic data.
- **estimated time cost:** 1 session. **Closed at session 245.** Lifted in-session: `events.source` column, `pages.source` column (pre-existing inconsistency), `products.source` column, blog post generation, seed-widget-collections, image attachments via Spatie Media Library, generation variability. See `sessions/245. Random Data Generator as Dashboard Widget — Log.md` for the full landing.

#### A1b. Fleet Manager Contract v2.0.0 — mTLS Migration ✅

- **gate:** release
- **prerequisites:** none (CRM-side authoring; FM-side absorbs after)
- **success criterion** *(closed at session 248)*: Auth handshake swaps from bearer token at the application layer to mTLS at the TLS layer. Nginx terminates the handshake; the application has no auth code path on `/api/health` after this session. Non-additive bump (v1.2.0 → v2.0.0); the bearer path retires in the same cutover. Pre-Beta-1 scope-correct because there are no live clients.
- **artifact:** `docs/fleet-manager-agent-contract.md` at v2.0.0; operator cert-paste runbook at `docs/runbooks/fleet-manager-cert-paste.md`. **Closed at session 248.** See `sessions/248. Fleet Manager Contract v2.0.0 — mTLS Migration — Log.md` for the full landing.

#### A1c. Fleet Manager Compromise Recovery Infrastructure ✅

- **gate:** release
- **prerequisites:** A1b (CRM-side v2.0.0 mTLS surface shipped at session 248)
- **success criterion** *(closed at session 253)*: Operator-facing tooling and documentation for break-glass recovery from FM compromise — the case where the per-install trust-one-cert mTLS model needs an operator-driven cert swap across every CRM in the fleet. Three artifacts: (a) operator-facing rotation script at `bin/rotate-fm-cert.sh` (validates input PEM via `openssl x509`, atomic `mv` into `/opt/nonprofitcrm/nginx-certs/fm-client.crt`, `nginx -s reload`, host-side audit log at `/opt/nonprofitcrm/logs/fm-cert-rotations.log`); (b) compromise-recovery runbook at `docs/runbooks/fm-compromise-recovery.md` (pre-installation break-glass cert generation with cold-storage discipline + at-recovery-time per-CRM cert swap procedure + post-recovery cold-storage invariant restore); (c) additive Security Posture sub-section "Recovery posture and FM-side trust assumptions" in `docs/fleet-manager-agent-contract.md` naming the three trust-model properties (break-glass recovery path; FM's off-filesystem-key posture; audit-sink discipline) under a section-header rule labeling each item shipped vs FM-side intended-posture. Documentation revision under v2.1.0; no contract bump.
- **artifact:** the script + runbook + spec doc revision. **Closed at session 253.** See `sessions/253. Fleet Manager Pivot Planning Session — Log.md` for the full landing.

#### A1d. Fleet Manager Contract v2.2.0 — Backup Trigger Endpoint ✅

- **gate:** release
- **prerequisites:** A1b (CRM-side v2.0.0 mTLS surface shipped at session 248); existing CRM-side backup infrastructure (spatie/laravel-backup config + `RecordBackupSuccess` listener + `last-backup-at` success-record file from CRM 242).
- **success criterion** *(closed at session 263)*: Single mTLS-gated HTTP endpoint `POST /api/backup/trigger` shipped — synchronously runs `Artisan::call('backup:run')` and returns a JSON envelope `{contract_version, status, last_backup_at, duration_ms, message}`. Additive contract bump v2.1.0 → v2.2.0 (v2.1.0 consumers continue working unchanged). Endpoint behaviors: throttle `6,1` per source IP; `set_time_limit(600)` + per-location `fastcgi_read_timeout 600;` for 10-minute backup ceiling. `request_terminate_timeout` finding: not set anywhere in the repo (no `www.conf` override, no Dockerfile injection); upstream `php:8.4-fpm` defaults to `0` (no PHP-FPM-imposed ceiling), so the 600s wall is enforced by nginx + `set_time_limit(600)` only — no bump needed. Same nginx mTLS gate pattern as `/api/health` and `/api/logs`, applied to both `docker/nginx/default.conf` and `docker/nginx/prod.conf` for parity. **Success-record mtime cross-check (the integrity guard, the regression-guard for the misleading-success class of bug):** when `Artisan::call` returns 0, the controller cross-checks `storage/app/private/fleet/last-backup-at` against the request start time; if the recorded timestamp is null OR older than start, downgrades to `status: failed` with `"backup:run exited cleanly but success record was not updated"` — protects FM-side from showing a misleading success panel when the listener silently failed. Response always HTTP 200 with status-discriminated envelope (mirrors `/api/health`'s pattern). Error-message sanitisation: strip absolute application-root prefix only (app-root-relative paths kept per open-source-app threat model); newline-collapse to ` | `; cap at 500 chars with trailing `…` if longer; no stack traces. Two error sources feed the pipeline: `Artisan::output()` on non-zero exit, `\Throwable::getMessage()` on caught exception. `HealthController::CONTRACT_VERSION` also bumped 2.1.0 → 2.2.0 (the canonical field FM polls). Spec doc updated with new endpoint section + CHANGELOG entry above v2.1.0. Cross-Repo block in `session-outlines.md` bumped. **Finding (in-session):** GET on a POST-only route returns 404, not 405, in this codebase — Laravel falls through to the public-site page-slug catchall in `web.php`; spec doc reflects this for FM consumers (operative invariant is "the backup pipeline does not run for non-POST requests," not a specific status code). Unblocks FM session 020. See `sessions/263. Fleet Manager Contract v2.2.0 — Backup Trigger Endpoint — Log.md`.
- **artifact:** the new endpoint + controller + spec-doc revision; the mtime cross-check integrity guard as the regression-guard for the misleading-success class of bug.
- **estimated time cost:** 1 session. **Closed at session 263.**

#### A1d'. Backup notification hardening — FM 020 finding ✅ *(A1d follow-on)*

- **gate:** release
- **prerequisites:** A1d closed at session 263 (the backup-trigger endpoint surface this hardens against). FM 020 manual testing on 2026-05-05 surfaced the structural fragility this entry closes.
- **success criterion** *(closed at session 266)*: Spatie's mail-channel notification dropped on the success path (`BackupWasSuccessfulNotification` / `HealthyBackupWasFoundNotification` / `CleanupWasSuccessfulNotification` flipped from `['mail']` to `[]`) so it can no longer kneecap `RecordBackupSuccess` via listener-chain throw propagation. Failure-class notifications (`BackupHasFailedNotification` / `UnhealthyBackupWasFoundNotification` / `CleanupHasFailedNotification`) kept `['mail']` as the FM-down redundancy layer. `AppServiceProvider::boot()` gained three sibling overrides bridging `SiteSetting` into spatie's parallel `backup.notifications.mail.from.address` / `from.name` / `to` config keys (eliminated the `'your@example.com'` placeholder; uses the same SiteSetting-driven values as the rest of the app's mail). **Verify-at-start finding:** spatie's mail path reads `app(Spatie\Backup\Config\Config::class)`, not `config('backup.notifications.mail.*')` directly — but the binding is a `scoped` lazy singleton (`BackupServiceProvider::packageRegistered()`) built on first resolution at notification-dispatch time, well after `AppServiceProvider::boot()`, so runtime overrides do propagate. Phase 3 test #3 carries a `forgetInstance` + `app(SpatieBackupConfig::class)` assertion to prove the bridge reaches spatie's actual read path. No agent contract change; no schema change; no FM-side change. Manual deploy-side verification confirmed — FM 020's "Trigger backup now" against beuscher.net now returns a success envelope with the integrity guard no longer firing on the success path. See `sessions/266. Backup notification hardening — FM 020 finding — Log.md`.
- **artifact:** the config + service-provider edits + regression-guard tests against re-introducing either fragility (success-channel mail OR unbridged from-address).
- **estimated time cost:** 1 session, small. **Closed at session 266.**

#### A1e. Fleet Manager Contract v2.3.0 — Backup Blob Download Endpoint ✅

- **gate:** release
- **prerequisites:** A1d closed at session 263 (this session extends the FM-agent endpoint set with the same nginx mTLS gate pattern). Spatie backup pipeline producing zips on `Storage::disk('local')` per existing `config/backup.php` (CRM 242). FM-side absorption is two sessions on the FM repo (FM 021 + FM 022), unblocked by this session's contract surface.
- **success criterion** *(closed at session 268)*: New mTLS-gated HTTP endpoint `GET /api/backup/blob` shipped — streams the freshest backup zip from the resolved source disk to the FM caller. Additive contract bump v2.2.0 → v2.3.0 (v2.2.0 consumers continue working unchanged). Endpoint behaviors: same nginx mTLS gate as the other three FM endpoints applied to both `docker/nginx/default.conf` and `docker/nginx/prod.conf`; per-location `fastcgi_read_timeout 600;` mirroring the trigger ceiling; `throttle:60,1` matching `/api/logs`; success response streams zip bytes with `Content-Type: application/zip`, `Content-Disposition: attachment; filename="<spatie-blob-filename>"`, `Content-Length: <bytes>`, `Cache-Control: no-store` (Laravel API middleware appends `private`, harmless). Streaming via `Storage::disk($d)->download($newest->path(), basename($newest->path()), [...])` — Laravel's wrapper auto-sets length + disposition; the explicit Content-Type override pins `application/zip`. **Disk-fallback rule, two layers, both load-bearing:** Layer A preference moves `local` to the front of `BACKUP_DISKS` regardless of authored position if it's present; Layer B falls through on empty (`->newest()` null) across the resolved order. The blob controller is a sibling method on `BackupController` (`blob()` next to `trigger()`), reusing the existing `sanitise()` helper and `MAX_MESSAGE_LENGTH` constant; no new controller class. **404 `no_backup_available`** envelope when all configured disks are empty (recoverable; FM triggers + retries). **500 `backup_destinations_not_configured`** envelope when `BACKUP_DISKS` resolves to an empty list (operator must set the env var). **500 `backup_disk_error`** envelope for synchronous storage exceptions (sanitised single-line message via the existing pipeline). 404 / 500 envelopes match `/api/logs`' shape — no `contract_version` field on errors; cross-endpoint addition of contract_version to error envelopes deferred. `HealthController::CONTRACT_VERSION` and `BackupController::CONTRACT_VERSION` both bumped 2.2.0 → 2.3.0. Spec doc gained new endpoint section + status-code semantics table + CHANGELOG entry above v2.2.0; Recovery posture sub-section gained a v2.3.0 note framing the new endpoint as the FM-readable half of the restore primitive. Cross-Repo block bumped (last boundary-touching → 268; FM-side absorption pending at FM 021 `BackupBlobClient` + FM 022 restore affordance). **Finding (in-session):** spatie's actual filename format is `Y-m-d-H-i-s.zip` (no `<backup-name>-` prefix); the session prompt's `nonprofitcrm-…` example was wrong. `<backup_name>` is a directory inside the disk; `basename()` strips it. Spec doc + CHANGELOG explicit so FM consumers don't expect the prefix. **Test pattern resolved:** `Storage::fake('local')->put('<backup_name>/<Y-m-d-H-i-s>.zip', $bytes)` registers as a recognized backup because spatie's `isZipFile` returns true on `.zip` extension alone (no body read); 11 fast tests + 1 slow test cover success / fallback / 404 / 500 / method enforcement / throttle. **Out of scope at v2.3.0:** historical blob enumeration; per-disk targeting via query; progress streaming; Range / resume; CRM-side restore primitives. Manual curl smoke test against a real 4.5GB dev backup blob confirmed the streaming pipeline (Content-Length match, Content-Disposition correct, Cache-Control present). Unblocks FM 021 + FM 022. See `sessions/268. Fleet Manager Contract v2.3.0 — Backup Blob Download Endpoint — Log.md`.
- **artifact:** the new endpoint + sibling-method controller + spec-doc revision + Cross-Repo block bump; FM-side absorption pending at FM 021 + 022.
- **estimated time cost:** 1 session. **Closed at session 268.**

#### A2. Fleet Manager — node operations parity

- **gate:** release
- **prerequisites:** A1b (CRM-side v2.0.0 mTLS migration shipped at session 248); A1d (CRM-side v2.2.0 backup-trigger endpoint — prerequisite for A2(b)); FM-side absorption at FM session 012 must complete before FM 013+ A2 affordance work begins.
- **success criterion:** From the FM admin UI, an operator can (a) provision a new CRM node from a clean droplet end-to-end *(FM-side; shipped at FM session 018)*, (b) trigger and verify a backup against a node *(CRM-side shipped via A1d at session 263; FM-side absorption shipped at FM session 020 pending manual-test sign-off)*, (c) restore a node from a backup blob *(restore-to-fresh-node primitive: CRM-side blob endpoint shipped via A1e at session 268; FM-side absorption pending FM 021 + 022; same-node click-restore stays explicitly out of scope per FM `session-outlines.md` and remains a manual `pg_restore` op)*, (d) fetch and surface application logs from a node without operator SSH *(CRM-side via session 251 v2.1.0; FM-side absorption shipped at FM 013)*. Each capability documented in the FM-side operator runbook.
- **artifact:** FM operator runbook covering all four capabilities.
- **estimated time cost:** 2 sessions likely (install + backup + restore in one session; log-reading in a separate session — different surface). Per Rule 11, may split further if scope surfaces.

#### A3. Multi-node operational readiness

- **gate:** release
- **prerequisites:** A2 substantially complete (so FM can install/back up the new nodes); E1 (Onboarding/Install Dashboard Widget) for the first-run customer install experience
- **success criterion:** Four nodes running on production: marketing site, demo install, test/deploy instance, spare-for-first-customer. Each node's purpose + URL + access creds documented. FM monitors all four. Test/deploy instance is the target environment for subsequent rehearsals.
- **artifact:** node inventory doc.
- **estimated time cost:** 1 session (mostly ops, not code; may extend if any node provisioning surfaces issues).

#### A4. DB wipe + backup recovery (Capsize drill — runbook polish)

- **gate:** release
- **prerequisites:** A1 (synthetic data to plant pre-wipe), A3 (test/deploy instance to run the drill against)
- **success criterion:** Timed cold restore against a production-shape install completes in under 30 minutes with a 200MB-shape zip. Marker contact (planted pre-wipe) confirmed gone post-restore. App health green post-restore. Procedure written for an operator who does not know the codebase. Procedure was verified twice end-to-end at session 242 against local infrastructure; this session validates the procedure on production-shape infrastructure and produces the operator-facing runbook.
- **artifact:** operator runbook in `docs/runbooks/db-wipe-restore.md`.
- **estimated time cost:** 1 session.

#### A5. 2FA for admin accounts

- **gate:** release
- **prerequisites:** none
- **success criterion:** Admin login requires a second factor (TOTP via authenticator app) in addition to password. Recovery codes available at enrollment. Existing admin users have a one-time enrollment flow on next login. The FM-agent API key path is unaffected (it's not a user credential, per the contract spec). Tested across the standard Filament admin entry points.
- **artifact:** the feature itself, plus help-doc entry on enrollment.
- **estimated time cost:** 1 session.

### Track B — Onboarding cluster

#### B1a. Organizations Model Overhaul (Min) ✅

- **gate:** release
- **prerequisites:** none
- **success criterion** *(closed at session 255)*: Five nullable transactional FKs landed (`donations.organization_id`, `memberships.organization_id`, `event_registrations.organization_id`, `events.sponsor_organization_id`, `transactions.organization_id`), all `ON DELETE SET NULL`. Four importer Org-as-source sentinels (`__org_donor__`, `__org_member__`, `__org_sponsor__`, `__org_invoice_party__`) reusing the `__org_contact__` strategy-radio UX. The original "four read-only panels on Org edit" plan was scoped down mid-session: financial transactions are referenced via filtered links to Finance, not displayed inside records; final shape is **Events Sponsored panel only** plus a "View affiliated contacts →" ellipsis-menu link to the Contacts list (filtered by `organization_id`). Org admin form rebuilt to a single-section layout with type values `nonprofit/for_profit/government/other`, an `email` field, and the address fields flowed inline. Sponsor field added to the Event admin form. Notes lifted from a free-text textarea to the same polymorphic Timeline pattern Contact uses, via a new shared abstract `app/Filament/Concerns/RecordTimelinePage.php` (ContactNotes refactored to extend it; new OrganizationNotes extends it; blade renamed to `record-timeline.blade.php`); `organizations.notes` text column dropped. Block-with-counts deletion guard with force-delete branch.
- **artifact:** the feature itself; required for B2 to have meaningful Org-related fixtures. **Closed at session 255.** See `sessions/255. Organizations Model Overhaul (Min) — Log.md` for the full landing.
- **estimated time cost:** 1 session.

#### B1c. Organizations Importer ✅

- **gate:** release
- **prerequisites:** B1a (Org peer-record family in place)
- **success criterion** *(closed at session 256)*: Top-level CSV importer for Organizations under the Tools group, mirroring the namespaced importer pattern (Memberships shape — single-entity, no contact-match bucket). Five new schema columns on `organizations` (`source` NOT NULL default `'human'`, `custom_fields` jsonb, `import_source_id`, `import_session_id`, `external_id`) + composite index. Three new mapping-save columns on `import_sources` (`organizations_field_map` / `organizations_custom_field_map` / `organizations_match_key`). Three new sentinels (`__custom_organization__` / `__tag_organization__` / `__note_organization__`). `Organization` model gains `EnforcesScrubInheritance` + `HasSourcePolicy` with `scrubInheritsFrom() === []` (top of source-policy graph); `ACCEPTED_SOURCES = [HUMAN, IMPORT, SCRUB_DATA]`. `ImportModelType::Organization` enum case + `ImportSessionActions` / `ImportSessionPreview` arms. In-session lifts: pre-existing trait bug fixed (`serializeColumnMaps` was zeroing custom-field sentinel in column_map, silently dropping custom-field columns under all five namespaced importers' UI flows); pre-existing `donations-mapping-indicator.spec.ts` Choices.js `selectOption` pattern fixed; Filepond upload-completion wait hardened; `@on-demand` Playwright tag + project introduced; Track F + Track G lifted into the release plan; session 257 prompts drafted. See `sessions/256. Organizations Importer — Log.md` for the full landing.
- **artifact:** the feature itself; required for B2 to land real-shape exports with full Org metadata. **Closed at session 256.**
- **estimated time cost:** 1 session.

#### B1b. Affiliations Junction & Soft-Credit Layer *(post-B2 follow-up to B1a)*

- **gate:** release
- **prerequisites:** B1a (transactional FKs in place); B2 (onboarding rehearsal informs the junction shape).
- **success criterion:** Split per Rule 11 — **structural half (affiliations + Org gaps + admin UI) shipped at session 264; soft-credit half (donation_credits) ships at session 265.** Final shape: `affiliations` junction table (`contact_id`, `organization_id`, `role`, `is_primary`) supporting multi-employer / multi-role Contact↔Org relationships (date-range columns dropped mid-264 as confusing-without-purpose). `Contact.organization_id` dropped outright with one-shot data migration to `affiliations` rows with `is_primary = true`. Five importer touch sites rerouted to `Affiliation::bindContactToOrganization` (Contacts importer + the four `__org_contact__` callers — Donations / Memberships / Events / Invoice Details). Contact admin form gains an affiliations Repeater. Org admin gains industry + EIN columns, an Affiliated Contacts panel (replacing the ellipsis-menu deep-link), and the deletion-guard count incorporates affiliations. `donation_credits` + soft-credit display land at 265. Out of scope: Org-Org relationships (parent / subsidiary / fiscal sponsor) — defer until forcing function emerges.
- **artifact:** the feature itself; carries the affiliation modelling required for any sales narrative involving complex donor relationships.
- **estimated time cost:** 1–2 sessions; may split per Rule 11.

#### B2. Onboarding rehearsal cluster ✅

- **gate:** release
- **prerequisites:** B1a, A1, E2 (Importer Mapping Page UX), E3 (Rich Text Custom Fields). B1b is *not* a prerequisite — B2 exercises B1a's transactional Org peering and informs the shape B1b ultimately adopts.
- **success criterion** *(closed at session 258 with substantial findings register)*: Onboarding migration help doc landed in the help system at `resources/docs/onboarding-migration.md` (slug `onboarding-migration`, category `tools`, standalone) and linked from the main importer help doc. Phase 2 (Migration-in) measurement was *partial* — Contacts cleared 25/25 cleanly; Events drove through wizard with manual mappings but landed 0 rows due to G1 cross-importer email-pool mismatch; the other five importers halted at the test-driver layer (methodology issue, not importer bug). Phase 3 (Migration-out) cleared cleanly: 5K-contact install exports in 1.65 seconds with row-count parity. Phase 4 (Custom fields + public collection) confirmed CF round-trip works on Contacts (25/25 with populated `custom_fields`, 6 auto-created defs); public `/events` renders HTTP 200 with content. Phase 5 (Custom-field-with-lookup) confirmed structurally not supported in v1. Phase 6 blind eval ran the discipline check. Two new working-set entries lifted from the rehearsal (B2a + B2b below); several Out-of-Gate register entries lifted (FieldMapper aliases, events status normalization, email-trim, A1 Faker uniqueness ceiling, public-widget bundle errors). The original four sub-scenario success bars were not all met as originally written — Migration-in's <5% manual cleanup bar holds for preset-supported CSVs only; with the G1 generic-preset fixtures, manual mapping under the current importer shape exceeds 50% for 5 of 6 namespaced importers. The help doc frames the migration tractably for the customer-facing audience (no specific source-system names, no methodology candor); the operator-internal methodology + findings detail lives in the session log.
- **artifact:** **Onboarding — Migration** help doc at `resources/docs/onboarding-migration.md` (registered in help system via `help:sync`). **Closed at session 258.** See `sessions/258. Onboarding Rehearsal Cluster — Log.md` for the full landing including methodology + measurements.

#### B2a. Lift Contacts auto-mapping pattern to namespaced importers ✅

- **gate:** release
- **prerequisites:** B2 closed (the rehearsal that surfaced this finding).
- **success criterion** *(closed at session 259)*: Five new per-importer mapper services at `app/Services/Import/<Type>FieldMapper.php` (Event / Donation / Membership / Invoice / Note) parallel to the existing Contacts `FieldMapper`; each page's `guessDestination()` delegates to its mapper; the trait passes `$sourcePreset` through. Floor preserved under all three presets (`generic` / `wild_apricot` / `bloomerang`); generic preset enriched with operator-realistic aliases including no-separator/underscored parity variants and entity-prefixed canonical labels matching each `*ImportFieldRegistry::flatFields()` output. Coverage shift on G1 messy fixtures vs ~10% baseline at 258: events 22%→**93%**, donations 44%→**83%**, memberships 67%→**83%**, invoice details 84%→**89%**, notes 93%. Contacts FieldMapper audited for missing common aliases — 27 generic-preset additions across 9 column groups including the `Postal Code` 258 finding plus the no-separator parity rule (`firstname`/`first_name`, `zipcode`/`zip_code`). +91 tests on the lift; +5 in-session-absorption tests; +1 pre-existing-bug regression test. Help-doc revision at `resources/docs/onboarding-migration.md` § "Some column names won't auto-recognize" softens the warning post-lift. **In-session absorptions:** events `status` case-normalization (`mapEventStatus()` mirroring `mapDonationStatus`/`mapMembershipStatus`); email whitespace trim at the contact-create / contact-update boundary in both `ImportContactsJob` and `ImportProgressPage::processOneRow`; pre-existing scope-shadow bug fix in `ImportInvoiceDetailsProgressPage::runCommit()` (foreach `$handle` shadowing the outer file resource — surfaced during user testing of the 259 changes with auto-create-CFs). **Sentinel-pattern parity** lifted to Out-of-Gate as a follow-on candidate (~2-4 hours plus design conversation).
- **artifact:** the architectural lift; five per-importer mapper services + unit tests for header recognition under each preset; runbook revision. **Closed at session 259.** See `sessions/259. Importer Auto-Mapping Pattern Lift — Log.md` for the full landing.

#### B2b. Export CSV + JSON actions for non-Contact list resources *(B2 follow-on)* ✅

- **gate:** release
- **prerequisites:** B2 closed.
- **success criterion** *(closed at session 261)*: Operator-facing **Export CSV** and **Export JSON** admin actions added to every list resource that holds migration-relevant data — 10 list pages total: Contacts (already had CSV; gained JSON), Organizations, Donations, Events, Event Registrations (event-scoped), Memberships, Transactions, Funds, Campaigns, Notes. Shared `App\Services\ListExportService::stream()` writer abstraction with single format flag (`'csv' | 'json'`); per-resource `exportColumnSpec()` static methods declare the column shape. CSV flattens custom fields one-column-per-`CustomFieldDef`; JSON nests them under a `custom_fields` sub-object, omitting empty values. Permission gates per resource via `view_any_<resource>`. EventRegistration export event-scoped to the currently-viewed event. The original B2b spec was CSV-only; the user-agreed expansion at 260 close folded JSON into the same session and lifted XLSX to a follow-on (B2b'). 38 new tests landed: 9 service-level + 20 page-level (CSV+JSON × 10 resources) + 9 capability-gate tests. In-session absorptions: DuplicateHeaderDetector address-line carve-out widened from `\s` to `[\s_\-]` so underscored canonical exporter headers (`address_line_1` / `_2`) round-trip cleanly through the importer (6 regression tests + 1 still-flagged-non-address guard); Import Contacts link removed from the contacts ellipsis menu for permission-isolation discipline. Help-doc additive note in `resources/docs/onboarding-migration.md` § Migration-out registered via `help:sync`.
- **artifact:** the feature itself; the `ListExportService` writer abstraction; per-resource standardized export pattern. **Closed at session 261.** See `sessions/261. List Resource Exports — CSV and JSON — Log.md` for the full landing.
- **estimated time cost:** 1 session.

#### B2b'. XLSX format add for list resources *(B2b follow-on)* ✅

- **gate:** release
- **prerequisites:** B2b closed (the 261 `ListExportService` writer abstraction is the load-bearing dependency).
- **success criterion** *(closed at session 262)*: Operator-facing **Export Excel** admin action shipped on every list resource that got the 261 CSV+JSON export pass — same 10 list pages. Shared `ListExportService::stream()` grew a third format branch (`'xlsx'`); per-page wireup added one `Actions\Action::make('exportXlsx')` entry after the existing JSON action in each ellipsis ActionGroup (menu order CSV → JSON → Excel). Library: `openspout/openspout` (already a transitive dependency via `filament/actions v3.3.49` — no Composer change). Streaming shape: OpenSpout writes to `tempnam(sys_get_temp_dir(), 'xlsx-')` via `openToFile($tempPath)`, then `readfile($tempPath)` inside the `streamDownload` callback, with the entire write-and-stream sequence wrapped in `try { ... } finally { @unlink($tempPath); }` (load-bearing — disk-leak guard, exercised by the cleanup-on-exception regression test). Cell-type semantics: **Option B** — additive `'type'` hint per column-spec entry (`'date' | 'datetime' | 'number' | 'boolean'`); the XLSX branch's private `coerceForXlsx()` helper coerces the value-callable's string return back to native types (`Carbon::parse()` for dates/datetimes, `(float)` for numerics, `(bool)` for booleans) before passing to OpenSpout's `Cell::fromValue`; CSV/JSON paths unchanged so 261's byte-for-byte output is preserved (38 existing tests passed unchanged). Date/datetime cells additionally carry cached `Style` instances with `setFormat('yyyy-mm-dd')` / `'yyyy-mm-dd hh:mm:ss'` so Excel renders them as dates rather than as raw OOXML serial numbers (discovered during test-writing — without the format style, OpenSpout's DateTimeCell writes a number that Excel renders as `46036` not `2026-03-01`). ~30 `'type'` hints landed across the 10 specs (Contact 2, Org 1, Donation 3, Event 5 + 4 for registrations, Membership 4, Transaction 3, Fund 3, Campaign 5, Note 4). CF flattening matches CSV (one column per `CustomFieldDef`); permission gates inherit `view_any_<resource>` verbatim. Filename `<resource>-YYYY-MM-DD.xlsx`; MIME `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`. Tests: 16 added (10 page-level + 6 service-level including the cleanup-on-exception regression guard). Help-doc additive note in `resources/docs/onboarding-migration.md` § Migration-out (v0.2 → v0.3) registered via `help:sync`.
- **artifact:** the third format branch on `ListExportService` + private `coerceForXlsx()` helper; per-resource column-spec `'type'` hints; updated ellipsis menus across 10 pages; cleanup-on-exception test as the regression-guard for the disk-leak class of bug. **Closed at session 262.** See `sessions/262. List Resource Exports — XLSX Format Add — Log.md` for the full landing.
- **estimated time cost:** 1 session.

### Track C — Workflow rehearsals

#### C1. Notes Permissions (feature half) *(prerequisite stub for C3)*

- **gate:** release
- **prerequisites:** none
- **success criterion:** Per the *feature half* of the existing Notes Permissions & Permissions Audit stub: finer-grained permission gates around the structured-interactions surface (subtype, direction, outcome, participants), `edit-only-by-creator` opt-in tenant setting (auth user must equal `notes.author_id` to edit), manager override permission. The audit half of the original stub is consumed by C3.
- **artifact:** the feature itself.
- **estimated time cost:** 1 session.

#### C2. Event Ticket Tiers *(prerequisite stub for C5)*

- **gate:** release
- **prerequisites:** none
- **success criterion:** Per existing stub in `session-outlines.md` § Event Ticket Tiers. `TicketTier` model, `Event hasMany TicketTier`, `EventRegistration.ticket_tier_id` FK, admin form repeater, public registration tier picker, data migration that creates a "General" tier for existing priced events, retroactive linkage of session 189's `ticket_type` + `ticket_fee` import fields to Tier rows.
- **artifact:** the feature itself.
- **estimated time cost:** 1 session.

#### C3. Permission audit + Concurrent admin editing + Accidental public exposure *(folded)*

- **gate:** release
- **prerequisites:** C1 (Notes feature gates landed); A1 (synthetic data for adversarial-edit attempts)
- **success criterion:**
  - **Permission audit:** every admin action (Filament resources, pages, actions, bulk actions, header actions) has a documented permission gate enforced at both UI and controller layers, walked from volunteer / board-read-only / staff-admin / public-visitor perspectives. Permission matrix table produced. Findings fixed in-session per Rule 2.
  - **Concurrent admin editing:** two admin sessions edit the same contact simultaneously → behavior is documented + predictable (last-write-wins or conflict warning); no data corruption. Same for CMS page edits during publish.
  - **Accidental public exposure:** attempts to mark sensitive fields public (home addresses, donor amounts, internal notes) hit a warning/confirmation gate or are impossible. Each sensitive field's protection mechanism documented. Public-content indicator visible on every record/widget surface that has potential to leak.
- **artifact:** permission matrix at `docs/runbooks/permission-matrix.md` + data-classification notes in same file.
- **estimated time cost:** 1–2 sessions; may split if audit findings exceed in-session-fix capacity per Rules 2 + 11.

#### C4. Donation-to-acknowledgment loop

- **gate:** release
- **prerequisites:** A1; E4 (Stripe Checkout Branding) for the narrative arc
- **success criterion:** Donor donates via public form → Stripe charges → CRM records donation + Transaction → tax receipt email sent → QuickBooks sync (if connected) → year-end statement. Refund path: process partial refund → corrected acknowledgment sent → CRM + QB stay consistent. All steps verified end-to-end; receipt email content matches donor + amount + date exactly.
- **artifact:** donation runbook at `docs/runbooks/donation-acknowledgment.md` + sales-narrative scaffold derived from the runbook.
- **estimated time cost:** 1 session.

#### C5. Event with everything

- **gate:** release
- **prerequisites:** C2 (Tiers feature shipped); A1
- **success criterion:** Event configured with paid tiers + comp tickets + waitlist + custom registration questions + capacity. Each path runs (paid pays Stripe, comp gets free seat, capacity hit triggers waitlist, waitlist promotion works on cancellation). Day-of check-in flow runs on a mobile device. Post-event sequence (thank-you email, attendance log) fires.
- **artifact:** event runbook at `docs/runbooks/event-with-everything.md`.
- **estimated time cost:** 1 session.

#### C6. Membership renewal cycle

- **gate:** release
- **prerequisites:** A1
- **success criterion:** Simulate signups → renewal-due → renewal-paid → grace → lapse → reactivation → dues-change → payment-failure across a synthetic cohort. Each transition triggers the right system email; portal reflects the right state; admin UI shows lifecycle correctly. State machine gaps logged as findings (and fixed in-session if small per Rule 2; otherwise lifted to follow-on tickets).
- **artifact:** membership runbook at `docs/runbooks/membership-renewal.md`.
- **estimated time cost:** 1–2 sessions; year-of-lifecycle compression is fixture-heavy.

#### C7. Email at volume

- **gate:** release
- **prerequisites:** A3 (production-infra send is the meaningful test)
- **success criterion:** 5K-recipient newsletter with personalization + images + unsubscribe link sends in under 30 minutes via Resend with no rate-limit failures. DKIM/SPF/DMARC verified at sender side via mail-tester.com (90+ score). Bounce handling parses bounces correctly. Unsubscribe link works and persists.
- **artifact:** email runbook at `docs/runbooks/email-at-volume.md` + deliverability checklist.
- **estimated time cost:** 1 session.

### Track D — Late-cycle drills

#### D1. Scale rehearsal

- **gate:** release
- **prerequisites:** A1 (the generator carries the synthetic-data load)
- **success criterion:** At 10x assumed ceiling, no degradation visible to end-users. At 100x, identify the first three things to drag and document workarounds. At 1000x, document failure modes. Sizing doc names contact / donation / registration counts at each tier with median + p95 latency on key admin views (contacts list, donations list, search) and key public flows (page render, event registration).
- **artifact:** sizing document at `docs/runbooks/sizing-ceilings.md`.
- **estimated time cost:** 1 session.

#### D2. Compatibility cluster *(Browser bingo + Accessibility + Flaky connection — folded)*

- **gate:** release
- **prerequisites:** Track E mobile/typography/theme items must close first per Rule 8 — specifically E5 (Mobile Type Scaling), E6 (Theme Colors Refactor), E7 (Column-Layout Mobile Collapse). Plus the entire workflow track (C1–C7) must close so the surface is final.
- **success criterion:**
  - **Browser bingo:** admin + public surfaces tested across Chrome / Safari / Firefox (current), iPad (one-version-old), Pixel (current), and Windows machine running 2-major-versions-back Chrome. Each combination passes or has documented known issues. Mobile type scaling, column collapse, and Quill-rendered content explicitly checked.
  - **Accessibility:** public site passes WCAG AA on the five seeded starter pages + admin contact form via axe-core; manual screen-reader pass through donation flow + event registration succeeds (NVDA + VoiceOver). Keyboard-only navigation works on the same flows.
  - **Flaky connection:** Chrome DevTools throttling (Slow 3G + 30% packet loss) for day-of check-in flow. Check-ins succeed eventually or fail predictably; no double check-ins on retry; admin sees clear connection-state feedback. Same simulation against admin contact-edit doesn't lose data.
- **artifact:** compatibility matrix + WCAG-AA compliance summary + graceful-degradation runbook, all at `docs/runbooks/compatibility.md`.
- **estimated time cost:** 1–2 sessions; may split into Browser+Accessibility / Flaky-connection halves if scope inflates per Rule 11.

#### D3. Integration retest — coordinated tire-kicking *(absolute last rehearsal)*

- **gate:** release
- **prerequisites:** all of A, B, C, D1, D2 closed (D3 runs against the surface as it'll ship)
- **success criterion:** Every external integration (Stripe / Resend / DigitalOcean Spaces / QuickBooks / Google Calendar / others identified at session time) exercised end-to-end. Per-integration: tire-kick steps + green criterion + red criterion documented. Audit-style per Rule 2 — small fixes absorb in-session.
- **artifact:** per-integration runbook entries at `docs/runbooks/integrations/{integration}.md`.
- **estimated time cost:** 1 session.

#### D4. Test suite review — cost & shape

- **gate:** release
- **prerequisites:** all of A, B, C, D1–D3, E closed — D4 reviews the suite as it'll ship; running it before late-cycle test additions land would re-bake the same cost analysis.
- **success criterion:** Per the existing `Test Suite Audit — Cost, Coverage, and Shape` stub in `session-outlines.md` — measurement-first pass with the three rubrics (runtime budget per shape, assertion density, setup-to-assertion ratio). User-supplied surface list drives the coverage-gap phase. Outcome target: trim measurable runtime or redundancy without losing meaningful coverage. The slow group's full-suite cost is the specific question the user surfaced at session 251 close — D4 either confirms it earned its weight or drops/restructures the heaviest tests. **D4 also scopes Pest `--parallel` viability** — runs the cheap experiment (install paratest, `php artisan test --parallel --processes=4`, log failure surface) and decides whether the audit-driven trims recover enough runtime to defer parallelization, or whether to fold the test-isolation cleanup (filesystem-shared paths under `storage/app/private/`, the pre-existing `seedWidgetCollections` flake) into D4 or lift it as a follow-on per Rule 11. See the `Parallelization evaluation` sub-section in the outline stub for shape details. Carry-forward exception: if iteration friction during the C-track rehearsals starts costing real time before D4's slot lands, lift parallelization sooner as a standalone fix-shape session.
- **artifact:** committed baseline timing snapshot, findings-and-gaps report at `sessions/NNN-test-audit-findings.md`, applied picks (each as its own commit), updated baseline snapshot.
- **estimated time cost:** 1 session; per Rule 11, may extend if findings exceed in-session-fix capacity.

### Track E — Demonstrability polish

All entries are pre-Beta-1 blocking. Order is best-guess; items with rehearsal dependencies are positioned to land before those rehearsals (see execution order). Detailed scope for each entry lives in the corresponding stub in `session-outlines.md`; entries below carry only the metadata that connects them to the plan.

#### E1. Onboarding/Install Dashboard Widget ✅

- **gate:** release
- **prerequisites:** none; should land before A3 (multi-node first-customer install experience)
- **success criterion** *(closed at session 249)*: Super-admin-gated slot-grid dashboard widget that surfaces a 14-item checklist across three category bands (required-to-launch / required-for-features / optional). Each item carries a status pill plus a deep-link "Configure →" button into the existing admin page where the item lives — no new admin surfaces authored. Two render modes: first-run (full prominence at top of the super-admin grid) when `installation_completed_at` is null; health-check (compact, only non-`done` items) when the flag is set. "Mark setup complete" + "Reset install state" actions flip the flag. The Stripe item carries a warning branch when configured against a `pk_test_` key (pairs with the separate Stripe Test-Mode Detection stub). In-session lift: per-widget Filament card outline (each slot-grid cell now wraps in its own `<x-filament::section>` rather than sharing one outer card with siblings).
- **artifact:** the widget itself. **Closed at session 249.** See `sessions/249. Onboarding-Install Dashboard Widget — Log.md` for the full landing.
- **estimated time cost:** 1 session.

#### E2. Importer Mapping Page UX ✅

- **gate:** release
- **prerequisites:** none; should land before B2 (will surface as a finding otherwise)
- **success criterion** *(closed at session 254)*: Three landed UX improvements to the Map Columns step, applied as a shared pattern across all six mapping pages (the five trait-driven importers plus the Contacts variant): (a) per-row status indicator badge — red **×** for unmapped, green **✓** for mapped, with custom-field rows requiring label / handle / Field type all filled before flipping to complete; (b) reduced vertical sprawl via `Forms\Components\Group` row wrap + Filament's native `inlineLabel()` (label 1/3 + Select 2/3 responsive) + tightened row padding and inter-row separator; (c) `->searchable()` on each per-column Select. In-session lifts: pre-existing wizard back-button bug fix; duplicate-strategy radio dropped its `default('skip')` and now indicates the same complete/incomplete state; red-coloured `.choices__button` clear-button styling scoped to importer rows for visibility; Filament Notification toast when a duplicate column-mapping is created; pre-existing help slide-over routing fix (resolves through render-hook scope so the help button survives Livewire roundtrips); `import-contacts.md` content audit covering the new behaviour. **Optional grouping by entity** (the original fourth bullet) was deliberately deferred — programmatic regex-shaped grouping is brittle without LLM/MCP-shape help; revisit on real importer-use feedback, not via stub-and-defer.
- **artifact:** the feature itself. **Closed at session 254.** See `sessions/254. Importer Mapping Page UX — Log.md` for the full landing.
- **estimated time cost:** 1 session.

#### E3. Rich Text Custom Fields ✅

- **gate:** release
- **prerequisites:** none; must land before B2 (HTML in import data)
- **success criterion** *(closed at session 250)*: New `rich_text` custom-field type alongside text / number / date / boolean / select. QuillEditor primitive on admin forms (the established convention across NoteResource / EventResource / EmailTemplateResource / GeneralSettingsPage). HTML stored verbatim in the existing `custom_fields` JSONB column; render path is `{!! !!}` matching every other rich-text surface in the app. Importer treats rich-text cells as plain strings — both the manual mapping and the auto-create paths now offer `rich_text` as a field-type option, so HTML CSV cells (Wild Apricot bios) round-trip into rich-text-typed fields and mount QuillEditor on the next admin edit. Event and Page admin forms surface rich-text custom fields automatically via the existing `HasPageBuilderForm::metadataFormSchema` trait wiring.
- **artifact:** the feature itself. **Closed at session 250.** See `sessions/250. Rich Text Custom Fields — Log.md` for the full landing.
- **estimated time cost:** 1 session.

#### E4. Stripe Checkout Branding

- **gate:** release
- **prerequisites:** none; should land before C4 (donation narrative)
- **success criterion:** Per existing stub. Audit Stripe API constraints, then implement consistent branding across product / donation / event / membership checkouts. `custom_text`, statement descriptors, line-item description/image overrides as available.
- **estimated time cost:** 1 session.

#### E5. Mobile Type Scaling *(scoped small)*

- **gate:** release
- **prerequisites:** none; must land before D2 (Rule 8)
- **success criterion:** Typography stabilizes on narrow viewports without becoming a whack-a-mole exercise. The user-supplied design (per existing stub) — 3 size fields per element at lg/md/sm breakpoints with 25%-per-step default — is the *target* shape, but the session should keep scope tight: either user-supplied per-breakpoint values OR calc functions, not both, not custom breakpoint widths beyond the existing three.
- **estimated time cost:** 1 session (per the "scoped small" constraint; per Rule 11, may extend if the storage-migration shape forces it).

#### E6. Theme Colors Refactor

- **gate:** release
- **prerequisites:** none; should land before D2 (Rule 8)
- **success criterion:** Per existing stub. Decide per-column placement of `primary_color` / `header_bg_color` / `footer_bg_color` / `nav_*_color` between theme (`SiteSetting`) and template; migrate accordingly.
- **estimated time cost:** 1 session.

#### E7. Column-Layout Mobile Collapse

- **gate:** release
- **prerequisites:** none; must land before D2 (Rule 8)
- **success criterion:** Per existing stub. Per-layout collapse toggle (default on, overridable off), implemented via container queries against `.page-layout`, threshold at 768px, with the public-side `data-collapse-mobile` attribute approach.
- **estimated time cost:** 1 session.

#### E8. UI/UX Sprint *(page builder full-screen + Quill height handle)*

- **gate:** release
- **prerequisites:** none
- **success criterion:** Per existing stub. Page-builder full-screen toggle (persists per-user via localStorage); Quill drag-resize handle on the bottom edge with per-user height persistence.
- **estimated time cost:** 1 session.

#### E9. Widget Help Authoring & Help-System Integration

- **gate:** release
- **prerequisites:** none
- **success criterion:** Per existing stub. Resolve where widget help lives, how it surfaces, the rollup story; land first 3–5 widget help entries to validate the chosen pattern.
- **estimated time cost:** 1 session.

#### E10. Full-Width Architecture Enforcement ✅

- **gate:** release
- **prerequisites:** none
- **success criterion** *(closed at session 267)*: The single `full_width` toggle split into `background_full_width` + `content_full_width` on widgets (`page_widgets.appearance_config.layout`), column layouts (`page_layouts.layout_config`), and per-type defaults (`widget_types` column-replace migration). Render pipeline collapsed the prior three full-width read sites in `AppearanceStyleComposer` + `PageBlockRenderer` into one helper with column-child clamping and `(false, true) → (true, true)` normalization. The renderer separates layout appearance from grid display: `.page-layout` (bg) > optional `.site-container` (content) > `.layout-grid` (display). Bypass audit ran across all 38 widgets and came back clean — no per-template CSS escape patterns; structural enforcement is satisfied entirely by the converged read path. Editor parity in-session absorptions: `formatLayout()` ships a composed `inline_style` field (the editor reaches gradient/image parity with the public site without duplicating `GradientComposer` in JS); both Livewire bootstrap paths (`PageBuilder.php`, `RecordDetailViewBuilder.php`) gained `appearance_config` + `inline_style` on layout items so the editor renders correctly on first load (pre-existing gap surfaced + closed); `LayoutRegion.vue` split into outer `.layout-region__container` (appearance) + inner `.layout-region__grid` (display) so the bg and content toggles act on independent elements (parallel to the public-side three-element structure). Per-type defaults flipped uniformly to `(bg:true, content:false)` per user direction (the four `fullWidth(): true` overrides on Hero / Nav / BlogListing / EventsListing dropped). Per-instance values across all three jsonb surfaces + `widget_presets` rewritten in the same migration. Permanent regression coverage at `tests/e2e/page-builder/full-width-matrix.spec.ts` (20 specs). See `sessions/267. Full-Width Architecture Enforcement — Background and Content Split — Log.md` for the full landing.
- **artifact:** the migration + composer/renderer convergence + admin-UI two-toggle inspector + Playwright matrix spec. **Closed at session 267.**
- **estimated time cost:** 1 session.

#### E11. Page Builder Focus-Scroll Clamp

- **gate:** release
- **prerequisites:** none
- **success criterion:** Per existing stub. Scroll lock once a widget is focused; tall-widget exception clamps the focused widget as a scroll container.
- **estimated time cost:** 1 session.

#### E12. Housekeeping Batch 2

- **gate:** release
- **prerequisites:** none
- **success criterion:** Per existing stub. Text widget vertical alignment, in-app build-trigger audit, dev-environment orphan media cleanup (artisan-command shape closed at session 252; revisit per the reopened stub in `session-outlines.md`), heroicon picker in Quill. May split per Rule 11.
- **estimated time cost:** 1–2 sessions.

#### E13. Help docs body content

- **gate:** release
- **prerequisites:** none; lands late
- **success criterion:** Existing stubs (currently `resources/docs/generate-tax-receipts.md`) get body content written. Audit for any other stubs and complete.
- **estimated time cost:** 1 session.

#### E14. Third-Party Licensing Compliance Audit

- **gate:** release
- **prerequisites:** none; lands late, before T1
- **success criterion:** Per existing stub. Swiper.js MIT compliance verified; all npm + Composer dependencies reviewed for license compatibility with a commercial product.
- **estimated time cost:** 1 session.

### Track G — Test-Data Generation Infrastructure

Multi-session track for generating adversarial fixtures the importer can be tested against. Lifted at session 256 close: the project has only two real-world data sets, both repeatedly scrubbed-and-re-imported, neither generating new findings. Real data has stopped paying for itself as a test input. Adversarial generated fixtures expand coverage without privacy concerns and let us harden the importer ahead of B2 (Onboarding rehearsal cluster) and any future importer-touching session.

Track G's pre-Beta-1 scope is the foundational generator + a follow-on session for cross-importer pairs / replay / adversarial dedup. Format extensions beyond CSV (XLSX, JSON, source-system-specific shapes like Salesforce) live in the post-release section.

#### G1. Importer Test-Fixture Generator — CSV Foundation ✅

- **gate:** release
- **prerequisites:** none (the generator stands alone; consumes the field registries + PII scanner the importer already uses)
- **success criterion** *(closed at session 257)*: New artisan command `import-fixtures:generate` emits CSV fixtures for each of the seven importers (contacts / events / donations / memberships / invoice_details / notes / organizations) across five "shape" modes (clean / messy / corrupt / pii / stress), seedable for determinism. Each fixture lands at `storage/app/import-test-fixtures/<importer>-<shape>-<preset>-<encoding>-<seed>.csv` paired with a sibling `.expected.json` manifest describing per-row expected importer outcome (imported / skipped + reason / errored + reason / pii-rejected + violation, plus `corruption_kinds_by_row` documenting permissive corruption that imports anyway). Source-preset flag scoped to the three presets `FieldMapper::presets()` actually exposes (`generic` / `wild_apricot` / `bloomerang`); Neon dropped during scope-out (preset doesn't exist in code). Encoding flag (`utf8` / `utf8-bom` / `windows-1252`) varies output encoding. `App\Services\Import\FixtureRunner` lifted to drive the importers off-Livewire via Reflection; pre-seeds a fixture-runner user, one Contact per non-blank email cell, and CustomFieldDef rows from the manifest. Parametrized Pest runner at `tests/Feature/Generated/ImportFixtureRunnerTest.php` enumerates 28 fast cases (7 × 4 fast shapes) + 7 slow cases (stress under `->group('slow')`). Pre-existing fix lifted in-session: every imported Contact + Organization now lands with an "Imported from X" timeline note (was missing on four creation paths). Findings recorded for B2 to inherit without re-discovery: events `status` not normalized before DB insert, importer permissive about control chars / malformed emails / oversized cells / cf-type mismatches, `hide_pending_imports` global scope behavior. See `sessions/257. Importer Test-Fixture Generator — CSV Foundation — Log.md` for the full landing.
- **artifact:** the generator + the fixture directory + the parametrized Pest runner + the authoring doc at `docs/runbooks/import-fixture-generator.md`. **Closed at session 257.**
- **estimated time cost:** 1–2 sessions; per Rule 11, may split if the per-importer generator implementations exceed a single context window.

#### G2. Importer Test-Fixture Generator — Cross-importer Pairs, Replay, Adversarial Dedup

- **gate:** release
- **prerequisites:** G1
- **success criterion:** Generator extended with three additional fixture-set modes: (a) `--pair=cross-importer` — coordinated CSV sets where contacts.csv + donations.csv + memberships.csv reference the same external IDs, exercising `ImportIdMap` linkage end-to-end; (b) `--pair=replay` — pass-1.csv + pass-2.csv pairs for re-import dedup-strategy tests (skip / update / error / duplicate), with manifests describing per-row dedup expectation; (c) adversarial dedup fixtures — match keys differ only by case / whitespace / NBSP / zero-width-space, hardening the case-insensitive trim path. Pest runner extended to consume pair manifests. False-positive PII coverage added: rows that look PII-shaped but should not be rejected, preventing over-rejection regressions when scanner rules tighten.
- **artifact:** the extended generator + the additional fixture sets + the extended Pest runner.
- **estimated time cost:** 1 session.

### Track F — On-Demand E2E Coverage

Pre-T1 deep Playwright sweeps for surfaces that don't earn full regression-suite coverage but want a one-shot validation pass before release. Each F-track session lands a `tests/e2e/{area}/` spec set tagged `@on-demand`, runnable via `npm run test:e2e:on-demand`. Default `npm run test:e2e` runs exclude these specs.

The on-demand category was introduced at session 256 close after the Organizations importer's deep Playwright pass surfaced two pre-existing bugs that earlier per-importer tests had missed (a `serializeColumnMaps` regression silently dropping custom-field columns, and a Choices.js `selectOption` pattern broken across multiple importer specs). The pattern is: deep, fixture-heavy, judgment-led — better suited to occasional sweeps than per-merge regression.

#### F1. On-Demand E2E — Donation / payment-flow integration depth pass

- **gate:** release
- **prerequisites:** C4 (donation-to-acknowledgment loop rehearsal); E4 (Stripe Checkout Branding); D3 (so the surface as it ships is what gets exercised)
- **success criterion:** A new `tests/e2e/payments/` spec set, all `@on-demand`-tagged, covers the public donation form → Stripe test-mode checkout → webhook → Donation + Transaction records → tax-receipt email content path end-to-end. Specs simulate signed Stripe webhook payloads and verify idempotency under retries. Coverage matrix: one-time donation happy path; recurring subscription start; partial refund; full refund; failed-payment retry; Stripe-cancel redirect; webhook-replay idempotency. Each spec asserts both the DB outcome (correct rows, amounts, contact linkage) and the email outcome (receipt body matches donor name + amount + date verbatim). Stripe is already running in test mode (per session 256 close note); webhook signing setup is the new infrastructure.
- **artifact:** the spec suite + a coverage doc at `docs/runbooks/payments-on-demand-coverage.md` listing what's covered, what's not, and any in-session bugs lifted.
- **estimated time cost:** 1 session. May extend per Rule 11 if Stripe webhook signing setup surfaces issues.

#### F2. On-Demand E2E — Member portal self-service & contact-scoping security

- **gate:** release
- **prerequisites:** C3 (permission audit informs the scoping invariant); A3 (production-shape install for portal mail flows)
- **success criterion:** A new `tests/e2e/portal/` spec set, all `@on-demand`-tagged, walks each portal route from two authenticated contact fixtures (Alice + Bob). Coverage: (a) the CLAUDE.md portal-security rule — every portal route and query is scoped to the authenticated contact's own `contact_id`; Bob cannot view Alice's donations / memberships / event registrations even via URL fishing; (b) password reset, email verification, address update flows succeed end-to-end; (c) signup → email verify → first-login flow lands a clean `PortalAccount` + `Contact` pair; (d) password-reset token expires and double-use is rejected; (e) `/{portal_prefix}/*` URL routing adapts when the `portal_prefix` site setting changes mid-session. The portal-security rule is the load-bearing invariant — any cross-contact data leak surfaces as a hard fail.
- **artifact:** the spec suite + a security findings doc at `docs/runbooks/portal-security-audit.md`.
- **estimated time cost:** 1 session.

#### F3. On-Demand E2E — Permission / role-gate matrix

- **gate:** release
- **prerequisites:** C3 (permission matrix lands at `docs/runbooks/permission-matrix.md`); E14 (no further structural changes pre-T1)
- **success criterion:** A new `tests/e2e/roles/` spec set, all `@on-demand`-tagged, walks each role fixture (super-admin, staff-admin, board-read-only, volunteer, public-visitor — sourced from C3's matrix) through the admin surface. For each (role × Filament resource × action) cell in the matrix: assert the role's expected outcome — visible / hidden, actionable / disabled, enforced at controller layer not just UI. C3 findings that were too small to fix in-session per Rule 2 land here as code-level fixes. Where Playwright contradicts the documented matrix, the matrix wins for the in-session fix and Playwright tests confirm the corrected gate.
- **artifact:** the spec suite + a delta entry in `docs/runbooks/permission-matrix.md` listing any cells where Playwright contradicted the documented expected outcome.
- **estimated time cost:** 1 session.

### Terminal session

#### T1. Code Review & Cleanup + Migration Squash

- **gate:** release
- **prerequisites:** all of A, B, C, D, E, F, G closed (Rule 10)
- **success criterion:** Final code review pass in the pattern of sessions 101 / 116 / 141 / 178–179 / 205–206 — dead code, unused imports, duplicated logic, naming, outdated comments, drift from framework conventions. Combined in the same session with migration squash: collapse the per-session migration history into a single squashed migration set against the v1 schema baseline. Both halves land in one branch; no code change after T1 closes.
- **artifact:** the cleaned-up code + the squashed migration set.
- **estimated time cost:** 1–2 sessions; the squash half may force its own session per Rule 11.

---

## Execution order

Sessions run sequentially in this flat order. Per Rule 11, any session that surfaces unforeseen work splits into multiple sessions; the order below reflects intended sequencing, not a session-count target.

1. **A1.** Random Data Generator as Dashboard Widget
2. **A1b.** Fleet Manager Contract v2.0.0 — mTLS Migration *(closed at 248; A2 prerequisite)*
3. **A1c.** Fleet Manager Compromise Recovery Infrastructure *(closed at 253; documentation revision under v2.1.0, no contract bump)*
4. **A2.** Fleet Manager — node operations parity *(may be 2 sessions; FM-side resumes at FM 013+ after FM 012 absorbs v2.0.0 + v2.1.0)*
5. **E1.** Onboarding/Install Dashboard Widget *(precedes A3 for first-run experience)*
6. **A3.** Multi-node operational readiness
7. **A4.** DB wipe + backup recovery — runbook polish
8. **A5.** 2FA for admin accounts
9. **E3.** Rich Text Custom Fields *(precedes B2 — HTML in import data)*
10. **E2.** Importer Mapping Page UX *(closed at 254; precedes B2)*
11. **B1a.** Organizations Model Overhaul (Min) *(closed at 255)*
12. **B1c.** Organizations Importer *(closed at session 256)*
13. **G1.** Importer Test-Fixture Generator — CSV Foundation *(precedes B2 per request at 256 close: real-data scrub-and-reimport has stopped finding bugs; generated fixtures expand coverage)*
14. **B2.** Onboarding rehearsal cluster *(closed at session 258 with two follow-on entries lifted)*
15. **B2a.** Lift Contacts auto-mapping pattern to namespaced importers *(closed at session 259)*
16. **B2b.** Export CSV + JSON actions for non-Contact list resources *(B2 follow-on; closed at session 261)*
17. **B2b'.** XLSX format add for list resources *(B2b follow-on; closed at session 262)*
18. **A1d.** Fleet Manager Contract v2.2.0 — Backup Trigger Endpoint *(closed at session 263 — execution-order deviation: A1d jumped the queue ahead of B1b at 262 close to unblock FM session 020 CRM-side)* ✅
19. **B1b.** Affiliations Junction (structural half) *(closed at session 264; post-B2 follow-up to B1a; moved from position 18 at session 262 close to make room for A1d)* ✅
20. **B1b.** Donation Credits — Soft-Credit Layer *(session 265; B1b's checkmark drops here per the Rule 11 split applied at 264)*
21. **A1d'.** Backup notification hardening — FM 020 finding *(closed at session 266; A1d follow-on; lifted at 264 close from FM 020 manual-testing finding 2026-05-05)* ✅
22. **A1e.** Fleet Manager Contract v2.3.0 — Backup Blob Download Endpoint *(closed at session 268; CRM-side prerequisite for FM 021 + 022 restore-to-fresh-node primitive; A2(c) success-criterion CRM-side half complete)* ✅
23. **E10.** Full-Width Architecture Enforcement *(closed at session 267 — folded in the background_full_width / content_full_width split + bypass-audit clean finding + editor-parity in-session absorptions; see log for the full landing)* ✅
24. **E11.** Page Builder Focus-Scroll Clamp
25. **C1.** Notes Permissions (feature half)
26. **E9.** Widget Help Authoring
27. **C2.** Event Ticket Tiers
28. **C3.** Permission audit + Concurrent admin editing + Accidental public exposure
29. **E4.** Stripe Checkout Branding *(precedes C4)*
30. **C4.** Donation-to-acknowledgment loop
31. **C5.** Event with everything
32. **C6.** Membership renewal cycle
33. **C7.** Email at volume
34. **E5.** Mobile Type Scaling *(precedes D2 per Rule 8)*
35. **E6.** Theme Colors Refactor *(precedes D2 per Rule 8)*
36. **E7.** Column-Layout Mobile Collapse *(precedes D2 per Rule 8)*
37. **E8.** UI/UX Sprint
38. **E12.** Housekeeping Batch 2
39. **D1.** Scale rehearsal
40. **D2.** Compatibility cluster
41. **D3.** Integration retest *(absolute last rehearsal per Rule 9)*
42. **E13.** Help docs body content
43. **E14.** Third-Party Licensing Compliance Audit
44. **G2.** Importer Test-Fixture Generator — Cross-importer Pairs, Replay, Adversarial Dedup
45. **D4.** Test suite review — cost & shape
46. **F1.** On-Demand E2E — Donation / payment-flow integration depth pass
47. **F2.** On-Demand E2E — Member portal self-service & contact-scoping security
48. **F3.** On-Demand E2E — Permission / role-gate matrix
49. **T1.** Code Review & Cleanup + Migration Squash *(terminal per Rule 10)*

Numbered positions are not session numbers — they are *position in execution order*. Session numbers are assigned at session start (245, 246, …). When a position splits per Rule 11, subsequent positions retain their order.

---

## Out-of-gate register

Items considered during 244 vetting and explicitly *not* in the working set. Each carries one line of "why not now" so future-us doesn't relitigate.

- **Event Description Widget Removal → PageContext** *(post-1.0)* — Refactor; no rehearsal forces it. Roadmap entry preserved in post-Beta-1 section of `session-outlines.md`.
- **Text Color Hierarchy Rules** *(post-1.0)* — Design discussion that doesn't surface during any rehearsal as currently scoped. Defer until forced.
- **Financial Data Origin & Lifecycle Discipline — Phases B and C** *(post-release)* — Phase A complete (session 233). B is gated on "lands when an action surface that needs it is imminent"; C is gated on "defer until forced." Neither is release-blocking by user direction at session 244.
- ~~**Test Suite Audit** *(orthogonal — conditional)*~~ — Promoted to **D4** in the working set at session 251 close. The 256M → 1G memory bump fixed the immediate CI cascade, but the underlying question (does the suite earn its size?) is now in-gate before Beta-1.
- **CI test suite cascade — root-cause & fix (session 252)** — out-of-gate emergency lift. Session 251's `memory_limit` bump was necessary but not sufficient; the same 86-test cascade returned with no memory signature. Root cause was [`AppResetCommandTest`](tests/Feature/AppResetCommandTest.php) (shipped at session 247) running `Artisan::call('app:reset')` — which executes `migrate:fresh --seed --force` — from inside Pest without `RefreshDatabase`, committing seed rows past the per-test transaction wrap and breaking 86 downstream tests on unique-constraint violations. Fix: deleted `app:reset` and its test entirely (the artisan-command surface is structurally unfit for re-running `migrate:fresh` from inside the test suite); reopened the underlying orphan-media-cleanup bug as a stub in `session-outlines.md` § Housekeeping — Batch 2 with the constraint *"no new artisan command that re-runs `migrate:fresh` from inside the application."* Suite went from 86 failed/1724 passed to 1808 passed/0 failed.
- **Events `status` case-normalization helper** *(B2 carry-forward, surfaced at session 257, confirmed at 258, absorbed at session 259)* — Events CSVs with a `status` value in mixed case (`Draft`, `PUBLISHED`) used to bypass normalization and hit the `events_status_check` constraint. `mapEventStatus()` added to `ImportEventsProgressPage` parallel to the existing `mapDonationStatus`/`mapMembershipStatus` shape; maps `draft`/`published`/`live`/`active`/`public`/`cancelled`/`canceled` plus an unrecognised-falls-to-draft default. ✅
- **FieldMapper missing common header aliases** *(B2 finding)* — `FieldMapper::map('Postal Code', 'generic')` returns NULL despite `Postal Code` being the international canonical term; only `ZIP` and `Zip Code` are recognized. Audit and expand the alias dictionary across the three presets. Follow-on session candidate; small. Rolls naturally into B2a (the pattern-lift) but can land sooner standalone if scheduling permits.
- **Email whitespace not trimmed before insert** *(B2 finding, absorbed at session 259)* — Contacts imported with leading/trailing whitespace in the email column landed with the whitespace preserved. Trim added at the contact-create / contact-update boundary in both `ImportContactsJob` and `ImportProgressPage::processOneRow`. Whitespace-only email values become null; whitespace around a real email matches the trimmed canonical form. ✅

- **Sentinel-pattern parity across importers** *(259 follow-on)* — Each namespaced importer (`*ImportFieldRegistry`) exposes a different set of `__*__` sentinels. Tag sentinels are asymmetric (`__tag_event__` exists but no `__tag_donation__` / `__tag_membership__` / `__tag_invoice__`), and `__org_contact__` is absent from Notes. Standardising the set requires (a) registry edits per-importer, (b) row-processor handlers in each `Import*ProgressPage` (the dispatch logic that turns a sentinel column value into a Tag / Note / Org link at import time), (c) integration tests for each round-trip. Also a design call: which sentinels make sense per importer, since some inconsistencies (Notes not having `__note_contact__`) are deliberately structural rather than gaps. Lift cost ~2-4 hours plus design discussion. Lifted at session 259 close after sizing during the in-session absorption pass for the value-normalization findings; deferred deliberately to keep 259 service-layer-only.
- **A1 Random Data Generator's Faker unique-pool ceiling** *(B2 finding, surfaced during Migration-out planting)* — The contacts factory uses `fake()->unique()->numerify('.###')` (1,000-value pool) which overflows around ~1,200 contacts in a single PHP process. Multi-process planting works (each process gets a fresh Faker cache) but is operator-unfriendly. Replace the numerifier with a strategy that scales: UUID-suffixed emails, or a much wider numeric range, or per-batch unique-cache reset. Documents the "10K-record install" success-criterion bar properly. Follow-on; small-medium.
- **Public-widget bundle / manifest mismatch on `/events`** *(B2 finding)* — Public `/events` page renders HTTP 200 with content but the browser console reports `NPWidgets is not defined` and `cfg is not defined`. Server-rendered content is unaffected, but any widget that depends on the JS bundle is at risk. Suggested first step: run `php artisan build:public` to refresh the manifest; if errors persist, it's a public-widget-bundle authoring or layout issue. Follow-on; investigation-shaped.
- **B2 test-driver methodology lessons** *(B2 internal)* — Future rehearsal sessions that drive multiple importers in sequence should use **inline driver code** rather than layering pre-setup atop existing high-level helpers. Mixing `page.goto` + `fillUploadStep` calls between custom and helper code produced wizard-state collisions that halted 4 of 7 importer measurements at the test-driver layer. Documented in the B2 log and the playbook's methodology section. Not a working-set entry; a process note for the next rehearsal.
- **Importer duplicate-header detector — separator-agnostic address-line carve-out** *(B2b finding, surfaced at session 261, absorbed at session 261)* — `address_line_1`/`address_line_2`, `address_1`/`address_2`, `street_1`/`street_2`, `address-line-1`/`address-line-2` were getting flagged as duplicates by the importer's Review Data step despite living in distinct database columns. Root cause: the `isKnownLegitimateGroup()` carve-out in `App\Services\Import\DuplicateHeaderDetector` used `\s` as the line/digit separator (matching `Address 1`, `Address Line 2`) but missed `_` and `-` separators. Surfaced when round-tripping the new per-resource exporters (which emit `address_line_1`/`_2` as canonical headers) back through the Organizations importer. Fix: widened the regex to `[\s_\-]` for line/digit separators. Tests added in `tests/Feature/ImportSession192Test.php` § Carve-out: separator-agnostic address-line variants — six new tests pinning underscore + dash + short-form variants, plus one regression-guard asserting non-address numbered pairs (`phone_1`/`phone_2`) still surface as duplicates. The carve-out is the fragile bit of the detector — it has been wiped out before by optimization passes — so the test set is intentionally exhaustive across separator forms. ✅

---

## Deferred decisions

- **Investment-gate subset selection.** This plan structurally supports a future subset selection (every working-set entry's `gate:` line could flip from `release` to `release, investment` for items in the subset). The user's stance at session 244: skip the subset selection for now — the full plan as-shown is sufficient to seek investment against.
- **Operator master runbook / SOPs scope.** Recorded in pre-release requirements register. TBD whether this work lives in this project or a separate non-technical project. Revisit before Beta-1 release.
- **A2 split shape.** FM node operations parity may run as one session or two (install + backup + restore in one; log-reading separately). Decide at A2 session start based on FM-side codebase state.
- **C3 split shape.** Permission audit + concurrent + exposure are folded; may split if findings exceed in-session-fix capacity (Rule 2).
- **D2 split shape.** Compatibility cluster is folded; may split into Browser+Accessibility / Flaky-connection halves if scope inflates.
- **E12 (Housekeeping Batch 2) shape.** May split per Rule 11 if any item turns out to need design-level conversation. The dev-environment orphan media cleanup item reopened at session 252 (original 247 `app:reset` fix reverted — see the reopened stub in `session-outlines.md`); the next attempt is design-shaped and likely wants its own session.
