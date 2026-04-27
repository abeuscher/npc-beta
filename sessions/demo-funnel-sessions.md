# Session Planning — Demo Funnel & Fleet Manager Kickoff

This document names and scopes four sessions arising from the demo-funnel and Fleet Manager planning conversations of April 27, 2026. The sessions are ordered for sequencing dependencies but otherwise stand alone. Each is summarized at a level sufficient to merge into the main session outlines document.

---

## Session A — Demo Funnel Architecture: Codebase Vetting

### Purpose

A focused planning session that takes the abstract demo-funnel design (public shared demo install + email-gated personal sandbox at `rectanglemachine.com`) and pressure-tests it against the actual structure, capabilities, and limitations of the codebase. The output is a refined implementation plan that the two subsequent sessions execute against, with any necessary modifications to the abstract design surfaced and resolved here rather than discovered mid-implementation.

### Position in the Roadmap

Pre-Beta-1. Highest-priority architectural planning beat after current widget primitive work. Necessary precondition for any public-facing surface at `rectanglemachine.com`. Should slot before the next housekeeping batch.

### Inputs

- The demo funnel design discussion summarized in the planning conversation: four-stage funnel (read → touch → own → pilot/paid), with stages two and three as the sandbox infrastructure scope.
- Current state of the importer (sessions 188-203), particularly the multi-source field mapping, dry-run review, and update-existing strategy.
- Current state of the build/bundle server (session 125) and its relationship to deploy infrastructure.
- Current site-settings, theme, and template architecture as it relates to per-tenant customization.
- Permissions framework (sessions 114, 117) for multi-tenant isolation review.

### Scope

The session evaluates the abstract demo design against codebase reality and produces a written plan covering:

1. **Multi-tenancy package decision.** Evaluate `stancl/tenancy` and `spatie/laravel-multitenancy` against this codebase specifically — its existing Filament admin, Livewire components, Vue page builder, build-server integration, and the way it currently wires database connections. The choice is heavily dependent on what the existing codebase can adopt cleanly without a structural rewrite. Decision documented with rationale.

2. **Public demo install architecture.** Single shared install with daily reset. Confirm the seeding strategy (dedicated `DemoSeeder` extending existing seeders? Snapshot-based reset?), the demo-mode environment flag and what it gates (banner display, write-restrictions on certain actions if any, deletion of payment-processing capability for safety), and the cron-driven reset mechanism. Decide whether the demo install runs on a separate subdomain (`demo.rectanglemachine.com`) or a path on the main marketing site.

3. **Personal sandbox provisioning model.** User-chosen or randomly-assigned subdomain pattern under `rectanglemachine.com`. Collision handling. Email-gated signup with verification before provisioning. 14-day expiry with automated teardown. Per-tenant database vs shared-database-with-tenant-scoping (this falls out of the multi-tenancy package decision in item 1). Spinup time budget.

4. **Importer integration in the sandbox flow.** The "import your existing CRM data" experience as the centerpiece of stage three. Identify any current-state importer constraints that affect this UX: large file limits, timeout behavior, batch sizes, queue worker scaling under simultaneous tenant imports. Document what needs strengthening (Session B is where the strengthening happens — this session catalogs what's needed).

5. **Abuse defense surface.** CAPTCHA placement (Cloudflare Turnstile recommended), email verification before provisioning, disposable-email blocklist, one-sandbox-per-email-per-30-days, IP and email-domain rate limiting. Establish where each of these lives in the codebase.

6. **Email handling policy in code.** Email collected for sandbox URL delivery only, deleted at sandbox expiry unless converted to pilot/paid. Newsletter signup is a separate, explicitly-checked, never-pre-filled checkbox — but ideally not on the sandbox form at all; lives in blog footer instead. Confirm where these data lifecycle rules are enforced (model events, scheduled jobs, both).

7. **Conversion path from sandbox to real install.** A "convert this to a real install" affordance in the sandbox UI that, in pilot phase, kicks off intake; in paid phase, collects payment and triggers production provisioning. Scope what data migrates from sandbox to production install.

8. **Identification of any structural blockers.** Items that must be resolved before Session B can proceed cleanly. May include refactors in the database connection layer, queue configuration, deploy pipeline, or the build server's tenant-awareness.

### Output

A single planning document at `sessions/demo-funnel-architecture-plan.md` containing the decisions above with rationale, the catalog of importer strengthening needed for Session C, and any flagged blockers. This document is the input contract for Sessions B and C.

### Out of Scope

- Implementation of any of the above. Pure planning session.
- Fleet Manager integration — that scope is owned by Sessions D and onward in the Fleet Manager repo.
- Marketing landing page design and content. Funnel infrastructure only; the LP itself is a separate concern.

---

## Session B — Public Demo Install + Release Discipline Foundation

### Purpose

Implement the public shared demo install at `demo.rectanglemachine.com` (or as decided in Session A), and *concurrently* establish the release-discipline infrastructure that public hosting requires. The two are intentionally bundled because the moment a public surface exists, the project enters a real release cycle paradigm — breaking changes and broken deploys now have external consequences.

### Position in the Roadmap

Pre-Beta-1, immediately following Session A. The public demo is the lower-complexity half of the funnel buildout and validates the basic premise (do prospects who land on the LP click through and explore?) before the heavier sandbox infrastructure of Session C.

### The Release Discipline Half

This is called out prominently and treated as load-bearing, not as a footnote to the demo work. Going public changes the project's operating mode permanently. The session establishes:

1. **Staging environment.** A separate environment that mirrors production configuration, runs migrations and deploys on every push to a staging branch, exists for the operator to smoke-test changes before they reach the public-facing demo.

2. **Deploy checklist.** A written, version-controlled checklist for deploys. Includes migration safety review (are migrations backward-compatible? do they require downtime? do they need to be paired with code changes?), asset rebuild verification, post-deploy smoke-test steps.

3. **Rollback plan.** Documented procedure for reverting a bad deploy. Includes database state considerations — which migrations are safely reversible and which aren't.

4. **Version tagging.** Every deploy produces a git tag. The `VERSION` file referenced in the Fleet Manager Phase 1 stub is written at deploy time. This is a precondition for Fleet Manager observability later.

5. **Migration safety audit.** A pass over the existing migration set to identify any that would cause downtime or data loss if run on a populated database. Flag and remediate before going public. Also: establish the rule going forward that breaking schema changes require a migration plan, not just a migration.

6. **Backup mechanism.** The Fleet Manager Phase 2 stub notes that no backup mechanism currently exists. This session is too early to scope the full Phase 2 work, but at minimum the public demo install must have a basic `pg_dump` cron to a known location. Daily, retained for seven days. Anything more elaborate stays in the Phase 2 stub.

7. **Public-facing error handling.** Default Laravel error pages replaced with branded ones. Stack traces locked to non-production. Logged errors routed somewhere the operator actually checks (Sentry, Bugsnag, log file with tail alert — operator preference, but it must exist).

### The Demo Install Half

1. Subdomain configured and SSL-terminated at the chosen pattern from Session A.
2. `DemoSeeder` (or equivalent) populated with a realistic small-nonprofit dataset — "Friends of the Hypothetical Animal Shelter" or similar, with believable donors, events, members, donations, and content.
3. Daily reset cron that drops the database, re-runs migrations, re-runs the demo seeder. Off-hours timing.
4. Demo-mode environment flag with clearly-visible banner across all admin and public pages. Banner text along the lines of "This is a demo install. Data resets daily. Ready to try with your own data? [link to sandbox signup]".
5. Demo-mode flag also disables outbound email sends (no accidental Resend transactional spam from the demo install), disables Stripe live mode (test mode only or fully disabled), and disables Mailchimp sync.
6. Auto-login as a "demo" user with full admin permissions. No login screen. Drop visitors directly into the dashboard.
7. Conversion CTA in the demo banner and in a sticky element in the page builder: "Try this with your own data — spin up your own sandbox in 60 seconds." Links to sandbox signup. (Even though Session C builds the sandbox, the link target should be wired in this session pointing to a "coming soon" page until Session C lands.)

### Out of Scope

- Sandbox provisioning. Session C.
- Marketing LP design.
- Fleet Manager integration.

---

## Session C — Personal Sandbox Provisioning System

### Purpose

Implement the email-gated, multi-tenant, 14-day-lifecycle personal sandbox system at `<chosen>.rectanglemachine.com`, with the importer integrated as the centerpiece conversion experience. This is the higher-complexity half of the funnel buildout.

### Position in the Roadmap

Pre-Beta-1, following Session B. Depends on the multi-tenancy package decision and importer-strengthening catalog produced in Session A, and on the release-discipline infrastructure established in Session B.

### Importer Readiness Gate (Hard Precondition)

Before the sandbox flow is built, the importer must be stress-tested against real-world exports from at least three competitor platforms — Neon CRM, Wild Apricot, and Bloomerang at minimum, drawn from the operator's existing client work or public sample data, sanitized as needed. The session begins with this stress-test phase and **does not proceed to sandbox infrastructure work until the importer is confirmed robust enough to handle real prospect data without first-impression-killing failures**.

The stress test produces a catalog of:

- **Large file limits.** What's the largest export the importer handles cleanly today? Where does it break? Establish a documented ceiling and any batching strategy needed above it.
- **Timeout behavior.** Which content types' imports run synchronously, which queue? Are queue workers configured with adequate timeouts? Is there a progress UI that survives long-running imports?
- **Real-world field-mapping snags.** Each competitor export's actual column shape vs. what the importer's presets expect. Gaps go to the per-platform preset stubs (sessions referenced in the existing roadmap under "Importer Source Presets").
- **Failure modes that confuse a first-time user.** Any error message, hung state, or silent failure that a sandbox visitor would interpret as "this product is broken." Each one fixed in this session before the sandbox work proceeds.

If the stress test reveals friction beyond what can be resolved within this session's budget, the session ends with a documented remediation plan and the sandbox implementation defers to a follow-on session. Better to delay than to ship a sandbox flow that breaks first impressions.

### Sandbox Implementation

Assuming the importer gate clears:

1. Multi-tenancy infrastructure per the package decision in Session A. Per-tenant database, subdomain routing, tenant resolution middleware, tenant-aware queue jobs.

2. Signup form at the chosen funnel entry point: email + optional sandbox name (auto-generated random if not provided, validated against collision). Cloudflare Turnstile. Disposable-email blocklist check. Rate limiting per IP and per email domain. Newsletter checkbox explicitly absent from this form (the project's email policy keeps newsletter signup separate, in the blog footer).

3. Email verification step: signup creates a pending tenant record but does not provision the database. Verification email sent with a one-click activation link. Link click triggers actual provisioning (database creation, migrations, demo seeder run, tenant URL email sent).

4. Spinup target: under sixty seconds from verification click to sandbox URL email delivered, ideally under thirty.

5. Sandbox dashboard: customized first-time experience. Welcome message. Prominent "Import your existing CRM data" affordance pointing into the importer wizard. Secondary affordance pointing to documentation.

6. Importer wizard, pre-existing, surfaced in a guided way for first-time users. "Drop your Neon CRM export here" as the simplest possible entry. The session tunes the wizard's first-run UX based on the readiness-gate findings.

7. Lifecycle management:
   - 12-day reminder email: "Your sandbox expires in 48 hours. [Convert to a real install] or [extend by 14 more days, one-time].".
   - 14-day expiry: tenant database dropped, email deleted unless converted, sandbox subdomain freed.
   - "Convert to real install" affordance: stub for now, links to a holding page that captures intent and notifies the operator. Full conversion flow lands when the operator is ready to onboard a paid customer.

8. Abuse-defense observability: a small admin dashboard (Filament page) showing current active tenants, signup rate, verification completion rate, and any rate-limit / blocklist hits over the last 7 days. Operator must be able to see abuse patterns at a glance.

9. Documentation: privacy policy updated with the email-handling rules. Terms of use for sandbox installs. Both linked from the signup form.

### Out of Scope

- Production-tenant provisioning (full paid-customer onboarding). Lands in a later session when the first paid customer is imminent.
- Demo install daily-reset infrastructure (already done in Session B).
- Fleet Manager integration. Sandboxes in this session are managed by the CRM itself; eventual handoff of demo-provisioning to Fleet Manager is the Fleet Manager v2 stub described below.

---

## Session D — Fleet Manager Repo: Session 001 (Foundation + Cross-Repo Protocol)

### Purpose

Begin the Fleet Manager codebase from scratch in a new repository. Establish the operating protocol that lets the CRM repo and the Fleet Manager repo evolve in parallel without quietly drifting on shared interfaces. Stand up the basic agent-polling and dashboard functionality required for v1, scoped narrowly to managing two-or-more production CRM installs from a single operator console.

### Position in the Roadmap

Pre-Beta-1, scheduled to start in the next several days per the operator's stated timing. Independent of Sessions A-C in terms of what's being built, but the cross-repo protocol established here is a precondition for the CRM-side Fleet Manager Agent Phase 1 session already stubbed in the main outlines.

### Stack Decision

The session opens with a brief evaluation: confirm Laravel/Filament as the stack, or document a reason not to. Default expectation is Laravel/Filament because (a) operator stack expertise compounds across both codebases, (b) Filament's admin shell is well-suited to operational dashboards, (c) the CRM's existing patterns for permissions, data tables, and forms transfer directly. The session does not start implementation until this is settled, but the bar for choosing differently is high.

### Cross-Repo Coordination Protocol

This is the load-bearing artifact of the session. Without it, the two repos drift silently and incidents emerge weeks later. The protocol consists of:

**1. The agent contract spec.** Lives in the CRM repo at `docs/fleet-manager-agent-contract.md`, referenced from the Fleet Manager repo by URL. Covers: endpoint paths, auth handshake (bearer token, per-install secret), request schema, response schema with all subcheck names and value shapes, version negotiation, error envelope. Carries an explicit `Contract Version` field. Includes a CHANGELOG section. The CRM repo owns this document because the CRM emits the contract; Fleet Manager consumes it.

**2. The cross-repo status reference document.** A self-describing reference document maintained at a known path in *each* repo (e.g., `docs/cross-repo-status.md`). It is mirrored, not unidirectional. The document defines its own contract; the session writes both the document and its self-definition. The self-definition specifies:

- **Trigger conditions for updates.** Close-gate of any boundary-touching session in either repo. A "boundary-touching session" is defined as: any session that modifies the agent contract, the demo-provisioning interface, the build-server interface, the version-reporting mechanism, the auth scheme, or any other artifact identified during this session as cross-cutting.
- **Required information categories.**
  - Current agent contract version.
  - Any in-flight contract change with expected resolution date and the sessions in each repo that will close it out.
  - Recently-resolved cross-cutting decisions, with date and link to the resolving session log entry. Retained for at least the last six entries; earlier entries can be archived to a CHANGELOG-style appendix.
  - Known divergences between the two repos that are pending reconciliation, with the planned reconciling session named.
  - Boundary-touching sessions queued in either repo's backlog, named with their target repo.
- **Information categories that do *not* belong in this document.**
  - Anything that lives in the formal contract spec (`fleet-manager-agent-contract.md`) — this doc references the spec but does not duplicate it.
  - Anything internal to one repo and not boundary-relevant.
  - Long-form roadmap material — that lives in each repo's main session outlines.
- **Update mechanics.** The close-gate prompt of every boundary-touching session in either repo includes "update cross-repo-status.md" as an explicit step. The opening prompt of every session in either repo includes "read the other repo's cross-repo-status.md" as an explicit step. Both repos' base prompts are amended to enforce this.

**3. Boundary-touching session naming convention.** Sessions in the CRM repo that touch the agent contract are named with a `Fleet Manager Agent — *` prefix; sessions in the Fleet Manager repo that touch the CRM-side surface are named with a `CRM Integration — *` prefix. Each marked session's base prompt is required to read the agent contract spec as a pre-implementation step.

**4. Version-bump checklist.** When the contract version bumps, both repos must update their respective references and any mocking/stubbing before the next boundary-touching session in either repo. Documented as a process rule in the protocol.

### v1 Functional Scope

Limited and concrete. The Fleet Manager v1 manages two-or-more production CRM installs and provides the operator with:

1. **Install registry.** A list of registered CRM installs with per-install configuration: URL, bearer token, environment (production / staging / development), display name, contact info, notes.
2. **Health polling.** A scheduled job that hits each install's `/api/health` endpoint at a configured cadence (default hourly, per-install override possible). Stores response history in a time-series table.
3. **Status dashboard.** Filament page listing all installs with current health status, last-checked timestamp, and a drill-down view per install showing the last 100 health checks, version history, and any logged anomalies.
4. **Failure alerting.** Email to the operator when an install fails three consecutive health checks. Resolution email when it recovers. No multi-channel alerting in v1.
5. **Per-install version awareness.** The agent reports the running CRM version; Fleet Manager surfaces this and flags installs running outdated versions.

### v1 Out of Scope (Explicitly)

- **Demo sandbox provisioning.** v2.
- **Build/bundle server integration.** v2.
- **Backup mechanism.** Owned by the separately-stubbed CRM-side Fleet Manager Phase 2 session.
- **Public status page.** Future.
- **Performance metrics, remote-action capability, multi-channel alerting.** Future.

### v2 Forward Hook (Documented Here, Not Built)

v2, scheduled post-launch of v1 but still pre-Beta-1, expands Fleet Manager into the **maker of demos**: it owns provisioning of both production CRM installs and short-lived demo sandboxes. v2 also gains awareness of the build/bundle server as a shared resource across all installs (production and demo), enabling Fleet Manager to coordinate asset builds, monitor build server health, and surface build-server status alongside install status. Architectural implications for v1: the install registry's data model should not assume "install = production install"; the abstraction should accommodate the demo-sandbox case without rewrite.

### Initial Repo Setup

1. New git repository, separate from the CRM repo.
2. Stack-of-choice scaffolded (Laravel/Filament default).
3. Base prompt template established, including the cross-repo-protocol read-step.
4. Session log structure established mirroring the CRM repo's pattern.
5. `cross-repo-status.md` written with self-definition and initial state populated.
6. The agent contract spec doc written and committed to the CRM repo (this is a cross-repo session by definition; close-gate updates both repos' status docs).
7. Empty `sessions/` directory with `001` log file template.

### Output

A working Fleet Manager v0 (scaffold + cross-repo protocol). v1 functional scope is delivered across subsequent sessions; this session's deliverable is the foundation, not the feature set.

### CRM-Side Companion Session

The existing CRM-side stub "Fleet Manager Agent — Phase 1 (CRM-Side MVP + Two-Repo Coordination Protocol)" should be re-scoped after this session closes to remove the protocol-establishment work (now owned by Session D) and focus exclusively on the CRM-side endpoint implementation. The two sessions then become a coordinated pair, with Session D opening the protocol and the CRM-side session closing the loop on the CRM's emission of the contract.

---

## Sequencing Summary

The four sessions can be sequenced two ways:

**Strict serial.** A → B → C → D. All demo-funnel work completes before Fleet Manager begins. Cleanest, but delays Fleet Manager.

**Interleaved (recommended).** A → D → B → C, with D running in parallel with B if attention budget allows. Fleet Manager v0 stands up while the public demo is being built; v1 functional scope follows as B and C land. This gets two-install management online sooner without blocking on the funnel work.

The interleaving depends on whether the operator can hold context across two repos simultaneously without thrash. Default to strict serial unless the operator is comfortable splitting attention.

---