# ADR 0009 — Plugin tests come in three tiers, and conformance is core's to publish

**Status:** Accepted — 2026-08-21

## Context

The application is being decomposed so that one codebase can compose different products from a manifest: a build declares which verticals it installs, and the rest of the system adapts. Seven verticals already live in their own repositories, consumed as packages.

The test suite was written for the thing this replaces — one assembled whole, one configuration, everything present. It has been following the decomposition rather than anticipating it, and the strain shows in a specific way.

As each vertical left, someone wrote a guard proving core no longer reaches into it. Seven of those guards now exist. They are the same test with a different name substituted, and four of them say so in their own comments — *"the previous one, verbatim."* Each extraction added another copy, so the cost of the guard grew with every vertical while what it actually caught stayed flat. Worse, the coverage was opt-in: a plugin had no guard until a person remembered to write one, and nothing anywhere would notice the omission.

Underneath that is a question the suite has never been able to answer: **what is this test for?** Three different things were being tested as though they were one:

- that a plugin's own logic works,
- that a plugin correctly implements what core publishes to it,
- that a particular assembled set of plugins behaves as a product.

Those have three different owners and three different lifetimes, and running them as one undifferentiated suite against one configuration hides that. It also makes the manifest idea untestable: if a build can compose a different set, then "the tests pass" has to mean something per set, and today it can only mean something for the one set that happens to be installed.

Options considered:

- **Keep writing a guard per plugin.** Honest and simple, and it is what got us here. Rejected: the cost scales with plugin count, in the direction of more plugins.
- **Rely on each plugin's own suite.** Rejected on its own: a plugin sees one side of the contract. It can prove its code works and cannot prove it implements what core actually publishes, because the definition of "correct" lives in core.
- **A generic conformance suite, published by core, that every installed plugin must pass.** Chosen. It is the only option whose cost is flat as verticals multiply.

## Decision

**Tests for a plugin architecture fall into three tiers. Each tier has one owner, and a test that cannot say which tier it is in does not yet know what it is for.**

**Tier one — the plugin's own logic.** Owned and stored by the plugin, travels with it, runs wherever the plugin's code runs. This is the autonomy tier and it already works this way.

**Tier two — conformance.** That a plugin correctly implements what core publishes: it registers what it claims, declares what it ships, degrades when an optional dependency is absent, and stays on its own side of the boundary. **Written once, generically, in core, and run against every plugin the manifest names.** Adding a vertical to the manifest brings it under these checks on the same commit — nobody has to remember, and nobody can ship a plugin that quietly has no guard.

**Tier three — composition.** That a *particular assembled set* behaves as a product. This belongs to the distribution, not to core and not to any plugin: a thin smoke suite per manifest — it boots, its pages render, its central flow completes — not a copy of core's suite run again.

Three rules keep the conformance kit from rotting the way the per-plugin guards did:

1. **A check belongs in the kit only if it is true of every plugin.** One that needs an exception list is a per-plugin check wearing a generic hat; it stays in that plugin's own file.
2. **The kit never restates a guard that exists elsewhere.** Duplicated guards are how the previous arrangement decayed.
3. **A failure must name the offending plugin.** A generic suite that fails without saying which plugin broke is worse than the bespoke ones it replaced.

**Core's own autonomy is testable directly:** run core's suite with no plugins installed. Whatever breaks is either a genuine leak across the boundary or a test that was never really about core. That is one configuration, not a per-plugin fixture, and it says more about how thin the core is than any amount of reading.

## Consequences

- **The guard cost stops scaling with plugin count.** Seven files became one, and the eighth vertical is covered by the commit that adds it to the manifest rather than by someone remembering.
- **"Where does this test belong?" now has an answer.** That question had no answer before, which is why the default was always "core", and why core's suite kept growing while verticals left.
- **Per-product testing becomes possible.** Once a build can compose a different set, tier three is what makes "the tests pass" mean something about *that* build. Naming the tier does not build it, but nothing could be built while the three were conflated.

Costs:

- **A generic check is harder to read than a bespoke one.** Seven files each stated their case in plain prose about one vertical. One file cannot do that as well, which is why the name-the-plugin rule is a hard rule rather than a nicety.
- **Consolidation loses per-plugin commentary.** The reasoning about what core legitimately keeps for each vertical was carried into the kit's header, but a table in one file will drift where seven paragraphs beside seven tests did not.
- **The kit is a single point of failure, and it fails silently.** If the manifest stops parsing, every check passes vacuously against an empty plugin list and the file reports green while asserting nothing — the exact failure this project keeps rediscovering. It therefore carries a check that the plugin set is non-empty: a guard for the guard.
- **A class of existing test becomes false, not merely redundant.** Anything asserting the shape of the monolith — a fixed count of widgets, of dashboard panels, of navigation items — is a per-product fact stated as a universal one. Under a manifest architecture those must move to tier three or go. That is a deletion programme spread across future work, not a single change, and it will keep surfacing as verticals leave.
- **Tier three does not exist yet.** Until it does, a build composing an unusual set is unverified beyond what tiers one and two catch.

## References

- ADR 0008 — where a plugin's browser tests live and who runs them. This record generalises that reasoning from browser tests to the whole suite; the ownership rule there is the tier-one/tier-three distinction seen from one angle.
- `docs/plugin-contract.md` — what core publishes to plugins. It defines what "conformance" means, so the kit's checks are only ever as good as that document is explicit.
- `docs/plugin-extraction-runbook.md` — the extraction procedure, which is where a vertical's tests get sorted by tier.
- `sessions/plugin-architecture-plan.md` — the standing ruling that each plugin carries its own suite and that the central build runs the distribution's suites, which tier one implements and tier three completes.
- `tests/Feature/Infrastructure/PluginConformanceTest.php` — the kit itself, carrying the rules above as its own header.
