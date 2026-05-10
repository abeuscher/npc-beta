# Session-Document Templates — Rationale

This doc is the maintenance log for the three session-document templates (`template-base-prompt.md`, `template-session-prompt.md`, `template-session-log.md`). It captures **why** the templates look the way they do, **what was changed and why** at each recalibration pass, and **what was considered and explicitly not changed** so future-self doesn't relitigate.

The templates are the system that produces the work. Treat changes to them as maintenance, not features.

---

## Recalibration pass — session 276.5 (2026-05-10)

### Triggering observation

User at session 276: "I am on session 276. The app is not as loose as it once was and the phases are pretty well designated at this point. I am trying to stop just looking for 'advance' and 'yes' prompts everywhere it's becoming robotic again."

The dynamic: guardrails written when the project was loose (session 30, session 50) had become ritual rather than load-bearing. The agent surfaced every micro-decision because the template told it to; the user clicked "yes" because the decisions were obvious given established convention; the loop produced motion without value.

The recalibration is not about removing guardrails — it's about distinguishing load-bearing rules (close gate, manual-testing pause for visual/UX judgment, FM cross-repo flag) from scar tissue (re-read templates every session, summarise-and-confirm-ready-to-proceed, pause-and-ask on decisions with obvious answers).

### Worth user time vs not — the calibration

- **Worth:** design decisions, real conflicts, actual session breaks, UAT, review of new features, planning for the roadmap.
- **Not worth:** clicking yes on questions whose answer is obvious from surrounding code, advancing through phases that don't need explicit advancement, confirming readiness to proceed after a session prompt the user already approved.

### What changed

#### `template-base-prompt.md`

- **Preamble step 1 (re-read templates):** softened from "Read the three templates" to "Re-read only if format has changed or you're uncertain about a structural detail; stable as of session 276." The "templates take precedence over previous logs" framing stays — that's still load-bearing — but the rote re-read is gone.
- **Preamble step 3 (new) — plan-backed pointer:** inserted "If this session executes a `release-plan.md` entry, that entry is canonical for scope, success criterion, prerequisites, and artifact. The session prompt is a delta against the plan entry, not a replacement for it." Codifies the relationship that already existed in practice and lets the session prompt template shrink.
- **Preamble step 7 (was step 6, summarise + confirm ready):** softened from "Summarise your understanding and confirm you are ready to proceed" to "Note any drift in a brief work-log entry; proceed unless something requires a decision per the drift and decision-threshold rules below." The rubber-stamp ritual is gone; the surface area for genuine flags stays.
- **"Pause and ask" rule:** appended a clause pointing at the new drift and decision-threshold rules. Did not delete — still load-bearing as the safety net; the appended clause directs interpretation toward the new calibration.
- **New rule — Adapt to drift; don't ask about it.** Landed verbatim from the planning brief. Targets the failure mode where the agent surfaces every field-name or signature mismatch as a decision point.
- **New rule — Decision threshold scales with project maturity.** Landed verbatim. Targets the failure mode where the agent asks about local naming/placement decisions whose answer is obvious from surrounding code.
- **Close gate Phase 1 — collapsed:** removed the "user reviews changes and brings up the next session's prompt when ready" beat. The agent now drafts the next session's documents directly after implementation and manual testing, then stops. The user reviews the work and the next session's drafts together, and initiates close when ready. One fewer explicit-go-ahead; the meaningful gate (user initiates close) stays. Phase 2 — the actual close steps — is untouched.

#### `template-session-prompt.md`

- **Restructured around plan-entry-pointer shape with soft two-mode framing.** The template now opens with: pick plan-backed (the common case — point at the `release-plan.md` entry, write only the deltas) or stub-driven/emergent (use the fuller shape with explicit phases). The two-mode framing is in the preamble, not as alternate templates — session-writer judgment picks.
- **Sections reshuffled:** Plan reference (new), Session-specific deltas (new), Open questions to resolve at session start (replaces "Design decisions resolved before starting" — narrower, less bookkeeping), Phases (now stub-driven only), Out of scope, Testing, Closing steps.
- **"Security checklist" section dropped** from the prompt template entirely. The corresponding section in the log template is rephrased to surface active concerns only (see below). The session-prompt-level checklist was rote at session 276; the log-level prose forces actual thinking.

#### `template-session-log.md`

- **"Security checklist — verified" → "Security considerations":** replaced the box-check shape with a short prose statement of what was reviewed on the security axis this session. Sessions with no security surface write "None beyond standard convention." Sessions with real security work get the space to explain what they checked. Forces thinking over ticking.
- **Everything else untouched.** The log shape ("What was built" + Phase narrative, Schema changes, Production deployment notes, Deferred decisions, Test result) is doing real work — it's the source of truth future-self reconstructs decisions from. Recent logs (271–275) show the pattern working at scale. Do not flatten to a TL;DR shape in future passes.

#### `CLAUDE.md`

- **Added fractional-session convention** to the Git Workflow section. Parallel / out-of-flow sessions (maintenance passes, cloud-based ad-hoc work) use `session-NNN.M/N` keyed to the in-flight session — e.g. `session-276.5/1` runs alongside session 276. The fraction signals "parallel to NNN, not in the release plan, not part of the main numbered sequence."
- **Added PR-at-close convention for fractional/browser sessions.** Because these run out-of-flow and outside the normal session-close cadence, they end with a PR opened against `main` so the user can track and merge them through the same review surface as numbered sessions. Numbered sessions still follow the standard "push branch, user opens PR if they want one" pattern.

### What was considered and explicitly not changed

Preserving the reasoning so future-self doesn't relitigate.

- **Close gate Phase 2 steps** (log file, completed-sessions update, outlines update, track doc update, phase-expiry compression, archive previous session, commit and push). This is the macro-checkpoint that prevents the agent from pipelining commits before review. The whole reason the gate exists. **Untouched.**
- **Manual-testing pause language** ("Pause for manual testing only when human judgment is required"). This is the rule that pulls the user in for visual/UX judgment — one of the rules genuinely earning its keep. **Untouched.**
- **Fleet Manager cross-repo flag.** Load-bearing and formal — prevents two-repo coordination drift at the FM contract surface. The rule is dormant until the agent surface exists; it self-activates the moment it does. **Untouched.**
- **"Verify objective outcomes yourself; pull the user in for judgment"** rule. One of the rules still earning every word. The importer / Playwright pattern is the model. **Untouched.**
- **Style rules section** (follow conventions, no docstrings on existing code, prefer editing existing files, breadcrumbs, portal security). None of these are the source of the robotic-yes loop. **Untouched.**
- **Portal security rule.** Strict scoping of portal routes and queries to `contact_id`. **Untouched.**
- **Per-session log file shape and depth.** The detail is the source of truth. Future-self at session 350+ reconstructs decisions from these logs. Do not simplify to a TL;DR shape in future passes.
- **Track-doc / release-plan / session-outlines separation.** Working as intended; templates point at the right place. **Untouched.**
- **Carve-out shape as a procedural pattern.** Already established (sessions 207, 275 precedents); templates don't need to explain it but must not undermine it. **Not added to templates** — it's a procedural pattern that lives in the session logs and the track docs, not in the templates.
- **Operational Process-rules bullets** (migrations via Docker, schema doc updates, Postgres restart, fast-suite run, front-end build matrix, test classification, Playwright artifact hygiene). All operational reference, all still accurate, all still useful. **Untouched.**

### Provenance of the two new rules

The drift rule and decision-threshold rule came out of a planning conversation at session 276 about the robotic-yes failure mode. The wording is tuned — landed verbatim from the planning brief. Future passes that want to rephrase should capture in this doc what the rephrase is improving.

### Forward signal — when to recalibrate next

Cadence trigger: **approximately every 50 sessions OR forcing function — whichever comes first.** Same shape as the Code Review track.

Concretely: revisit no later than **session 326**. Earlier if a forcing function appears (new track shape, new repo, new collaboration pattern, observed return of the robotic-yes loop or its inverse — agent under-surfacing things that needed user input).

The questions to ask each rule, section, or template element at the next pass:

1. Is this still load-bearing at the current session number? Would removing it cause real damage, or is the damage hypothetical?
2. What failure mode does it prevent? Name it concretely. If you can't, the rule is suspect.
3. Has the project's maturity made the failure mode less likely? A rule that prevented a real session-30 failure mode may be preventing nothing at session 326.

If a rule passes all three, keep verbatim. If it fails one, soften or rephrase with rationale captured here. If it fails two or more, cut with rationale captured here.

Items the user holds veto on (do not land changes without explicit sign-off):

- Anything in the close gate Phase 2.
- The FM cross-repo flag.
- The manual-testing pause language.
- The portal security rule in Style rules.
