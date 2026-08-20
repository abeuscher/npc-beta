# Architecture Decision Records

Point-in-time records of the load-bearing architectural decisions in this codebase — the **why** behind the shapes a reader finds in the code. Each record is immutable once accepted; if a decision is revisited, a new ADR supersedes the old one and both note the link.

These records complement, and never replace, the living documents:

- `docs/plugin-contract.md` — the current authoritative shape of every surface core publishes to plugins.
- `sessions/plugin-architecture-plan.md` — the arc plan, question dispositions, and stage ladder.
- `sessions/tracks/*.md` — per-track planning and retrospectives.

An ADR records what was decided and why *at the time*; the contract records what is true *now*. When they appear to disagree, the contract wins and the ADR is history.

## Index

| ADR | Title | Decided |
|---|---|---|
| [0001](0001-widgets-are-autonomous-components.md) | Widget templates are pure renderers behind declared data contracts | 2026 (enforced session 378) |
| [0002](0002-thin-core-everything-else-is-a-plugin.md) | Thin core: everything else is a plugin | session 376 |
| [0003](0003-plugin-owned-data-in-plugin-owned-tables.md) | Plugin data lives in plugin-owned tables — and plugin-owned schema | sessions 376 / 383 |
| [0004](0004-one-repository-per-plugin.md) | One repository per plugin, composed by a central build | session 385 |
| [0005](0005-two-layer-activation-without-auto-discovery.md) | Two-layer activation; Laravel auto-discovery deliberately unused | sessions 382–386 |
| [0006](0006-cross-plugin-communication-through-core-events.md) | Plugins communicate only through core-owned events and capabilities | sessions 381 / 389 |

## Format

Status / Date / Context / Decision / Consequences, MADR-style. Keep new records to the same shape; link session logs and living docs rather than restating them.
