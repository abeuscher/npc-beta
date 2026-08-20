# ADR 0005 — Two-layer activation without auto-discovery

**Status:** Accepted — 2026-08-18

## Context

Laravel's package auto-discovery (`extra.laravel.providers`) is the framework-conventional way to activate a package, and the obvious question at packaging time was why not use it. Three guarantees in this codebase are load-bearing and all keyed on an *ordered, explicit* provider list: the remove-the-line vanish guarantee (deleting one entry removes a plugin's entire composed surface), the registries-before-plugins binding order (core sockets exist before any plugin registers into them), and plugin-boot-before-core-routes ordering (a plugin GET route must register ahead of the page-slug catch-all). Auto-discovery would move activation to composer edits and surrender ordering to installation order.

Separately, the business premise (niche = manifest; per-install feature dials) needs two different notions of "has this plugin": what is *in the image*, and what is *on for this install*. The no-auto-discovery ruling landed with the first package (`97097961`, guard `d94a93ac`); the per-install layer (`b2bc6bab`) and the manifest layer (`92a07408`) completed the model.

## Decision

Activation is **two-layered**, and auto-discovery is banned:

- **Installed** = the image's composed set, declared in **`distribution.json`** — per-entry provider FQCN, honest source kind (`folder` / `path` / `vcs`), package + bound constraint. **Array order is provider load order.** `config/plugins.php` is a *generated artifact* of the manifest (`plugins:manifest-sync`, byte-stable, header-marked) that the runtime loader still reads — no bootstrap-time JSON parsing, and remove-the-entry ≡ remove-the-line by construction.
- **Enabled** = installed minus **`PLUGINS_DISABLED`** — a comma-separated list of derived kebab handles, env-backed (registration happens before the database is guaranteed to exist, and `.env` is what Fleet Manager's config-push channel delivers per install). The filter is **subtraction only** — never reordering, never adding. Unknown handles fail the bootstrap loudly, naming the bad handle and the valid set.
- **No package declares `extra.laravel.providers`** — guard-enforced for in-repo and extracted packages alike. Handles are derived from the provider FQCN, not declared — no registry to drift.
- `composer.json` and the Dockerfile stay hand-maintained, pinned to the manifest by a standing guard (regeneration idempotence, lock-pinning per source kind, no unmanifested plugin surfaces).

## Consequences

- **One superset image serves many compositions.** Which plugins run is a per-install `.env` decision (restart + `migrate` on enable — the recorded flip runbook), verified end-to-end by a one-image run across full and plugin-disabled compositions (`af40965d`). This is the cheap, common path the niche premise rests on.
- **Runtime-disabled ≡ strip-the-line by construction** — the flag subtracts at the exact seam the removal fixtures prove, so one set of vanish tests covers both mechanisms. Disabled is inert: routes gone, no schedule, widget rows dropped on sync, **schema and data kept** (ADR 0003).

Costs:

- **We are permanently off the framework's paved road.** Every engineer who knows Laravel will expect auto-discovery and find it banned; this record and the guards exist so the inevitable "fix" fails loudly instead of silently. Framework upgrades that lean harder on discovery will need review against this posture.
- **Three hand-maintained surfaces must agree** — manifest, `composer.json`, Dockerfile — on every plugin addition or source-kind change, in the same commit. The guard makes disagreement loud, but the ceremony is real.
- **A generated-but-committed config file is a standing foot-gun**: hand edits are wasted work that fails CI, and the file's existence invites them.
- Disabled-but-installed code still ships in the image. True per-composition code absence needs per-distribution composer sets — a recorded boundary, deliberately not built.

## References

- `docs/plugin-contract.md` surface 1 and § The distribution manifest — current authority.
- Commits `97097961` + `d94a93ac` (first package, no-auto-discovery guard, 2026-08-18), `b2bc6bab` + `af40965d` (per-install layer + one-image proof, 2026-08-19), `92a07408` (the manifest layer, 2026-08-19).
