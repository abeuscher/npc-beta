# ADR 0005 — Two-layer activation; Laravel auto-discovery deliberately unused

**Status:** accepted
**Date:** the no-auto-discovery posture at session 382 (arc P6, owner-approved amendment); the per-install layer at session 384 (P8); the manifest layer at session 386 (P10)

## Context

Laravel's package auto-discovery (`extra.laravel.providers`) is the framework-conventional way to activate a package, and the obvious question at packaging time was why not use it. Three guarantees in this codebase are load-bearing and all keyed on an *ordered, explicit* provider list: the remove-the-line vanish guarantee (deleting one entry removes a plugin's entire composed surface), the registries-before-plugins binding order (core sockets exist before any plugin registers into them), and plugin-boot-before-core-routes ordering (a plugin GET route must register ahead of the page-slug catch-all). Auto-discovery would move activation to composer edits and surrender ordering to installation order.

Separately, the business premise (niche = manifest; per-install feature dials) needs two different notions of "has this plugin": what is *in the image*, and what is *on for this install*.

## Decision

Activation is **two-layered**, and auto-discovery is banned:

- **Installed** = the image's composed set, declared in **`distribution.json`** — per-entry provider FQCN, honest source kind (`folder` / `path` / `vcs`), package + bound constraint. **Array order is provider load order.** `config/plugins.php` is a *generated artifact* of the manifest (`plugins:manifest-sync`, byte-stable, header-marked) that the runtime loader still reads — no bootstrap-time JSON parsing, and remove-the-entry ≡ remove-the-line by construction.
- **Enabled** = installed minus **`PLUGINS_DISABLED`** — a comma-separated list of derived kebab handles, env-backed (registration happens before the database is guaranteed to exist, and `.env` is what Fleet Manager's config-push channel delivers per install). The filter is **subtraction only** — never reordering, never adding. Unknown handles fail the bootstrap loudly, naming the bad handle and the valid set.
- **No package declares `extra.laravel.providers`** — guard-enforced for in-repo and extracted packages alike. Handles are derived from the provider FQCN, not declared — no registry to drift.
- `composer.json` and the Dockerfile stay hand-maintained, pinned to the manifest by a standing guard (regeneration idempotence, lock-pinning per source kind, no unmanifested plugin surfaces).

## Consequences

- **One superset image serves many compositions.** Which plugins run is a per-install `.env` decision (restart + `migrate` on enable — the recorded flip runbook), verified end-to-end by the session-384 one-image run. This is the cheap, common path the niche premise rests on.
- **Runtime-disabled ≡ strip-the-line by construction** — the flag subtracts at the exact seam the removal fixtures prove, so one set of vanish tests covers both mechanisms. Disabled is inert: routes gone, no schedule, widget rows dropped on sync, **schema and data kept** (ADR 0003).
- A maintainer familiar with Laravel conventions will be tempted to "fix" the missing auto-discovery; this record and the guards exist so that fails loudly instead of silently.
- Accepted costs: every new packaged plugin touches manifest + composer + Dockerfile in the same commit (guard-enforced agreement), and the generated config file must never be hand-edited.
- Stage E's per-distribution composer sets (true code absence per niche) layer on the same manifest authority — recorded boundary, deliberately not built yet.

## References

- `docs/plugin-contract.md` surface 1 and § The distribution manifest — current authority.
- Session 384 log (the superset-image verified run); session 386 log (the manifest layer).
