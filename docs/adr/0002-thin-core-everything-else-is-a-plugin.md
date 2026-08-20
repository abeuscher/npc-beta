# ADR 0002 — Thin core: everything else is a plugin

**Status:** accepted
**Date:** session 376 (2026-08-18) — the plugin-architecture planning session; extended by the session 385 reframe (the end-state architecture is the project's finish line)

## Context

The founding architectural conviction of the project — carried from the start but never fully implemented — is a minimal core with everything else delivered as plugins, assembled by a central build. Session 376 pressure-tested that conviction against a then-375-session production codebase and settled why it is worth the cost. Three motivations, in increasing order of consequence:

1. **The architecture itself** — a small engine with composable features.
2. **The LLM context-window economy.** A bounded module plus a written contract lets an agent work without loading the whole application. The CRM↔Fleet-Manager relationship (two repos, a versioned contract, ~50 FM sessions run without ever loading the CRM codebase) already proved the pattern in-house. Key correction from that experience: **the context savings come from the boundary and the contract, not from the repo split.**
3. **The business premise** — vertical SaaS: one engine, several niche configurations. A niche is a *manifest* (core + chosen plugins + config/seed pack), not new engineering.

## Decision

Core is the engine and only the engine:

- **Identity & access** — `User`, `Contact` (minimal identity: name/contact info), auth, permissions, 2FA.
- **The admin shell as sockets** — the Filament panel, the core-owned nav-group list, the settings framework. Plugins contribute pages, resources, and permissions *into* the shell.
- **The page/widget engine** — pages, layouts, the widget primitive and registry, the contract resolver, the public renderer, theme/tokens (ADR 0001).
- **Plumbing** — media library, email transport + the template mechanism, queues/scheduler, activity log, backups.

Everything else — every domain vertical (events, donations, memberships, portal, blog, forms, store), every integration, and eventually the generic widget packs — is a plugin.

The decision rules that draw the line:

- **Litmus test for plugin-hood:** a piece is a plugin only if some plausible install would exclude or replace it. Pieces every install needs are core.
- **Sockets vs. parts:** composition points every install needs (nav, page canvas, settings) are core sockets; the things plugged into them are plugin parts. The nav is not a plugin; nav entries are.
- **Plugins never patch plugins.** Extensions extend core through published surfaces only — the property whose cost explodes in mature plugin ecosystems is deliberately excluded.

## Consequences

- The core's size sets the floor on what every agent must understand; keeping it thin is what makes the module boundaries pay.
- Core owns the sockets' invariants: nav groups, permission seeding at enable time, the admin theme (plugins ship no admin CSS), panel auth wrapping every plugin admin route.
- Standing boundary guards (`*ModuleBoundaryTest`, zero allowlist) make the line mechanical rather than judgment-based.
- The line is cheap to hold *because* of ADR 0001: most features are widgets + admin surfaces + tables, all of which have plugin-shaped channels.
- Accepted cost: some core seams must tolerate absent plugins (see ADR 0003's composition-safety rule), and a handful of generic engine arms reference plugin-owned models by design.

## References

- `sessions/plugin-architecture-plan.md` §§ 1–4 (motivation, principles, the end-state core table) — canonical.
- `docs/plugin-contract.md` — the published surface list (13 surfaces).
