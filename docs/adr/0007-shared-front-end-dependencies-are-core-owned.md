# ADR 0007 — Shared front-end dependencies are core-owned

**Status:** Accepted — 2026-08-20

## Context

Widgets declare their front-end build inputs (SCSS/JS paths plus named library
identifiers) and the core build pipeline compiles them into the public widget
bundle. As widgets spread across plugins in their own repositories, the
question is what happens when two widgets — possibly in two different plugins —
share a front-end component.

The codebase held two answers when this was decided. The clean one is the
**`libs` mechanism**: a third-party library (swiper, chart.js) is defined once
in core's asset build service and built into its own named bundle; consumers —
five widgets across core and two extracted plugins — declare only the
identifier (`'libs' => ['swiper']`). No consumer points into another owner's
folder, and the mechanism survived every plugin extraction untouched. The
dirty one was a single **raw path reach**: the BlogPager widget's stylesheet
was declared, by path into BlogPager's folder, by two other widgets — one in
core and one in the extracted Events plugin. Asset paths are resolved off the
consuming repo's disk and **silently skipped** when missing, so a path into
another plugin's folder breaks the bundle without an error the moment that
plugin moves or is absent from the composition — the exact hazard the plugin
contract's asset surface records as its standing caveat.

## Decision

**A front-end component consumed by more than one widget owner is core-owned,
and consumers reference it by a stable identifier — never by a path into
another plugin.** Two shapes, by size:

- **Third-party libraries** go through the `libs` mechanism: defined in core's
  asset build service, built as named bundles, declared by identifier.
- **Shared local styles or scripts** (too small to be a library bundle) live at
  a stable core-owned path that every consumer declares; core paths never move
  with an extraction, so the declaration is valid on every composition.

The alternative — one plugin declaring a path into another plugin's vendor
directory — is banned: it couples the consumer's bundle to the provider's
presence, and the silent-skip behavior turns a missing provider into invisible
styling loss rather than a loud failure.

Departures from this rule are **explicit exceptions, recorded when made**: if
core ownership is the wrong home for a shared component, the sanctioned
alternatives are duplicating the component into each consumer or designing a
third mechanism at that time — not reintroducing the cross-plugin path reach.

## Consequences

- Shared front-end dependencies are valid on every composition by
  construction: a core-owned identifier or path cannot dangle when a sibling
  plugin is absent, disabled, or extracted.
- The bundle-content verification gate (build and grep the output) stays the
  proof that shared inputs actually land — a green test suite alone never
  proves the bundle.

Costs:

- **A plugin leaning on another plugin's component forces that component into
  core.** The gravity is one-directional: shared once, core forever — and for
  some components that will be a suboptimal home (core accretes presentation
  code that belongs, conceptually, to a domain). With one developer and a
  thin-core trajectory this is the right trade today; the recorded exception
  path (duplicate, or design a third option) exists for the cases where it
  stops being right.
- Promoting a component to core is a two-repo event when the borrower is
  already extracted: the core lift plus a tagged release of the borrowing
  plugin re-pointing its declaration.

## References

- `docs/plugin-contract.md` surface 11 (front-end assets; vendor paths and the
  silent-skip caveat) — current authority.
- ADR 0006 — the same core-mediation principle for cross-plugin communication;
  this record applies it to build inputs.
- The BlogPager pager styles are the worked case: shared by core BlogListing
  and the extracted Events plugin's EventsListing, lifted to a core-owned
  stylesheet with an Events release re-pointing its declaration (the Blog
  vertical's carve, 2026-08).
