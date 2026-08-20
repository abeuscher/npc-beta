# ADR 0004 — One repository per plugin, composed by a central build

**Status:** accepted
**Date:** session 385 (2026-08-19) — the reframe ruling and the Stage D topology decisions; first extraction same session

## Context

With boundaries proven in-repo (Stages A–B), the question became whether and how to realize the founding vision's separate-distribution half. The in-house precedent is the Fleet Manager relationship: two repos with a versioned contract, where the boundary — not the split — provides the context savings, and the split adds *independent change cadence*. At session 385 the owner ruled that the project's finish line **is** the end-state architecture: thin core in this repo, every plugin a composer package in its own repository, composed by the central build (there is no launch gate ahead of it).

Topology options considered: a plugins monorepo (rejected — shared tags or splitter tooling would surrender per-plugin version cadence, the main point of splitting), a git-submodule umbrella (rejected — a second pinning mechanism composer never reads, plus clone/CI friction), and individual repos (chosen).

## Decision

**Every plugin eventually lives in its own public repository**, named `crm-plugin--{kebab-handle}`, consumed as a composer package FM-style:

- **Tags are releases.** The lockfile pins every extracted package to an exact tag commit (git source + dist zip) — the supply-chain posture. Core bumps its pin deliberately (`composer update {package}`); nothing floats.
- **The explicit `version` field is a path-repo-only device** (deterministic resolution without git metadata inside image builds) and **drops at extraction** — under a VCS repository the tag is the version, and a disagreeing field is an install error.
- **Repos are public** so `composer install` stays credential-free in every context that builds the image (CI, e2e, deploy, a fresh machine). Choosing private would require auditing credentials into all of them.
- **Changes are contract-first**: both sides program against `docs/plugin-contract.md`, and cross-repo changes ship as plugin releases, not edits.
- **Granularity is packs, not per-widget** — plugin-hood cost is fixed overhead per package. Vertical widgets travel with their verticals; the generic remainder becomes two widget packs.
- The repeatable procedure is `docs/plugin-extraction-runbook.md`; each vertical block runs carve → package → extract end-to-end.

## Consequences

- **Independent cadence is real and already exercised**: the first cross-repo change (session 389) shipped as `crm-plugin--payments` v0.2.0 plus a small `crm-plugin--events` v0.1.1 rider — released, tagged, and lock-pinned rather than edited in place.
- **Vendor code is never edited.** A needed change in an extracted plugin is a release, even a one-line one. Sibling-plugin tests that encode single-plugin assumptions surface as releases too (the v0.1.1 lesson).
- **Guards follow the code out of the repo** — every standing guard that scans `plugins/*` also scans extracted package roots, keeping zero-allowlist guarantees over code this repo no longer contains.
- Tests that live inside a plugin travel with it; core CI runs them from the vendor path (the central build runs the distribution's suites). Per-plugin CI in plugin repos is deliberately deferred.
- Accepted costs: multi-repo coordination overhead; release ceremony for small changes; the extraction runbook must be re-run per plugin (~1 session each, in practice).

## References

- `sessions/plugin-architecture-plan.md` § Stage D (the reframe, settled decisions 1–4) — canonical.
- `docs/plugin-contract.md` § External repo (Stage C-lite) — current authority on the extracted shape.
- `docs/plugin-extraction-runbook.md` — the procedure, with worked examples.
