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
7. **Phase A blocks Phase B/C/D execution.** Operational foundations (Random Data Generator, Fleet Manager node ops, multi-node provisioning, Capsize runbook polish, 2FA) must be in place before rehearsals run. Rehearsals don't have a meaningful environment without them.
8. **Compatibility runs last.** D2 always runs against the surface as it'll ship. Items in Phase E that affect mobile / typography / theme / column collapse must close before D2 starts.
9. **Integration retest runs absolutely last.** D3 runs against a near-final surface — it's the final tire-kicking pass before the terminal session.
10. **Code Review + Migration Squash is terminal.** T1 is the final session before Beta-1 release. Every other entry must close first.
11. **Session count is always flexible.** A session that surfaces unforeseen work splits into multiple sessions rather than overloading a single context window. The plan doc tracks the *work*, not the session count. When a session splits, update the execution-order list to reflect the new shape — do not compress work to hit a target count.
12. **Public Website Complete is the first pre-Beta milestone.** Lifted at session 282 close: the investment conversation is blocked on a credible-looking public website, and that website is built via the CMS. Entries that block the CMS from looking demo-ready (E4–E8, E12, E15–E17) land before non-public-website work. The execution-order list marks the boundary with a `── PUBLIC WEBSITE COMPLETE ──` divider; below it execution continues to Beta 1. No scope was added — the rule re-sequences existing pre-Beta work plus the three housekeeping-promoted entries (E15 Table widget, E16 Header/footer overhaul, E17 Borders pass).

---

## Pre-release requirements register (non-session gate items)

Items that must be live before Beta-1 release but are tracked outside the session pipeline:

- **Privacy policy live on marketing site** — drafts in process with counsel.
- **Terms of Service live on marketing site** — drafts in process with counsel.
- **Operator master runbook / SOPs** — DEFERRED DECISION: TBD whether this lives in this project or a separate non-technical project. Revisit before Beta-1 release.

---

## Working set

Each entry carries: gate, prerequisites, success criterion, artifact, estimated time cost. All entries are gated on `release` only — investment-gate subset selection is a follow-up conversation against this doc; the structural slot exists per Rule 6 if/when it's wanted.

### Phase A — Operational foundations

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

### Phase B — Onboarding cluster

#### B1a. Organizations Model Overhaul (Min) ✅

- **gate:** release
- **prerequisites:** none
- **success criterion** *(closed at session 255)*: Five nullable transactional FKs landed (`donations.organization_id`, `memberships.organization_id`, `event_registrations.organization_id`, `events.sponsor_organization_id`, `transactions.organization_id`), all `ON DELETE SET NULL`. Four importer Org-as-source sentinels (`__org_donor__`, `__org_member__`, `__org_sponsor__`, `__org_invoice_party__`) reusing the `__org_contact__` strategy-radio UX. The original "four read-only panels on Org edit" plan was scoped down mid-session: financial transactions are referenced via filtered links to Finance, not displayed inside records; final shape is **Events Sponsored panel only** plus a "View affiliated contacts →" ellipsis-menu link to the Contacts list (filtered by `organization_id`). Org admin form rebuilt to a single-section layout with type values `nonprofit/for_profit/government/other`, an `email` field, and the address fields flowed inline. Sponsor field added to the Event admin form. Notes lifted from a free-text textarea to the same polymorphic Timeline pattern Contact uses, via a new shared abstract `app/Filament/Concerns/RecordTimelinePage.php` (ContactNotes refactored to extend it; new OrganizationNotes extends it; blade renamed to `record-timeline.blade.php`); `organizations.notes` text column dropped. Block-with-counts deletion guard with force-delete branch.
- **artifact:** the feature itself; required for B2 to have meaningful Org-related fixtures. **Closed at session 255.** See `sessions/255. Organizations Model Overhaul (Min) — Log.md` for the full landing.
- **estimated time cost:** 1 session.

#### B1c. Organizations Importer ✅

- **gate:** release
- **prerequisites:** B1a (Org peer-record family in place)
- **success criterion** *(closed at session 256)*: Top-level CSV importer for Organizations under the Tools group, mirroring the namespaced importer pattern (Memberships shape — single-entity, no contact-match bucket). Five new schema columns on `organizations` (`source` NOT NULL default `'human'`, `custom_fields` jsonb, `import_source_id`, `import_session_id`, `external_id`) + composite index. Three new mapping-save columns on `import_sources` (`organizations_field_map` / `organizations_custom_field_map` / `organizations_match_key`). Three new sentinels (`__custom_organization__` / `__tag_organization__` / `__note_organization__`). `Organization` model gains `EnforcesScrubInheritance` + `HasSourcePolicy` with `scrubInheritsFrom() === []` (top of source-policy graph); `ACCEPTED_SOURCES = [HUMAN, IMPORT, SCRUB_DATA]`. `ImportModelType::Organization` enum case + `ImportSessionActions` / `ImportSessionPreview` arms. In-session lifts: pre-existing trait bug fixed (`serializeColumnMaps` was zeroing custom-field sentinel in column_map, silently dropping custom-field columns under all five namespaced importers' UI flows); pre-existing `donations-mapping-indicator.spec.ts` Choices.js `selectOption` pattern fixed; Filepond upload-completion wait hardened; `@on-demand` Playwright tag + project introduced; Phase F + Phase G lifted into the release plan; session 257 prompts drafted. See `sessions/256. Organizations Importer — Log.md` for the full landing.
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

### Phase C — Workflow rehearsals

#### C1. Notes Permissions (feature half) *(prerequisite stub for C3)* ✅

- **gate:** release
- **prerequisites:** none
- **success criterion** *(closed at session 276)*: Three concrete pieces shipped — `edit_others_note` permission registered in `PermissionSeeder` and granted to `developer` (super_admin gets it via Gate::before bypass); `notes_edit_only_by_creator` SiteSetting on `GeneralSettingsPage` (string `'true'`/`'false'`, default `'false'`, super-admin-gated section); `NotePolicy::update` and `::delete` extended with three-step shape (outer capability → toggle read → author OR override). Timeline UI rewired so Contact / Organization Timeline edit/delete affordances compose the policy. 21 Pest tests cover the full matrix; 3 Playwright specs verify the UI gate end-to-end. Fast Pest 2304/0 (+27 over 275 baseline). See `sessions/276. Notes Permissions (feature half) — Log.md` for the full landing.
- **artifact:** the feature itself. **Closed at session 276.**
- **estimated time cost:** 1 session.

#### C2. Event Ticket Tiers *(prerequisite stub for C5)* ✅

- **gate:** release
- **prerequisites:** none
- **success criterion** *(closed at session 278)*: Shape (A) chosen — tier-canonical. `ticket_tiers` table (uuid, event_id FK cascade, name, price, capacity, sort_order, timestamps) + `event_registrations.ticket_tier_id` nullable FK shipped in one atomic migration that also backfills one `{name: 'General'}` tier per priced-or-capped event, retroactively links existing `event_registrations` with `ticket_type` to matching-or-newly-created tiers, and drops `events.price` + `events.capacity`. `Event::is_free` and `Event::isAtCapacity` walk tiers. Filament `Repeater::make('ticketTiers')` on EventResource with cross-row uniqueness rule. Public widget renders three modes (0 tier: no UI / 1 tier: hidden id + price / 2+ tiers: radio picker + "(sold out)" labels); single form action per surface, server-side routes free vs. paid by chosen tier's price. Per-tier capacity replaces event-level. Iteration /4 added a `notes` textarea on the public form (interim workaround for missing per-attendee data) and dropped the absolute email-uniqueness silent-success dedup (was blocking legitimate repeat registrations). Fast Pest 2341/0 (+30 over 277 baseline); +1 Playwright spec (3 scenarios). See `sessions/278. Event Ticket Tiers — Log.md`.
- **artifact:** the feature itself. **Closed at session 278.**
- **estimated time cost:** 1 session.

#### C2a. Multi-Quantity Event Ticket Purchase ✅ *(C2 follow-on)*

- **gate:** release
- **prerequisites:** C2 (tier-canonical schema shipped)
- **success criterion** *(closed at session 279)*: Buyer can purchase N tickets across one or more tiers in a single transaction. Shape (A) chosen at 278-close and shipped: `event_registrations.quantity` smallint default 1; per-tier capacity aggregate switches from `withCount` to `withSum('registrations', 'quantity')`; mixed-tier purchases produce multiple registration rows sharing a `stripe_session_id`. Public widget gains per-tier quantity spinners + live subtotal + ≥ 1-ticket-total validation. Stripe Checkout assembles multi-line-item sessions (one line per chosen tier with `quantity`). Webhook generalizes to "find all pending registrations on this stripe_session_id, promote them, record one transaction at the order total." Admin "View Registrants" gains a `tickets` column reading `quantity`. Importer unchanged (one-row-per-CSV-row → quantity=1). Fast Pest 2355/0 (+14 over 278 baseline). See `sessions/archived/279. Multi-Quantity Event Ticket Purchase — Log.md` for the full landing.
- **artifact:** the feature itself. **Closed at session 279.**
- **estimated time cost:** 1 session.

#### C3. Permission audit + Concurrent admin editing + Accidental public exposure *(folded; pre-emptively split at 279-close into #32 + #32b; #32b further split at 280-close into #32b + #32c; both #32b and #32c scopes refit at 282 Phase C audit — see notes below)*

- **gate:** release
- **prerequisites:** C1 (Notes feature gates landed); A1 (synthetic data for adversarial-edit attempts); C3a (page-action accountability + audit trail — prereq stub for #32c, lifted at 282 audit)
- **success criterion:**
  - **Permission audit** *(closed by session 280 — execution-order #32)*: every admin action (Filament resources, pages, actions, bulk actions, header actions) has a documented permission gate enforced at both UI and controller layers, walked from volunteer / board-read-only / staff-admin / public-visitor perspectives. Permission matrix table produced. Findings fixed in-session per Rule 2.
  - **Concurrent admin editing** *(deferred to #32b — TBD session; scope refit at 282 audit to slim-(b) path)*: two admin sessions editing the same record simultaneously have visible-but-not-prevented collisions — last-write-wins is documented in the admin runbook, and a lightweight "currently being edited by X (HH:MM ago)" indicator appears on edit pages of records currently being edited by another admin. No pessimistic locking, no polymorphic lock table, no takeover rules — those were the over-scoped (a) path that the 282 audit retired in favor of the minimum-viable visibility affordance. **Note:** session 281 was originally scheduled for the (a)-scope work but was never executed; the (b) refit starts from scratch.
  - **Accidental public exposure** *(deferred to #32c — TBD session; scope refit at 282 audit to Path-A only)*: attempts to mark sensitive fields public (home addresses, donor amounts, internal notes) hit a warning/confirmation gate or are impossible. Each sensitive field's protection mechanism documented. Public-content indicator visible on every record/widget surface that has potential to leak. **Out of #32c scope (lifted to C3a as prereq):** page-action accountability (actor stamped on publish/unpublish), actor notification of action, and the broader page-action audit trail — those land first as feature-half work in C3a.
- **artifact:** permission matrix at `docs/runbooks/permission-matrix.md` (matrix landed by #32; #32b adds a brief admin-concurrent-editing runbook entry; data-classification notes appended by #32c).
- **estimated time cost:** 1 session for #32b (slim refit); 1 session for #32c (Path-A refit); 1–2 sessions for C3a prereq. Original undivided estimate was 1–2 sessions; 282 audit reset the budget to ~3–4 sessions across the family.

#### C3a. Page-action accountability + audit trail *(feature half — prerequisite stub for #32c drill, lifted at 282 audit)*

- **gate:** release
- **prerequisites:** none (independent feature build)
- **success criterion:** Three pieces shipped together as the prereq to the #32c accidental-exposure drill:
  - **Accountability:** every publish/unpublish action on a public-flip-bearing record (Page, Post, Event, Product, Collection, Form — verify the full list at session start against the permission matrix doc) stamps `published_by_user_id` + `published_at` + corresponding `unpublished_*` columns on the record. Surfaced on the record's edit page ("Published by X on Y" / "Unpublished by X on Y") so the current state is visible without leaving the record.
  - **Notification:** the acting user receives a transactional email confirming the publish/unpublish they just performed, with timestamp and a direct link to the record. Intentional accountability — not interruptive but reviewable in their inbox.
  - **Audit trail:** every page-shape action (create, update, publish flip, delete) writes to a `page_action_log` table — actor, action type, record type+id, timestamp, optional changed-field summary. Queryable via an admin Tools-group resource. Retention follows the existing data-retention policy.
- **artifact:** the feature itself + a brief audit-log runbook entry at `docs/runbooks/page-action-audit.md`.
- **estimated time cost:** 1–2 sessions.

#### C3b. Auto tax receipt email *(feature half — prerequisite stub for C4 rehearsal, lifted at 282 audit)*

- **gate:** release
- **prerequisites:** none
- **success criterion:** Successful donation (via Stripe Checkout webhook) automatically dispatches a tax-receipt email to the donor — donor name, amount, date, transaction id, fund (if specified), org tax-id/EIN, IRS-compliant language. Email template configurable via the existing `manage_email_templates` admin surface. Today's manual "Send Receipts" admin action on the DonorsPage stays as a backfill / one-off resend affordance but is no longer the only path to a receipt.
- **artifact:** the feature itself.
- **estimated time cost:** 1 session.

#### C3c. Comp-tier polish + skip-Stripe-on-zero-total *(feature half — prerequisite stub for C5 rehearsal, lifted at 282 audit)*

- **gate:** release
- **prerequisites:** C2a (multi-quantity tickets shipped)
- **success criterion:** Event-registration flow handles comp tickets cleanly — when the chosen tier(s) total $0 (whether a comp-only tier or a free-event single-tier), the public flow skips Stripe Checkout entirely and confirms the registration server-side, sending the thank-you email immediately. No empty Stripe sessions, no $0 line items in the dashboard. Admin can mark a tier `is_complimentary` (label in the picker; behavior driven by zero-price). Mixed-tier orders (e.g. 1 comp + 1 paid) continue through Stripe with the $0 line item as today (no change).
- **artifact:** the feature itself.
- **estimated time cost:** 1 session.

#### C4. Donation-to-acknowledgment loop *(scope refit at 282 audit — see below)*

- **gate:** release
- **prerequisites:** A1; C3b (auto tax receipt email feature half); E4 (Stripe Checkout Branding) for the narrative arc
- **success criterion:** Donor donates via public form → Stripe charges → CRM records donation + Transaction → tax receipt email sent automatically → QuickBooks sync (if connected). All steps verified end-to-end; receipt email content matches donor + amount + date exactly. **Lifted to post-Beta at 282 audit:** year-end statement generation (December-cycle work, not Beta 1 critical); partial-refund corrected-acknowledgment automation (refunds are rare — document the manual procedure for Beta 1).
- **artifact:** donation runbook at `docs/runbooks/donation-acknowledgment.md` + sales-narrative scaffold derived from the runbook.
- **estimated time cost:** 1 session.

#### C5. Event with everything *(scope refit at 282 audit — see below)*

- **gate:** release
- **prerequisites:** C2 (Tiers feature shipped); C2a (multi-quantity tickets shipped); C3c (comp-tier polish); A1
- **success criterion:** Event configured with paid tiers + comp tickets + capacity. Each path runs (paid pays Stripe with multi-quantity support, comp gets a free seat via the C3c zero-total flow, capacity hit blocks the registration with a clear error). Post-registration thank-you email fires. **Lifted to post-Beta at 282 audit:** waitlist + waitlist-promotion-on-cancellation (real nonprofit use case but not table-stakes for v1.0 demo); per-event custom registration questions (per-attendee data); day-of check-in flow (paper check-in acceptable for Beta 1); attendance log (couples with check-in).
- **artifact:** event runbook at `docs/runbooks/event-with-everything.md`.
- **estimated time cost:** 1 session.

#### C6. Membership renewal cycle *(LIFTED POST-BETA at 282 audit)*

- **status:** Lifted entirely to post-Beta backlog at 282 audit. The lifecycle state machine the rehearsal would walk (renewal-due, grace, lapse, reactivation, dues-change, payment-failure handling for memberships, lifecycle transition emails) is almost entirely absent from the code today. Building it pre-Beta is the wrong shape — a multi-session feature build disguised as a rehearsal. Beta 1 memberships work as one-time charges with portal-displayed tier; the full lifecycle (and a real rehearsal of it) lands post-Beta. See post-Beta backlog in `session-outlines.md`.

#### C7. Email at volume *(DROPPED at 282 audit)*

- **status:** Dropped entirely at 282 audit. The original framing pre-supposed a built-in broadcast-mail feature (mass-send, unsubscribe-token surface, Resend bounce handler, throttled queue) that doesn't exist and isn't planned — bulk emails go through Mailchimp via the existing webhook integration. Mailchimp-as-an-integration coverage absorbs into D3 (Integration retest — coordinated tire-kicking) where every external integration is exercised end-to-end. No replacement entry needed.

### Phase D — Late-cycle drills

#### D1. Scale rehearsal

- **gate:** release
- **prerequisites:** A1 (the generator carries the synthetic-data load)
- **success criterion:** At 10x assumed ceiling, no degradation visible to end-users. At 100x, identify the first three things to drag and document workarounds. At 1000x, document failure modes. Sizing doc names contact / donation / registration counts at each tier with median + p95 latency on key admin views (contacts list, donations list, search) and key public flows (page render, event registration).
- **artifact:** sizing document at `docs/runbooks/sizing-ceilings.md`.
- **estimated time cost:** 1 session.

#### D2. Compatibility cluster *(Browser bingo + Accessibility + Flaky connection — folded)*

- **gate:** release
- **prerequisites:** Phase E mobile/typography/theme items must close first per Rule 8 — specifically E5 (Mobile Type Scaling), E6 (Theme Colors Refactor), E7 (Column-Layout Mobile Collapse). Plus the remaining Phase C work must close (C3a / C3b / C3c feature halves; C4 and C5 rehearsals; #32b and #32c — note C6 was lifted post-Beta and C7 was dropped at 282 audit, so the original "C1–C7 must close" requirement no longer applies).
- **success criterion:**
  - **Browser bingo:** admin + public surfaces tested across Chrome / Safari / Firefox (current), iPad (one-version-old), Pixel (current), and Windows machine running 2-major-versions-back Chrome. Each combination passes or has documented known issues. Mobile type scaling, column collapse, and Quill-rendered content explicitly checked.
  - **Accessibility:** public site passes WCAG AA on the five seeded starter pages + admin contact form via axe-core; manual screen-reader pass through donation flow + event registration succeeds (NVDA + VoiceOver). Keyboard-only navigation works on the same flows.
  - **Flaky connection:** Chrome DevTools throttling (Slow 3G + 30% packet loss) for mobile donation submit (highest-stakes public flow). Submits succeed eventually or fail predictably; no double-charges on retry; donor sees clear connection-state feedback. Same simulation against admin contact-edit doesn't lose data. *(Original criterion named the day-of event check-in flow; that flow was lifted to post-Beta at 282 audit and replaced here with donation submit.)*
- **artifact:** compatibility matrix + WCAG-AA compliance summary + graceful-degradation runbook, all at `docs/runbooks/compatibility.md`.
- **estimated time cost:** 1–2 sessions; may split into Browser+Accessibility / Flaky-connection halves if scope inflates per Rule 11.

#### D3. Integration retest — coordinated tire-kicking *(absolute last rehearsal)*

- **gate:** release
- **prerequisites:** all of A, B, C, D1, D2 closed (D3 runs against the surface as it'll ship)
- **success criterion:** Every external integration exercised end-to-end. Integrations to walk (confirmed at 282 audit against current code): Stripe (Checkout + webhooks), Resend (transactional send), Mailchimp (list sync + inbound unsubscribe webhook), QuickBooks (donation/transaction sync via `SyncTransactionToQuickBooks` job), DigitalOcean Spaces (media + backup blob via the `spaces` s3-driver disk), Slack (admin notifications), Build Server (deploy automation), Fleet Manager mTLS surface (health + logs + backup trigger + backup blob — current contract v2.3.0). Per-integration: tire-kick steps + green criterion + red criterion documented. Audit-style per Rule 2 — small fixes absorb in-session. **Removed from earlier scope at 282 audit:** Google Calendar (integration does not exist in code; "add to calendar" public-side ICS export feature lifted to post-Beta backlog); Postmark and SES (Laravel mail drivers configured by default but no integration planned).
- **artifact:** per-integration runbook entries at `docs/runbooks/integrations/{integration}.md`.
- **estimated time cost:** 1 session.

#### D4. Test suite review — cost & shape

- **gate:** release
- **prerequisites:** all of A, B, C, D1–D3, E closed — D4 reviews the suite as it'll ship; running it before late-cycle test additions land would re-bake the same cost analysis.
- **success criterion:** Per the existing `Test Suite Audit — Cost, Coverage, and Shape` stub in `session-outlines.md` — measurement-first pass with the three rubrics (runtime budget per shape, assertion density, setup-to-assertion ratio). User-supplied surface list drives the coverage-gap phase. Outcome target: trim measurable runtime or redundancy without losing meaningful coverage. The slow group's full-suite cost is the specific question the user surfaced at session 251 close — D4 either confirms it earned its weight or drops/restructures the heaviest tests. **D4 also scopes Pest `--parallel` viability** — runs the cheap experiment (install paratest, `php artisan test --parallel --processes=4`, log failure surface) and decides whether the audit-driven trims recover enough runtime to defer parallelization, or whether to fold the test-isolation cleanup (filesystem-shared paths under `storage/app/private/`, the pre-existing `seedWidgetCollections` flake) into D4 or lift it as a follow-on per Rule 11. See the `Parallelization evaluation` sub-section in the outline stub for shape details. Carry-forward exception: if iteration friction during the Phase-C rehearsals starts costing real time before D4's slot lands, lift parallelization sooner as a standalone fix-shape session.
- **artifact:** committed baseline timing snapshot, findings-and-gaps report at `sessions/NNN-test-audit-findings.md`, applied picks (each as its own commit), updated baseline snapshot.
- **estimated time cost:** 1 session; per Rule 11, may extend if findings exceed in-session-fix capacity.

### Phase E — Demonstrability polish

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

#### E4. Stripe Checkout Branding ✅

- **gate:** release
- **prerequisites:** none; should land before C4 (donation narrative)
- **success criterion** *(closed at session 283)*: Audit at session start found the shared helper already lifted — every Stripe Checkout call site (Donation / Product / Membership / public Event / portal Event) routes through `App\Services\StripeCheckoutService::createSession`. Service extended (split into `createSession` + testable `buildParams`) to apply `custom_text.{submit, after_submit, terms_of_service_acceptance}`, `submit_type` (per-flow: 'donate' for donations, 'pay' for products / events / one-off membership; subscription-mode flows omit), `payment_intent_data.statement_descriptor[_suffix]` (payment mode only — Stripe rejects on subscription), `consent_collection.terms_of_service: 'required'` (gated on operator confirmation that ToS URL is set in Stripe Dashboard), and per-flow line-item images (per-record `event_thumbnail` / `product_image` first, fallback to per-flow defaults). New "Stripe Checkout — Branding" section on `CmsSettingsPage` (gated by `manage_cms_settings`) holds the ten new SiteSetting keys (Dashboard-confirmed toggle, three copy strings, ToS-configured gate, statement descriptor + suffix with regex validation, four image uploads). New "Stripe Dashboard branding" Onboarding Checklist item in the Optional band (manual-acknowledgement shape). Operator-facing help doc at `resources/docs/stripe-checkout-branding.md` walks both halves (Dashboard + in-app). `widget-development.md` gained a "Stripe Checkout Integration" section for widget authors. `session-outlines.md` "Default Content State" stub gained a Privacy Policy + Terms of Use as default starter pages forward note (must land before Public Website Complete milestone closes; rationale tied to Stripe Dashboard requiring both URLs). Fast Pest 2390 / 0 sequential (+19). Operator deploy-server tested with live Stripe; branding renders correctly on hosted Checkout pages. Iteration /2 of the same session lifted the Public Marketing Website track mid-session; see `sessions/tracks/public-marketing-website.md`. See `sessions/283. Stripe Checkout Branding — Log.md`.
- **artifact:** `StripeCheckoutService` extension + five wired call sites + CmsSettingsPage section + SetupChecklist item + help doc + widget-development.md addition + session-outlines.md forward note + 19 Pest tests. **Closed at session 283.**
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

#### E9. Widget Help Authoring & Help-System Integration ✅

- **gate:** release
- **prerequisites:** none
- **success criterion** *(closed at session 277)*: Resolved as "everything is just markdown flowing through the existing `help:sync` → `help_articles` → `HelpSearch` pipeline." Five widget help docs landed (`widget-bar-chart`, `widget-donation-form`, `widget-event-calendar`, `widget-event-registration`, `widget-web-form`); a canonical `widgets.md` introduces page-builder widgets and renders a sortable Alpine-driven table of the five. Frontmatter `parent: widgets` on each detail doc plus a new `parent_slug` column + breadcrumb-chain walker in `HelpArticlePage::getBreadcrumbs()` produce `Help > CMS > Widgets > Bar Chart Widget` instead of the previous flat chain. A new `search_weight` integer column (additive migration, default 0) acts as a HelpSearch tiebreaker after rank and before title; `widgets.md` gets `search_weight: 100` so searching "widget" leads with the canonical page instead of the alphabetically-first detail doc. `cms-pages.md` gained a one-paragraph callout pointing at the canonical Widgets article. Scoped `.help-page-content a` / `.help-panel-body a` link styling added to `public/css/admin.css` (blue-600 / underline light, blue-400 dark) so internal doc links read as clickable on both the standalone help-article page and the `?` slide-over flyout. Both extensions (search-weight tiebreaker, parent-chain breadcrumb) are generalisable opt-in mechanisms for any future canonical/detail help pairing. Fast Pest 2311/0 (+7 over 276 baseline). See `sessions/277. Widget Help Authoring & Help-System Integration — Log.md`.
- **artifact:** the 5 widget docs + canonical Widgets page + 2 additive `help_articles` migrations + 7 indexing/search/breadcrumb tests + cms-pages callout + help-doc link CSS rule. **Closed at session 277.**
- **estimated time cost:** 1 session.

#### E10. Full-Width Architecture Enforcement ✅

- **gate:** release
- **prerequisites:** none
- **success criterion** *(closed at session 267)*: The single `full_width` toggle split into `background_full_width` + `content_full_width` on widgets (`page_widgets.appearance_config.layout`), column layouts (`page_layouts.layout_config`), and per-type defaults (`widget_types` column-replace migration). Render pipeline collapsed the prior three full-width read sites in `AppearanceStyleComposer` + `PageBlockRenderer` into one helper with column-child clamping and `(false, true) → (true, true)` normalization. The renderer separates layout appearance from grid display: `.page-layout` (bg) > optional `.site-container` (content) > `.layout-grid` (display). Bypass audit ran across all 38 widgets and came back clean — no per-template CSS escape patterns; structural enforcement is satisfied entirely by the converged read path. Editor parity in-session absorptions: `formatLayout()` ships a composed `inline_style` field (the editor reaches gradient/image parity with the public site without duplicating `GradientComposer` in JS); both Livewire bootstrap paths (`PageBuilder.php`, `RecordDetailViewBuilder.php`) gained `appearance_config` + `inline_style` on layout items so the editor renders correctly on first load (pre-existing gap surfaced + closed); `LayoutRegion.vue` split into outer `.layout-region__container` (appearance) + inner `.layout-region__grid` (display) so the bg and content toggles act on independent elements (parallel to the public-side three-element structure). Per-type defaults flipped uniformly to `(bg:true, content:false)` per user direction (the four `fullWidth(): true` overrides on Hero / Nav / BlogListing / EventsListing dropped). Per-instance values across all three jsonb surfaces + `widget_presets` rewritten in the same migration. Permanent regression coverage at `tests/e2e/page-builder/full-width-matrix.spec.ts` (20 specs). See `sessions/267. Full-Width Architecture Enforcement — Background and Content Split — Log.md` for the full landing.
- **artifact:** the migration + composer/renderer convergence + admin-UI two-toggle inspector + Playwright matrix spec. **Closed at session 267.**
- **estimated time cost:** 1 session.

#### E11. Page Builder Focus-Scroll Clamp — closed without shipping at session 269

- **gate:** release
- **prerequisites:** none
- **success criterion** *(closed at session 269 — no code shipped)*: Verify-at-start audits surfaced two factual errors in the prompt's design-decisions block (`paneEl` is not the scroll container — the document scrolls at `window` level; no `clearSelection` path exists — clicking the canvas background does nothing today). Closing-decision evaluation surfaced four paths: (A) ship as written retargeted to `window` (predicted jitter on Mac trackpad inertia), (B) ship only the tall-widget half (drop the short-widget lock that fights inertia), (C) restructure `.preview-canvas` into a real fixed-height `overflow-y: auto` container with `overscroll-behavior: contain` first (right shape, but bigger than this session's scope), (D) don't ship — reaffirm the 204 escape hatch. User chose **D**. The 204 framing stands: the must-have scroll-to-centre-on-selection (shipped at 204) suffices; the manageable UI doesn't justify scroll-jacking. Reopen only if user testing surfaces a concrete UX problem the must-have doesn't resolve. Internal-scroll-widget audit came back clean (every widget uses `overflow: hidden`; carousels use Swiper.js gestures). See `sessions/269. Page Builder Focus-Scroll Clamp — Log.md`.
- **artifact:** none.
- **estimated time cost:** 1 session *(spent on audit + decision; no implementation)*.

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

#### E15. Table widget *(promoted from housekeeping inbox at 282 audit — pre-Public-Website-Complete)*

- **gate:** release
- **prerequisites:** none
- **success criterion:** New Table widget for the Page Builder — admin authoring affordances for row/column add + header-row toggle + basic cell alignment + optional border style; public render with responsive overflow (mobile-friendly horizontal scroll). Cell content uses the existing rich-text primitive so links/emphasis work consistently with the rest of the CMS.
- **artifact:** the widget itself.
- **estimated time cost:** 1–2 sessions.

#### E16. Header / footer defaults overhaul *(promoted from housekeeping inbox at 282 audit — pre-Public-Website-Complete)*

- **gate:** release
- **prerequisites:** none
- **success criterion:** Header default changes — no longer full-width; reasonable centered chrome. Footer gains a stacking nav option (vertically stacked links instead of horizontal drop) and the default footer template includes the new stacking nav out of the box. Site chrome defaults reviewed for sensible production-ready appearance on a fresh install.
- **artifact:** the feature itself.
- **estimated time cost:** 1 session.

#### E17. Borders pass — widget controls + columns *(promoted from housekeeping inbox at 282 audit — pre-Public-Website-Complete)*

- **gate:** release
- **prerequisites:** none; may coordinate with the Design System Editor track if it lands first
- **success criterion:** Standard widget controls and columns gain consistent border options — top/bottom default; left/right inset available. Uniform visual polish pass across widgets that surface in the page builder. If the Design System Editor track promotes pre-Beta, this entry may fold into that track's "buttons first" pass per session sequencing.
- **artifact:** the feature itself.
- **estimated time cost:** 1 session.

#### Public Marketing Website track *(lifted at session 283 mid-session; pre-Public-Website-Complete)*

Four-phase track building the five-page nonprofitcrm.com marketing site inside the product's own page builder. Distinct shape from the rest of Phase E: the deliverables are page JSON exports, screenshots, and a gap report — no production code unless a gap-report row gets lifted into a follow-on session. Track doc at `sessions/tracks/public-marketing-website.md`. Working folder at `sessions/public website/` (brief, copy, references, produced JSON, screenshots, gap report).

##### PMW1. Public Marketing Website — Audit + Home cleanup

- **gate:** release
- **prerequisites:** none; sequenced before E5 / E6 / E7 / E8 so the audit output informs their design choices
- **success criterion:** System-understanding summary written (type scales, button styles, appearance config schema, available widget inventory, sample image library). Existing home page export edited to align with the brief's section-band / padding / appearance-config-as-text-color conventions, re-imported, screenshot captured. `gap-report.md` scaffolded with first findings.
- **artifact:** the audit summary + cleaned-up `home.json` + first gap-report rows.
- **estimated time cost:** 1 session.

##### PMW2. Public Marketing Website — Pricing build-out + About extend

- **gate:** release
- **prerequisites:** PMW1 (audit output + home as established structural pattern); user exports current Pricing and About from admin into the working folder before session start
- **success criterion:** `pricing.json` complete against the structure in `copy.md`. `about.json` extended; links to in-product demo LPs (`/my-nonprofit`, `/my-nonprofit-workshop`) added. Both pages re-imported, screenshots captured. Gap report extended.
- **artifact:** `pricing.json` + `about.json` + screenshots + extended gap report.
- **estimated time cost:** 1 session.

##### PMW3. Public Marketing Website — Contact + Demo (greenfield)

- **gate:** release
- **prerequisites:** PMW1
- **success criterion:** `contact.json` and `demo.json` built greenfield. Demo includes a Form widget configured for demo-access intake (name / email / interest / message — all fields optional per copy). Both pages imported, screenshots captured. Gap report extended.
- **artifact:** `contact.json` + `demo.json` + screenshots + extended gap report.
- **estimated time cost:** 1 session.

##### PMW4. Public Marketing Website — Page-capture harness + close-out

- **gate:** release
- **prerequisites:** PMW1, PMW2, PMW3
- **success criterion:** `scripts/generate-page-screenshots.js` (or equivalent) renders each of the five marketing pages at their published URLs and writes `sessions/public website/screenshots/page-{slug}.png`. Distinct from `scripts/generate-thumbnails.js` (which targets dev-mode widget previews). All five screenshots committed. `build-summary.md` close-out doc written. Track-closure entry lands in `sessions/tracks/public-marketing-website.md`.
- **artifact:** the capture script + five committed screenshots + `build-summary.md`.
- **estimated time cost:** 1 session.

### Phase G — Test-Data Generation Infrastructure

Multi-session phase for generating adversarial fixtures the importer can be tested against. Lifted at session 256 close: the project has only two real-world data sets, both repeatedly scrubbed-and-re-imported, neither generating new findings. Real data has stopped paying for itself as a test input. Adversarial generated fixtures expand coverage without privacy concerns and let us harden the importer ahead of B2 (Onboarding rehearsal cluster) and any future importer-touching session.

Phase G's pre-Beta-1 scope is the foundational generator + a follow-on session for cross-importer pairs / replay / adversarial dedup. Format extensions beyond CSV (XLSX, JSON, source-system-specific shapes like Salesforce) live in the post-release section.

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

### Phase F — On-Demand E2E Coverage

Pre-T1 deep Playwright sweeps for surfaces that don't earn full regression-suite coverage but want a one-shot validation pass before release. Each Phase-F session lands a `tests/e2e/{area}/` spec set tagged `@on-demand`, runnable via `npm run test:e2e:on-demand`. Default `npm run test:e2e` runs exclude these specs.

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
4. ~~**A2.** Fleet Manager — node operations parity~~ *(moved to post-Public-Website-Complete; see new position 42 — Phase A infra work doesn't block the public website demo per Rule 12)*
5. **E1.** Onboarding/Install Dashboard Widget *(precedes A3 for first-run experience)*
6. ~~**A3.** Multi-node operational readiness~~ *(moved to post-Public-Website-Complete; see new position 43)*
7. ~~**A4.** DB wipe + backup recovery — runbook polish~~ *(moved to post-Public-Website-Complete; see new position 44)*
8. ~~**A5.** 2FA for admin accounts~~ *(moved to post-Public-Website-Complete; see new position 45)*
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
24. **E11.** Page Builder Focus-Scroll Clamp *(closed at session 269 — no code shipped; verify-at-start audits reaffirmed the 204-time descoping rationale; user direction "D — don't ship"; the must-have scroll-to-centre from 204 suffices)*
25. **A1e'.** PostgreSQL Major-Version Skew Fix *(closed at session 270; emergent unblocker for FM 021 manual testing 2026-05-08 — `pg_dump 17` from Trixie's `postgresql-client` meta-package produced dumps containing the PG17-only `transaction_timeout` directive that PG16 servers couldn't ingest; pinned `postgresql-client-17` in Dockerfile, bumped `postgres:17-alpine` in both compose files, added structural `PostgresVersionSkewTest`; both droplets wiped and redeployed via the destructive shortest path acceptable under pre-Beta no-live-data posture)* ✅
26. **Code Review & Cleanup — 4-session housekeeping cycle** *(sessions 271 / 272 / 273 / 274 — audit Part 1 → audit Part 2 → apply → squash; lifted at 269 close after E11 abandonment opened calendar; window covered 207 → 268 ~60 sessions of growth since last code review at 205/206 and last squash at 208; 271 ✅ closed; 272 ✅ closed; 273 ✅ closed — 6 iterations on session-273/1 consumed the entire W7/W8/W11/W12/Open-Flags backlog (Flags A / W6/B / W4/A / W10/A applied; Flag B reaffirmed won't-fix; Flag W4c/A carved out to dedicated successor session at 273-close); 274 ✅ closed — 4 commits on session-274/1 consumed Phase 3 picks (B1 bootstrap-widgets-duplicate drop = 208-deferred B4 resolved; C1 three help-doc route registrations) + Phase 4 squash (18 migrations collapsed, schema dump 3544→3915 lines, squash-note bumped 208/2026-04-22 → 274/2026-05-09) + Phase 5 obsolete-migration-test deletion; fast Pest 2166/0 (−3 from 273); Playwright 42/0 (273 baseline preserved); residual cumulative-load FilePond flake did not reappear)* ✅
27. **Rich-Text Surface Sanitization Hardening** *(session 275 — closed; carved out at 273-close per Flag W4c/A; canonical 250-time stub implementation; `App\Support\HtmlSanitizer` utility + 8 model-boundary apply sites with companion regression-guard tests + `ContentImporter::sanitizeWidgetConfig` extension + Memos Trix→Quill convergence with one-time data migration absorbed by next squash + 71-case allow-list test suite + 2 new Playwright specs; mid-session bug fix for `SanitisesRichTextCustomFields` trait FQCN-vs-codebase-convention drift; fast Pest 2277/0 (+111 over 274 baseline); Playwright 44/0)* ✅
28. **C1.** Notes Permissions (feature half) *(session 276 — closed; `edit_others_note` permission to developer, `notes_edit_only_by_creator` SiteSetting toggle on GeneralSettingsPage, `NotePolicy::update`/`::delete` extended with toggle + override gate, Timeline UI rewired to compose policy; fast Pest 2304/0 (+27 over 275), 3 new Playwright specs)* ✅
29. **E9.** Widget Help Authoring *(session 277 — closed; 5 widget detail docs + canonical `widgets.md` with sortable Alpine table + 2 additive `help_articles` migrations (`search_weight` tiebreaker, `parent_slug` breadcrumb chain) + cms-pages callout + scoped help-page link CSS; fast Pest 2311/0 (+7 over 276 baseline))* ✅
30. **C2.** Event Ticket Tiers *(session 278 — closed; shape (A) tier-canonical; `events.price` and `events.capacity` dropped; `ticket_tiers` table + `event_registrations.ticket_tier_id` FK + General-tier backfill + retroactive importer linkage in one atomic migration; Filament tier repeater on EventResource; public widget with three picker modes; per-tier capacity; `notes` field added to public form as interim workaround for per-attendee data; email-uniqueness silent-success dedup dropped; fast Pest 2341/0 (+30 over 277 baseline); +1 Playwright spec / 3 scenarios)* ✅
31. **C2a.** Multi-Quantity Event Ticket Purchase *(closed at session 279; shape (A) shipped — `event_registrations.quantity smallint default 1`; `withSum` per-tier capacity; merged checkout controllers via iteration /2 bugfix that fixed a 278-introduced 302→GET 404 dispatch bug; quantity-spinner widget with live subtotal; admin Tickets column; iteration /3 cleaned cloud-session/parallel-session/PR rules from CLAUDE.md per user evidence on parallel-workstream cost; fast Pest 2355/0 sequential)* ✅
32. **C3.** Permission audit *(closed at session 280; walked 27 Filament resources + 28 Filament pages + sub-pages + admin controllers across 8 shipped roles + unauthenticated; produced `docs/runbooks/permission-matrix.md` + `tests/Feature/PermissionMatrixTest.php` with 16 codified probes; key empirical finding — `Resource::canAccess()` runs as a Livewire mount hook so it's a universal URL gate, no bypass via no-policy-permissive-default pattern; 7 findings surfaced — 3 OK-by-design, 4 open flags for follow-on; fast Pest 2371/0 sequential)* ✅
### Public Website Complete — milestone work *(sequenced first per Rule 12; lifted at 282 audit; PMW track interleaved at 283 mid-session)*

33. **E4.** Stripe Checkout Branding *(closed at session 283; shared helper already lifted at audit start so the work was extension not lifting; five call sites wired with per-flow `submit_type` + per-record / per-flow images; CmsSettingsPage Branding section + SetupChecklist item + operator help doc + widget-dev-doc integration section; ten new SiteSetting keys; deploy-server tested with live Stripe; fast Pest 2390 / 0 sequential, +19; session also lifted the Public Marketing Website track mid-session per the 4-phase plan in `sessions/tracks/public-marketing-website.md`)* ✅
34. **PMW1.** Public Marketing Website — Audit + Home cleanup *(track lifted at 283 mid-session; sequenced before E5–E8 so its audit output informs their design choices)*
35. **E5.** Mobile Type Scaling *(precedes D2 per Rule 8 — slots pre-milestone; informed by PMW1 audit)*
36. **E6.** Theme Colors Refactor *(precedes D2 per Rule 8 — slots pre-milestone; informed by PMW1 audit)*
37. **E7.** Column-Layout Mobile Collapse *(precedes D2 per Rule 8 — slots pre-milestone; informed by PMW1 audit)*
38. **E8.** UI/UX Sprint
39. **PMW2.** Public Marketing Website — Pricing + About *(prereq: PMW1; runs after E5–E8 so the page polish lands against finalized type / color / collapse decisions)*
40. **PMW3.** Public Marketing Website — Contact + Demo (greenfield) *(prereq: PMW1)*
41. **PMW4.** Public Marketing Website — Page-capture harness + close-out *(prereq: PMW1, PMW2, PMW3)*
42. **E12.** Housekeeping Batch 2 *(absorbs the public-website-blocking subset of `sessions/housekeeping-inbox.md` items)*
43. **E15.** Table widget *(promoted from housekeeping inbox at 282 audit)*
44. **E16.** Header / footer defaults overhaul *(promoted from housekeeping inbox at 282 audit)*
45. **E17.** Borders pass — widget controls + columns *(promoted from housekeeping inbox at 282 audit)*

── PUBLIC WEBSITE COMPLETE ──

### Post-milestone — continues to Beta 1

46. **A2.** Fleet Manager — node operations parity *(moved here from position 4 at 282 audit; may be 2 sessions; FM-side resumes at FM 013+ after FM 012 absorbs v2.0.0 + v2.1.0)*
47. **A3.** Multi-node operational readiness *(moved here from position 6 at 282 audit)*
48. **A4.** DB wipe + backup recovery — runbook polish *(moved here from position 7 at 282 audit)*
49. **A5.** 2FA for admin accounts *(moved here from position 8 at 282 audit)*
50. **C3a.** Page-action accountability + audit trail *(feature half lifted at 282 audit as prereq for #32c; precedes #32c)*
51. **C3-deferred-concurrent.** Concurrent admin editing *(slim (b) refit at 282 audit; #32b. Note: session 281 was scheduled for the original (a)-scope plan but was never executed.)*
52. **C3-deferred-exposure.** Accidental public exposure *(Path-A scope refit at 282 audit; #32c; depends on C3a)*
53. **C3b.** Auto tax receipt email *(feature half lifted at 282 audit; prereq for C4)*
54. **C3c.** Comp-tier polish + skip-Stripe-on-zero-total *(feature half lifted at 282 audit; prereq for C5)*
55. **C4.** Donation-to-acknowledgment loop *(slim — depends on C3b)*
56. **C5.** Event with everything *(slim — depends on C3c)*
57. **D1.** Scale rehearsal
58. **D2.** Compatibility cluster
59. **D3.** Integration retest *(absolute last rehearsal per Rule 9)*
60. **E13.** Help docs body content
61. **E14.** Third-Party Licensing Compliance Audit
62. **G2.** Importer Test-Fixture Generator — Cross-importer Pairs, Replay, Adversarial Dedup
63. **D4.** Test suite review — cost & shape
64. **F1.** On-Demand E2E — Donation / payment-flow integration depth pass
65. **F2.** On-Demand E2E — Member portal self-service & contact-scoping security
66. **F3.** On-Demand E2E — Permission / role-gate matrix
67. **T1.** Code Review & Cleanup + Migration Squash *(terminal per Rule 10)*

── BETA 1 RELEASE ──

*(C6 Membership renewal cycle was lifted post-Beta at 282 audit; C7 Email at volume was dropped at 282 audit. See entries above and `session-outlines.md` post-Beta backlog.)*

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
- **C3 split shape.** Permission audit + concurrent + exposure originally folded. Pre-emptively split at 279-close into #32 (audit, closed by session 280) + #32b (concurrent + exposure). Further split at 280-close into #32b (concurrent editing — session 281) + #32c (accidental public exposure — session 282) when the concurrent-editing design grew substantial scope through 281 session-prep conversation. Both #32b and #32c scopes were refit at the 282 Phase C audit: session 281 was scheduled for the over-scoped (a) locking plan but never executed; #32b refit to slim (b) (last-write-wins + indicator); #32c refit to Path-A (protection + per-field docs + indicator) with accountability/notification/audit-trail lifted to a new prereq stub C3a. Session 282 itself was retconned at the audit's close from "#32c implementation" into "Phase C + D audit + planning-doc cleanup." See `sessions/282. ... — Log.md` for the audit findings and the slim entries C3a / C3b / C3c added.
- **D2 split shape.** Compatibility cluster is folded; may split into Browser+Accessibility / Flaky-connection halves if scope inflates.
- **E12 (Housekeeping Batch 2) shape.** May split per Rule 11 if any item turns out to need design-level conversation. The dev-environment orphan media cleanup item reopened at session 252 (original 247 `app:reset` fix reverted — see the reopened stub in `session-outlines.md`); the next attempt is design-shaped and likely wants its own session.
