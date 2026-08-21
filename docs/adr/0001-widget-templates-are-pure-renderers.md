# ADR 0001 — Widget templates are pure renderers behind declared data contracts

**Status:** Accepted — 2026-08-18

## Context

The page/widget engine is the product's actual core — at heart this is a niche-specific page builder. Widgets began as the one component primitive intended to render anywhere a component-shaped hole exists (`sessions/tracks/widget-primitive-premise.md` — the Rimworld lesson: stock content built from the same primitive extensions use, no privileged internal API). But as the product grew, widget templates drifted into direct model queries, `auth()` calls, and service resolution. Each such reach made a widget inseparable from core internals: it could not be moved, tested in isolation, or trusted to render the same data the editor previewed.

A second pressure made this urgent: the plugin architecture (ADR 0002–0004) needs widgets to travel — out of `app/Widgets/` into plugins, and eventually into separate repositories. A template that queries models directly cannot cross that boundary.

The boundary was enforced across all 41 widgets in one retrofit (commit `03be2918`), after a pilot extraction proved the shape (`4ccc70d7`).

## Decision

Widget templates are **pure renderers**. A template reads only `$config`, `$configMedia`, `$widgetData`, `$pageContext`, and render-layer helpers. No model calls, no `auth()`, no `app()`/`resolve()`/`@inject`, no `DB::`/`Auth::` — in any widget template, core or plugin.

Data reaches a template exclusively through a **declared contract**: the definition class declares `dataContract()` (or named `dataContracts()` for multi-source widgets), and the core `ContractResolver` owns every arm that turns a contract into data — projecting contract-declared fields only, fail-closed, with authorization resolved inside the arm (e.g. the portal-member arm resolves the portal guard itself; super-admin-only sources hard-gate in the arm).

The boundary is enforced, not aspirational: the standing guard `tests/Feature/WidgetTemplateBoundaryTest.php` scans every widget template — core, plugin, and shared partials — with **zero allowlist**.

## Consequences

- Widgets became relocatable. Three extractions since — one pilot widget, seven event widgets, two donation widgets — moved into plugins with no data-path change; the contract was the whole interface.
- The resolver arms are core engine even when the models they read become plugin-owned. On a composition without the plugin, `widgets:sync` drops the widget rows, so no shipped page reaches the arm.
- Editor preview and public render draw from the same contract, which is what makes editor/public parity a testable property rather than a hope.

Costs, equally real:

- **Every new data need is a core change.** A plugin author who needs a new field or arm is blocked on core review. This is a bottleneck, and it partially contradicts ADR 0004's decoupling goal — a live tension between two of our own records, accepted with eyes open because the alternative (templates reaching wherever they like) is how we got here.
- **`ContractResolver` centralizes what the plugin architecture decentralizes.** Every arm for every plugin lives in core — a growing surface in exactly the place ADR 0002 says we want thin, and a privileged API in tension with the founding "no privileged internal API" premise. The resolver *is* privileged; we accept that because it is also the single place where projection and authorization are auditable.
- **Zero allowlist means no escape hatch.** Someone will eventually have a legitimate case the contract shape can't express. When that happens, the answer is a new ADR superseding this one — not a quiet allowlist entry.

## References

- `docs/plugin-contract.md` — the widget-contribution surface is the current authority on how a plugin adds widgets.
- `sessions/tracks/widget-primitive-premise.md` — the founding premise.
- Commits `4ccc70d7` (pilot extraction, 2026-08-18), `03be2918` (the all-41 retrofit + standing guard, 2026-08-18).
