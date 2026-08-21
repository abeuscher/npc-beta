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
| [0008](0008-browser-tests-travel-with-the-plugin.md) | Browser tests travel with the plugin; the assembled application runs them | 2026-08-21 |
| [0009](0009-plugin-tests-in-three-tiers.md) | Plugin tests come in three tiers, and conformance is core's to publish | 2026-08-21 |

## Format

Five sections: **Status**, **Context**, **Decision**, **Consequences**, **References**. Status is one word and a date. The three that carry the substance:

- **Context** — what was true when the question arose, and what the alternatives were. A reader who has never seen this codebase should be able to follow why the question was live at all, and should leave understanding what was rejected as well as what was chosen.
- **Decision** — the rule, stated so it can be applied to a case the record never anticipated. Not a description of what was built; a statement of what is now true and what follows from it.
- **Consequences** — what this buys and what it costs, with the costs named as plainly as the benefits. A record whose Consequences read as advocacy is not finished.

Write for a reader with no context and no way to ask a follow-up question. That means: **no internal shorthand of any kind** — no session numbers, no plan-item or phase labels, no section numbers, no "surface 7" or "§ 6.2". When a record points at a living document, say in plain words what that part of it contains, so the pointer is still meaningful to someone who never opens it. Dates and commit hashes are fine — those resolve for anyone with the repository.

Keep new records to this shape, and link the living documents rather than restating them.
