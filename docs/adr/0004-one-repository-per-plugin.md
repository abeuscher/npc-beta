# ADR 0004 — One repository per plugin

**Status:** Accepted — 2026-08-19

## Context

With boundaries proven in-repo, the question became whether and how to realize the founding vision's separate-distribution half. The in-house precedent is the Fleet Manager relationship: two repos with a versioned contract, where the boundary — not the split — provides the context savings, and the split adds *independent change cadence*. We ruled that the project's finish line **is** the end-state architecture: thin core in this repo, every plugin a composer package in its own repository, composed by the central build.

Topology options considered: a plugins monorepo (rejected — shared tags or splitter tooling would surrender per-plugin version cadence, the main point of splitting), a git-submodule umbrella (rejected — a second pinning mechanism composer never reads, plus clone/CI friction), and individual repos (chosen). The first extraction (`2d2ad9d2`) doubled as the authoring pass for the repeatable runbook.

## Decision

**Every plugin eventually lives in its own public repository**, named `crm-plugin--{kebab-handle}`, consumed as a composer package the same way we consume Fleet Manager:

- **Tags are releases.** The lockfile pins every extracted package to an exact tag commit (git source + dist zip) — the supply-chain posture. Core bumps its pin deliberately (`composer update {package}`); nothing floats.
- **The explicit `version` field is a path-repo-only device** (deterministic resolution without git metadata inside image builds) and **drops at extraction** — under a VCS repository the tag is the version, and a disagreeing field is an install error.
- **Repos are public** so `composer install` stays credential-free in every context that builds the image (CI, e2e, deploy, a fresh machine). Choosing private would require auditing credentials into all of them.
- **Changes are contract-first**: both sides program against `docs/plugin-contract.md`, and cross-repo changes ship as plugin releases, not edits.
- **Granularity is packs, not per-widget** — plugin-hood cost is fixed overhead per package. Vertical widgets travel with their verticals; the generic remainder becomes two widget packs.
- The repeatable procedure is `docs/plugin-extraction-runbook.md`; each vertical block runs carve → package → extract end-to-end.

## Consequences

- **Independent cadence is real and already exercised**: the first cross-repo change shipped as a payments-plugin v0.2.0 release plus a small events-plugin v0.1.1 rider (`7d2dc087`) — released, tagged, and lock-pinned rather than edited in place.
- **Guards follow the code out of the repo** — every standing guard that scans `plugins/*` also scans extracted package roots, keeping zero-allowlist guarantees over code this repo no longer contains.
- Tests that live inside a plugin travel with it; core CI runs them from the vendor path (the central build runs the distribution's suites).

Costs:

- **Vendor code is never edited, so every change is a release** — even a one-line fix means tag, push, pin-bump, and a core commit. Sibling-plugin tests that encode single-plugin assumptions surface as releases too; we have already paid this once.
- **Multi-repo coordination is permanent overhead**: a contract change now fans out across N repos on N cadences, and drift between a plugin repo and the contract is only caught where CI happens to exercise it. Per-plugin CI in the plugin repos does not exist yet — until it does, an extracted plugin has no tests of its own outside this repo's vendor-path runs.
- **The extraction itself costs roughly a working day per plugin** (the runbook run), and reverses expensively — pulling a plugin back in-repo has no runbook and has never been done.
- Public repos are a disclosure decision as much as an auth convenience: everything in a plugin is world-readable the moment it is extracted.

## References

- `sessions/plugin-architecture-plan.md` — the full-decomposition stage, where the repository-topology options were weighed and settled; canonical.
- `docs/plugin-contract.md` — the external-repository section is the current authority on the extracted shape.
- `docs/plugin-extraction-runbook.md` — the procedure, with worked examples.
- Commits `2d2ad9d2` (first extraction + runbook, 2026-08-19), `085052c9`, `869ef80d` (second and third extractions), `7d2dc087` (the first cross-repo release pair).
