# ADR 0001 — Widget templates are pure renderers behind declared data contracts

**Status:** accepted
**Date:** premise established early in the widget-system era (`sessions/tracks/widget-primitive-premise.md`); enforced across all 41 widgets at session 378 (2026-08-18, Plugin Architecture arc P2)

## Context

The page/widget engine is the product's actual core — the owner's framing is "a niche-specific page builder." Widgets began as the one component primitive intended to render anywhere a component-shaped hole exists (the premise doc's Rimworld lesson: stock content built from the same primitive extensions use, no privileged internal API). But as the product grew, widget templates drifted into direct model queries, `auth()` calls, and service resolution. Each such reach made a widget inseparable from core internals: it could not be moved, tested in isolation, or trusted to render the same data the editor previewed.

A second pressure made this urgent: the plugin architecture (ADR 0002–0004) needs widgets to travel — out of `app/Widgets/` into plugins, and eventually into separate repositories. A template that queries models directly cannot cross that boundary.

## Decision

Widget templates are **pure renderers**. A template reads only `$config`, `$configMedia`, `$widgetData`, `$pageContext`, and render-layer helpers. No model calls, no `auth()`, no `app()`/`resolve()`/`@inject`, no `DB::`/`Auth::` — in any widget template, core or plugin.

Data reaches a template exclusively through a **declared contract**: the definition class declares `dataContract()` (or named `dataContracts()` for multi-source widgets), and the core `ContractResolver` owns every arm that turns a contract into data — projecting contract-declared fields only, fail-closed, with authorization resolved inside the arm (e.g. the portal-member arm resolves the portal guard itself; super-admin-only sources hard-gate in the arm).

The boundary is enforced, not aspirational: the standing guard `tests/Feature/WidgetTemplateBoundaryTest.php` scans every widget template — core, plugin, and shared partials — with **zero allowlist**.

## Consequences

- **Widgets became relocatable.** The LogoGarden pilot (session 377), the seven Events widgets (381), and the two Donations widgets (389) moved into plugins with no data-path change — the contract was the whole interface. This decision is the proven miniature that made the plugin architecture credible before any plugin existed.
- **The resolver arms are core engine** even when the models they read become plugin-owned. On a composition without the plugin, `widgets:sync` drops the widget rows, so no shipped page reaches the arm (the posture proven for the `event` arm at 383 and the `fund`/`donation` arms at 390).
- **Every new data need is a visible contract change** — a new contract field or resolver arm, reviewed in core — rather than a silent template reach. This is deliberate friction.
- Editor preview and public render draw from the same contract, which is what makes editor/public parity a testable property rather than a hope.

## References

- `docs/plugin-contract.md` surface 2 (widget contribution) — current authority.
- `sessions/tracks/widget-primitive-premise.md` — the founding premise.
- Session 378 log (arc P2) — the all-41 retrofit; `WidgetTemplateBoundaryTest`.
