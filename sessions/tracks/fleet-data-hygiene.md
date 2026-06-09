# Track: Fleet Data Hygiene (Cruft Prevention, Detection & Bounded Audit)

Prevent and detect the silent accumulation of derived/cruft data on live instances — orphaned event/post landing pages, leftover scrub data, media creep — under a hard privacy boundary on what Fleet Manager may see. Cross-repo (CRM + FM). Scoped during session 349 (2026-06-09) off the orphaned-event-landing-pages bug; **not started**, **pre-release priority** with a couple of items ahead of it.

This doc carries: the premise, the **privacy boundary** (load-bearing), the prevent-vs-detect split, the component design, the forward plan, and status.

---

## Status snapshot

**Last update:** 2026-06-09 (scoped during 349).

**Status: NOT STARTED — pre-release priority, a couple items ahead.** A real problem confirmed on several live instances (no real events/posts, yet undeleted scrub-derived pages accumulating; media creep separately known).

**Down-payment already made (session 349):** the *cause* of the event-page leak was fixed (landing pages now inherit the event's `source`; `EventObserver::deleted` cascades the landing page) and two cleanup commands exist — `pages:prune-orphan-events` and the pre-existing `media:prune-orphans` (both dry-run-default, `--force` to delete). What's missing is the *systemic guard* (so it can't regress before go-live) and the *visibility* (what's actually on each live box).

**Trigger:** pre-release — must land before go-live (the prevention half is a launch gate). The owner has a couple of in-flight items ahead of it.

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

1. **Phase 1 — CRM prevention + detection (in-repo, no boundary; the launch gate).** The CI keystone scrub-residue test + targeted cascade tests; the `app:data-hygiene` audit command (count-only + deep modes). Run the audit on the live boxes to see the damage; clean with the prune commands. ~1–2 sessions.
2. **Phase 2 — FM visibility (boundary).** Count-only `data_hygiene` health subcheck (contract bump) + the FM per-node maintenance toggle. Cross-repo. ~1 CRM session + FM-side absorption.
3. **Phase 3 — bounded remediation (future).** Cleanup-on-upgrade via the established one-time-repair-migration pattern (shipped alongside the bug fix that caused the cruft, runs once on deploy), plus a toggle-gated remediation trigger. Never silent destructive auto-delete on every deploy.

---

## Key decisions & insights (carry forward; don't re-derive)

- **The privacy boundary above is non-negotiable** — design the FM surface so it *can't* read data, not so it's *policied* not to.
- **Prevention ≠ detection.** CI is the launch gate; the runtime audit is the live-state answer. Build both.
- **The audit logic is the shared core** — one computation powers the CLI report (now), the count-only health subcheck (later), and the gated deep mode. Build it once.
- **Cleanup is opt-in / one-off, never silent-auto.** One-time repair migrations shipped with the fix; toggle-gated remediation otherwise.
- **Originating bug:** session 349 — 252 orphaned `type=event` landing pages on the local DB (scrub events mass-deleted by `RandomDataGenerator::wipe`, their `source='human'` pages left behind). Same cruft on several live instances. See `sessions/349. Table Widget — Log.md` (event-page section) when written.
