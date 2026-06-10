# Track: Fleet Data Hygiene (Cruft Prevention, Detection & Bounded Audit)

Prevent and detect the silent accumulation of derived/cruft data on live instances — orphaned event/post landing pages, leftover scrub data, media creep — under a hard privacy boundary on what Fleet Manager may see. Cross-repo (CRM + FM). Scoped during session 349 (2026-06-09) off the orphaned-event-landing-pages bug; **not started**, **pre-release priority** with a couple of items ahead of it.

This doc carries: the premise, the **privacy boundary** (load-bearing), the prevent-vs-detect split, the component design, the forward plan, and status.

---

## Status snapshot

**Last update:** 2026-06-10 (Phase 2 CRM half shipped at session 353).

**Status: PHASE 1 ✅ CLOSED (352) — PHASE 2 CRM HALF ✅ SHIPPED (353); FM-side absorption pending.** Phase 1 shipped the in-repo prevention + detection half (the CI keystone scrub-residue test + the node-local `app:data-hygiene` audit on a build-once `DataHygieneAudit` core whose count-only `counts()` is the Phase-2 seam). **Phase 2 (353, boundary-touching)** shipped the CRM half of FM visibility: a count-only `data_hygiene` `/api/health` subcheck reading `counts()` — additive contract bump **v2.3.0 → v2.4.0**; `value` is the four-category non-PII breakdown; **informational, never red, excluded from the worst-of overall status** (benign cruft never drags node health); counts cached ~10 min (`Cache::remember`) to stay cheap on the polled endpoint, sidestepping the scheduler-runner gap; **counts only over the wire** — no raw records, the `--deep` mode stays node-local. **Remaining Phase-2 work is FM-side** (handed off via the FM repo's `sessions/data-hygiene-handoff-from-crm.md` + the Cross-Repo block): consume the subcheck (refresh contract cache to v2.4.0, parse the object `value`, honor the excluded-from-worst-of semantics) and build the manual per-node **"maintenance/auditable" toggle** gating any data-touching op. The **Phase-2 phase-expiry compression waits until the FM side lands** (the phase isn't complete until both halves ship). Detail in *Phase Retrospectives* below; full CRM landings in `sessions/352. * — Log.md` and `sessions/353. Fleet Data Hygiene — FM Visibility — Log.md`.

**Trigger:** pre-release — prevention (Phase 1) + CRM-side FM visibility (Phase 2 CRM half) are in. The FM-side Phase-2 absorption (toggle + subcheck consumption) and Phase 3 (remediation, future) are the remainder.

---

## The privacy boundary (load-bearing — set by the owner at 349, governs all FM work here)

Recorded in agent memory as `project-fleet-data-audit-privacy-boundary`. Verbatim intent:

- **Fleet Manager must NOT be built to read raw node data** — even though mTLS makes it technically possible. Do not build a raw-data / row-dumping endpoint into the FM↔node contract. Capability existing is not licence to expose it.
- **Only aggregate counts cross the wire.** The signal FM consumes is count-only (e.g. "12 orphan event pages, 0 scrub records, 3 orphan media") — non-PII, safe on every node, ungated. Raw rows/contents never traverse the FM API.
- **Deep audit (reading actual records) is node-local + consent-gated.** Run via artisan on a node the owner controls (the owner's own test nodes — consent self-granted). Not an FM-initiated feature.
- **The critical near-term deliverable is a per-node "maintenance / auditable" toggle in FM** — manual, user-editable, default off. It gates any data-touching operation (deep audit, cleanup, remediation). It sits there as a guardrail against accidental intrusion; nothing intrusive runs against a node until the owner flips it, with the node owner's consent. Defense-in-depth option: the node *also* refuses gated ops unless a node-side flag is set.

---

## The prevent-vs-detect split (the key framing)

Two needs that feel like one but need separate mechanisms:

1. **Prevention ("this can't happen before go-live")** — stop code that leaks cruft from ever shipping. A **CI test-suite** job. Runs on every deploy, fails the build on a broken invariant. Cannot see live data (fresh test DB) — and doesn't need to.
2. **Detection ("what's actually piled up on instance #4")** — an audit over real runtime state. CI literally can't answer it. Needs a **runtime audit**, surfaced via CLI (node-local) and/or counts to FM.

Neither alone suffices: CI proves the cleanup *code* is correct; the audit tells you what's *already* accumulated.

---

## Component design

**A. CI keystone test — prevention (in-repo, no boundary).** One high-leverage, future-proof test: snapshot every relevant table's row count + media count → generate a full scrub spread (events, posts, products, contacts, orgs, donations, media…) → `RandomDataGenerator::wipe()` → assert **every count returns to baseline and zero derived pages/media remain.** Any future scrub-generating-but-not-cleaning code fails it automatically — catches the whole class, not just today's bug. Plus targeted cascade tests (delete-event→page already landed at 349; add delete-model→media, etc.).

**B. Node-local audit + the count-only signal (in-repo).** An `app:data-hygiene` artisan command reporting, for the box it runs on: orphan event pages (type=event, no event), any `source=scrub` records still present (pure cruft on a live box), orphan media (reusing the `media:prune-orphans` detection), media rows whose owning model is gone. Splits two ways: a **count-only** computation (feeds the health subcheck — safe) and a **deep mode** (actual records — node-local, toggle/consent-gated). Pairs with the prune commands for cleanup.

**C. Fleet Manager visibility (boundary, cross-repo).** Surface B's counts as a new **count-only** `data_hygiene` subcheck in `/api/health` (slots into the existing subcheck shape; FM already polls every install). Plus the **per-node maintenance/auditable toggle** in FM gating any data-touching op. Contract-version bump + cross-repo coordination (Two-Repo Coordination Protocol). Built on B's audit logic — so B comes first regardless.

---

## Forward plan (sequenced)

1. **Phase 1 — CRM prevention + detection ✅ closed (session 352, one session).** The CI keystone scrub-residue test + the `app:data-hygiene` audit shipped. Detail in *Phase Retrospectives*; full landing in `sessions/352. *— Log.md`.
2. **Phase 2 — FM visibility (boundary).** Count-only `data_hygiene` health subcheck (contract bump) + the FM per-node maintenance toggle. Cross-repo. **CRM half ✅ shipped at session 353** (the subcheck, additive v2.3.0 → v2.4.0); **FM-side absorption pending** (consume the subcheck + build the maintenance toggle — handed off via the FM repo's `data-hygiene-handoff-from-crm.md`).
3. **Phase 3 — bounded remediation (future).** Cleanup-on-upgrade via the established one-time-repair-migration pattern (shipped alongside the bug fix that caused the cruft, runs once on deploy), plus a toggle-gated remediation trigger. Never silent destructive auto-delete on every deploy.

---

## Phase Retrospectives

### Phase 1 — CRM Prevention + Detection ✅ (session 352, one session)

Non-boundary, v2.3.0, no schema. Built on the 349 down-payment (event-page cause fix + the `pages:prune-orphan-events` / `media:prune-orphans` commands).

- **Prevention — the keystone scrub-residue test.** `RandomDataGeneratorServiceTest`'s "KEYSTONE …" case: snapshot every scrub-touched table + the `media` count → `generate()` a full spread (+ attach owned media) → `wipe()` → assert all counts return to baseline (raw `DB::table` counts, so even soft-deleted residue fails). Fast (~0.8s, `Queue::fake()` skips conversions) → in the primary fast suite as the launch gate. **Revert-checked** (fails *"residue left in media 6≠0"* without the fix). Catches the whole generates-but-doesn't-clean class.
- **The in-scope `wipe()` fix it surfaced.** `wipe()` mass-deleted scrub events/products via the query builder, bypassing Spatie's per-model media teardown → orphan media rows + CAS files. Fixed with a `wipeEach(Builder)` per-model-delete helper (posts were already clean via `wipeScrubPages()`). Plus delete-model→owned-media cascade tests.
- **Detection — `DataHygieneAudit` + `app:data-hygiene`.** Build-once core: `counts()` = the four-category non-PII aggregate (`orphan_event_pages` / `scrub_records` / `orphan_media_dirs` / `dead_owner_media`) — the Phase-2 seam; `--deep` records mode is node-local. The two prune commands refactored onto its detection (one source of truth).
- **`media:prune-dead-owner` (new).** Spatie's `media-library:clean --delete-orphaned` crashes on this schema (`operator does not exist: character varying = uuid` — varchar `media.model_id` vs uuid owner PKs). The new command + a hardened pull-ids-compare-in-PHP `deadOwnerMedia()` sidestep it. Dry-run default; soft-deleted owners treated as alive.
- **Live (nphelper):** the audit surfaced 252 orphan event pages (cleaned) + orphan media dirs. A separate 404 responsive-image incident (1350/1510 conversions claimed-but-absent) was diagnosed as a DB/media-tree out-of-sync load — **not** the hygiene cleanup, **not** import/export, **not** a code bug — and fixed with `media-library:regenerate --only-missing --force`.
- **Carried forward:** dead-owner cleanup on live (`media:prune-dead-owner --force`, once 352 deploys); the varchar-`model_id`-vs-uuid mismatch (worked around, not migrated — see `docs/app-reference.md`); the collection-item-media export-portability gap (noted for a later session).

---

## Key decisions & insights (carry forward; don't re-derive)

- **The privacy boundary above is non-negotiable** — design the FM surface so it *can't* read data, not so it's *policied* not to.
- **Prevention ≠ detection.** CI is the launch gate; the runtime audit is the live-state answer. Build both.
- **The audit logic is the shared core** — one computation powers the CLI report (now), the count-only health subcheck (later), and the gated deep mode. Build it once.
- **Cleanup is opt-in / one-off, never silent-auto.** One-time repair migrations shipped with the fix; toggle-gated remediation otherwise.
- **Originating bug:** session 349 — 252 orphaned `type=event` landing pages on the local DB (scrub events mass-deleted by `RandomDataGenerator::wipe`, their `source='human'` pages left behind). Same cruft on several live instances. See `sessions/349. Table Widget — Log.md` (event-page section) when written.
