# Architecture Decision Records

Point-in-time records of the load-bearing architectural decisions in this codebase — the **why** behind the shapes a reader finds in the code. Each record is immutable once accepted; if a decision is revisited, a new ADR supersedes the old one and both note the link.

These records complement, and never replace, the living documents:

- `docs/plugin-contract.md` — the current authoritative shape of every surface core publishes to plugins.
- `sessions/plugin-architecture-plan.md` — the architecture plan and its question dispositions.

An ADR records what was decided and why *at the time*; the contract records what is true *now*. When they appear to disagree, the contract wins and the ADR is history.

## Index

| ADR | Title | Accepted |
|---|---|---|
| [0001](0001-widget-templates-are-pure-renderers.md) | Widget templates are pure renderers behind declared data contracts | 2026-08-18 |
| [0002](0002-thin-core-everything-else-is-a-plugin.md) | Thin core: everything else is a plugin | 2026-08-18 |
| [0003](0003-plugin-owned-data-in-plugin-owned-tables.md) | Plugin-owned data in plugin-owned tables | 2026-08-18 |
| [0004](0004-one-repository-per-plugin.md) | One repository per plugin | 2026-08-19 |
| [0005](0005-two-layer-activation-without-auto-discovery.md) | Two-layer activation without auto-discovery | 2026-08-18 |
| [0006](0006-cross-plugin-communication-through-core-events.md) | Cross-plugin communication through core-owned events | 2026-08-18 |
| [0007](0007-shared-front-end-dependencies-are-core-owned.md) | Shared front-end dependencies are core-owned | 2026-08-20 |

## Format

Status / Context / Decision / Consequences / References. Status is one word and a date; provenance lives in Context and References (dates and commit SHAs, not internal session labels). Consequences name the costs as plainly as the benefits. Keep new records to the same shape; link the living docs rather than restating them.
