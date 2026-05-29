# Spec: Widget Styling Contract Rework

> **Status:** Pre-session spec. Not yet scheduled. Convert to a `NNN. Session Title.md`
> (or a small arc of them) when it slots into the work. This is widget-surface +
> contract-surface work — it belongs to whichever track owns `app/Widgets/*` and the
> widget contract surface at scheduling time (the **Widget Autonomy** track per
> `sessions/tracks/widget-autonomy.md`); the branch prefix follows that track.
>
> Written from a design conversation (2026-05-29), outside an official session.

---

## One-sentence description

Extend the already-proven colour-token contract to the rest of the widget styling
vocabulary (spacing, type, breakpoints), formalize the widget interior's responsive
model on container queries, and migrate the stock widgets off the remaining host
Sass-variable leaks — so the widget styling boundary is a *declared, enforced
contract* rather than a working-by-convention arrangement.

---

## Why now / framing

The product roadmap treats **widgets as plugins**: back+front-end units (Blade + SCSS +
JS + optional JSON/manifest/README) that will eventually be **externally authorable**.
That future needs a formal, documented, version-locked styling contract. Today the
styling works only because we control every widget and concatenation order is fixed —
fine with zero friction now, untenable the moment a third party writes one.

The good news from the survey: **most of this already exists.** The
Theme/Template Re-Taxonomy arc (sessions ~300–301) already ran the exact play this
spec generalizes — *for colour*:

- a tiered token vocabulary (`--np-color-*`, Tier 1 user-tunable + Tier 2 published)
  emitted into the public bundle by `App\Services\ColorTokenCompiler`,
- a single published contract doc (`docs/theme-color-tokens.md`, subtitled
  "Widget-Dev Contract"),
- a permanent enforcement gate (`tests/Feature/WidgetColorTokenConsumptionTest.php`)
  that *fails the build if a widget hardcodes a hex or reads a bare `$color-*` Sass var*,
- demotion of the host `$color-*` Sass variables to build-time fallbacks only.

This spec is: **run that playbook for the remaining vocabulary families, and formalize
the interior's responsive + cascade behaviour.** It is not a rebuild.

### Goal split

- **Beta goal — "stock widgets get cleaner."** Unify the rules, migrate the stock
  widgets to honour them, enforce with tests. Contract stays *internal and breakable*.
  No public authoring guide finalized, no package extraction.
- **Release goal — "widgets are externally authorable."** The contract, now proven
  internally, gets written down for outside authors and the `app/Widgets/*` extraction
  to `abeuscher/npc-widgets` becomes mechanical because the boundary is already clean.
  *Out of scope for this spec* (see Out of scope); this spec is the beta enabler for it.

---

## The model this formalizes (already a physical seam in the code)

Every widget renders as a **host-owned container** wrapping a **widget-owned interior**.
The seam already exists: the container's entire appearance is composed by
`App\Services\AppearanceStyleComposer::compose()` from the instance's
`appearance_config`, and the widget's Blade renders inside it.

```
┌─ CONTAINER (host-owned) ─────────────────────────────────────┐
│  styled ONLY by appearance_config via AppearanceStyleComposer: │
│  margin · padding · border · text colour · background ·        │
│  content-full-width + background-full-width toggles            │
│  → the common styling contract. Version-locked by definition.  │
│  → establishes the named container-query context (NEW).        │
│                                                                │
│  ┌─ INTERIOR (widget-owned) ─────────────────────────────┐   │
│  │  the widget's blade + scss + js (+ json/manifest/readme)│   │
│  │  MAY read the published token vocabulary (lean set)     │   │
│  │  MAY use approved + self-carried data                    │   │
│  │  responds to ITS OWN width via @container (NOT @media)   │   │
│  │  otherwise: the author's playground                      │   │
│  └──────────────────────────────────────────────────────────┘ │
└────────────────────────────────────────────────────────────────┘
```

The common container styles (margin / padding / border / text colour / background /
full-width toggles) map **one-to-one** onto what `AppearanceStyleComposer` already
emits — this part is done. The work is the *interior* contract and the *vocabulary*
the interior may read.

---

## The lean vocabulary — state vs. gap

Agreed posture: **lean.** Publish the smallest set we're confident we'll still want in
two years; adding tokens later is safe/additive, removing them is a breaking change.
Mostly already authored in Theme (Design System page) + relevant settings.

| Family | State | Work |
|---|---|---|
| **Colour** | ✅ Done — tiered tokens, compiler, contract doc, enforcement test, schemes | None. This is the template to copy. |
| **Spacing** | ❌ Gap — no `--np-space-*` family; widgets still reach `$gutter` | Create a lean **runtime** `--np-space-*` token family (spacing is a `var()`-able value; mirror the colour-token pipeline). |
| **Type** | 🟡 Partial — `TypographyCompiler` applies type as *compiled rules*, doesn't expose a readable token vocabulary | Expose a lean readable type-scale token set (families / a few sizes / line-heights / weights) widgets can read. Most design-heavy of the three. |
| **Breakpoints** | ❌ Gap (conceptually) — `$bp-*` are static literals in `_variables.scss` | Keep as **build-time Sass constants** (NOT runtime, NOT editable). Document them as the published standard collapse widths. |

### Breakpoints — settled decisions (do not relitigate)

- Layout/CSS breakpoints (`$bp-sm: 576 … $bp-xxl: 1400`) stay **fixed build-time
  constants** in `resources/scss/_variables.scss`. Sass resolves them to literals before
  the browser sees them, so they work inside `@container` conditions exactly as
  `resources/scss/_layout.scss:65` (`@container (max-width: #{$bp-md})`) already proves.
- They are **NOT editable** and **NOT a runtime custom property** — a container-query /
  media-query *condition* cannot read a `var()`, and we have no need for admin-tunable
  layout breakpoints. The "make breakpoints editable" idea is **killed**, not deferred.
- `image_breakpoints` (CMS Settings → responsive image-variant widths) is a **different,
  unrelated feature** and stays exactly where it is. Do not touch it.
- The contract documents the breakpoint pixel values as named constants so widget
  authors collapse at the same widths the host layout does (no host/widget drift).

---

## Interior responsive model — container queries

The host already uses container queries for layout collapse:
`resources/scss/_layout.scss` sets `.page-layout { container-type: inline-size }` and
collapses columns via `@container (max-width: #{$bp-md})` keyed to the *layout's own
width*, not the viewport (this is also what makes the page-builder preview collapse
faithfully). This spec extends that one level down to widget interiors.

- **Host change:** the per-widget container wrapper gets a **named** containment context
  (`container-type: inline-size; container-name: <name>`), so a widget interior can query
  its own width.
- **Contract rule:** widget interior SCSS responds via `@container` against its own
  container — **never `@media` against the viewport**. A widget can land full-width or in
  a narrow column; viewport width is the wrong signal (it's exactly the
  `.layout-column { min-width: 0 }` blowout class). Container width composes for free with
  the host's column-collapse: when the layout collapses N→1 columns, each widget gets
  wider and its own `@container` rules re-evaluate.
- The two levels (host layout collapse, widget interior collapse) are independent and
  need not know about each other.

---

## Cascade isolation — `@layer` (decision needed; lean yes)

The "well of specificity": widget interiors currently inherit host `_base`/`_layout`
styling implicitly and rely on fixed concatenation order for precedence — fragile the
moment an outside author writes a deeper selector. Proposal: introduce **CSS cascade
layers** (`@layer`) — host reset/base in a lower layer, widget interiors in a `widgets`
layer — so author precedence is guaranteed by layer order regardless of selector
specificity. Preserves the *deliberate* inheritance (container text colour inherits
inward) while killing the *accidental* kind. Shadow DOM is the nuclear alternative and is
**out of scope** (it would break the inherited-text-colour contract and the host's
deliberate reach-in). `@layer` touches host base ordering, so it carries slightly more
blast radius than the per-family token work — flag for a deliberate decision at session
start; may warrant being its own phase/session.

---

## Concrete migration surface (the leaks, surveyed)

Small and bounded. Host SCSS partials keep using Sass `$bp-*`/`$gutter` (host-internal,
fine). Only **widget** SCSS crossing the line needs migrating:

- **Spacing leak (`$gutter`):** `app/Widgets/ThreeBuckets/styles.scss` (2 refs) →
  `var(--np-space-*)`.
- **Viewport `@media` → `@container` (the `$bp-*` refs inside widgets):**
  `ThreeBuckets`, `ProductCarousel`, `PricingChart`, `MapEmbed`, `LogoGarden` (4 refs),
  `BoardMembers` (2 refs). Convert each `@media (max-width: $bp-x)` to a container query
  against the widget's own container (`@container (max-width: #{$bp-x})` once the wrapper
  is a named container). `$i` hits in `BoardMembers` are `@for` loop counters, not leaks —
  ignore.
- `PricingChart` is the reference target shape already (local `--pc-*` namespace, reads
  `--np-color-*`).

The per-widget migration is embarrassingly parallel — fits the Widget Autonomy track
pipeline (one widget per PR).

---

## Enforcement

Extend the proven gate pattern (mirror `WidgetColorTokenConsumptionTest` — an explicit
reviewed baseline list in the `design` Pest group, fails both on a new violation and on a
baseline entry that no longer exists):

- No raw spacing literal / bare `$gutter` in widget SCSS (must read `--np-space-*`).
- No viewport `@media` query in widget SCSS (must use `@container`).
- (If type tokens land) no bare `$font-*` / hardcoded type values in widget SCSS.

Group membership stays pinned per `tests/Feature/DesignGroupIntegrityTest.php` — update
the list in the same reviewed pass.

---

## Docs to update

- **Contract doc:** decide whether to add per-family contract docs alongside
  `docs/theme-color-tokens.md` or consolidate into one `docs/widget-styling-contract.md`.
  Must publish: spacing tokens, type tokens, the breakpoint constants, the container-query
  rule, the `@layer` model.
- **`resources/docs/widget-development.md`** SCSS section is currently stale against this
  direction — it says "`$bp-sm`/`$bp-md` are available; do not use `@use`." Update to the
  container-query guidance and the token vocabulary once landed.

---

## Suggested phasing (likely an arc, not one session)

Each phase is a self-contained milestone; the migration phases are per-widget parallel.

- **Phase A — Design + contract (user in the loop).** Lock: spacing scale shape (steps,
  names, base unit), the public type-token set, the breakpoint constant list, the
  named-container convention, and the `@layer` in/out call. Write the contract doc. These
  are the expensive-to-reverse decisions.
- **Phase B — Spacing.** `--np-space-*` emit (mirror `ColorTokenCompiler`), migrate
  widgets off `$gutter`, extend the enforcement test.
- **Phase C — Type tokens.** Expose the type-scale tokens, migrate widget type usage,
  extend the test.
- **Phase D — Container queries (+ optional `@layer`).** Host wrapper named container;
  migrate the six widgets' `@media` → `@container`; introduce `@layer` if Phase A said yes.
- **Phase E — Docs.** Finalize the contract doc + refresh `widget-development.md`.

---

## Open questions to resolve at session start

- **Spacing scale:** how many steps, naming (t-shirt `xs/sm/md/lg/xl` vs numeric), base
  unit. Keep lean — fewer steps, add later if a real widget proves the need.
- **Type tokens:** which type values are public (families + how many sizes + line-heights
  + weights). The most design-heavy call; lean bias.
- **`@layer`:** adopt for beta or hold? It reorders host base precedence (blast radius);
  could be its own phase.
- **Named-container convention:** the `container-name`, and whether *every* widget wrapper
  unconditionally gets `container-type: inline-size` (cheap, uniform) vs only those that
  declare interior queries. Confirm no ambiguity with the existing `.page-layout`
  container (nested containers, naming).
- **Contract doc shape:** one consolidated `widget-styling-contract.md` vs per-family docs.

---

## Out of scope

- **Package extraction** to `abeuscher/npc-widgets` (release-phase; Widget Autonomy track).
- **Public authoring guide finalization** (release-phase; contract must prove internally
  first per `widget-primitive-premise.md`).
- **External-author breakpoint *delivery* mechanism** — how a third-party widget receives
  the `$bp-*` constants without coupling to the host's `_variables.scss` (today the build
  inlines `_variables.scss` into widget SCSS). Note as an open *release-phase* question;
  beta keeps the current inline mechanism.
- **Shadow-DOM isolation** — `@layer` is the chosen isolation mechanism; shadow DOM is not.
- **Editable layout breakpoints** — killed (see settled decisions).
- **`image_breakpoints`** (CMS Settings) — untouched, unrelated feature.
- **Per-template `custom_scss` / ScssPhp runtime path** — separate compiler, not part of
  this contract.

---

## Testing

- **Slow test groups to run:** none expected (token + SCSS work is fast suite + `design`
  group).
- **New tests expected:** yes — extend the consumption-gate pattern for the new families
  (spacing, type, container-query rule), in the `design` group; update
  `DesignGroupIntegrityTest` in the same pass.
- **Build:** widget asset changes require `docker compose exec app php artisan build:public`
  (regenerate `public/build/widgets/manifest.json`); token-emit changes to the public
  bundle run through it too. Front-end source touching `resources/scss/**` also needs
  `npm run build`.
- **Visual verification:** the responsive/collapse behaviour under container queries is a
  human-judgment surface — Playwright can confirm a collapse *fires*, but "does it look
  right at each width" needs the user. Pause for manual testing on the responsive phase.
