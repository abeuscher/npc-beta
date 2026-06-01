# Session Brief: Mobile Collapse Ruleset — Research & Apply

## Objective

Derive a reusable set of rules for how desktop-first layouts collapse to mobile, by measuring how well-built reference sites do it. Produce a proposed ruleset, pause for review, then apply the approved version to the existing widget styles.

This is a derived, programmatic ruleset — not per-breakpoint authoring exposed to users. The agent's job is to learn magnitudes and edge cases from real sites and express them as rules that plug into the primitives already in place. It is **not** to invent a responsive framework.

## Hard constraints

- Express every output in terms of **existing widget fields and the existing mobile collapse mechanism**. Assume the breakpoints are already known and fixed.
- Do **not** introduce new breakpoints, new custom properties, or any new "system" (spacing scale, type scale, token set) *gratuitously*. This is a **guideline, not a hard ban** (relaxed at the 2026-05-31 review — see "App reconciliation" below). One bounded new primitive is already sanctioned: section spacing emitted as `--np-*` custom properties so a single viewport rule can compress it. Anything beyond that — a user-facing scale, a token vocabulary, a new breakpoint — still **stop and flag as a question** before building.
- Stay scoped to what is asked. Reconcile and tune existing values; do not expand into adjacent refactors.
- Output **ratios and relationships**, not absolute pixel values. "Headings scale ~0.57 to mobile" survives arbitrary content; "h1 = 32px" is a frozen copy of someone else's page.

## App reconciliation — resolved decisions (2026-05-31 review)

This brief was written before being checked against the codebase. The research half (Phase 1) stands as written; the items below correct how the output maps to *this* app and record the decisions taken at review. **Where this section differs from the prose elsewhere in the brief, this section wins.**

**This is not greenfield — two prior sessions built the foundation.** Session 294 shipped column-layout mobile collapse (`.page-layout` and `.widget-preview-scope` get `container-type: inline-size`; `.layout-grid` collapses to one column via `@container (max-width: 768px)`, gated on a `data-collapse-mobile` attribute). Session 295 shipped per-breakpoint type scaling (`TypographyCompiler` emits `@media (max-width: Npx)` font-size steps). This ruleset *tunes and extends* those — it does not invent collapse. Note 294 explicitly **carved out** `product_carousel` and `blog_pager` as unhandled; they are exactly the two widgets this work still has to address (see Carousel routing).

**Mechanism map — where each measured ratio actually lands.** The app has four responsive mechanisms, split viewport-vs-container. A rule applies to whichever owns its role; Phase 2 must not reach for any other mechanism.

| Role / property | Existing mechanism | "Apply the ratio" means |
|---|---|---|
| Headings (h1/h2…) | Viewport `@media` in `TypographyCompiler` | Tune the existing per-breakpoint sizes |
| Body text | `TypographyCompiler` (may have no ramp yet) | Add a ramp inside the same compiler — not a new system |
| Column / grid stacking | `@container (max-width: 768px)` on `.page-layout` | Already shipped (294); verify, don't rebuild |
| Widget-interior grids (`board_members`, `logo_garden`, `pricing_chart`, `events_listing`) | `@container np-widget` (own width) | Shipped 330–331; tune thresholds only |
| Section padding / margin | **None today** — see the primitive below | Build the primitive, then apply the ratio |
| Carousel slides-per-view | Swiper viewport `breakpoints` | See Carousel routing |

**The section-spacing primitive (the one sanctioned new primitive).** Today `AppearanceStyleComposer` writes section padding/margin as literal inline values (`padding-top: 80px`), and inline always beats any stylesheet rule — which is *why* nothing can compress them. Fix: emit them as custom properties instead (`--np-pad-top: 80px` inline), add a host rule that consumes them (`.widget { padding-top: var(--np-pad-top) }`), and express the entire compression ruleset as **one viewport `@media` rule** scaling via `calc(var(--np-pad-top) * <ratio>)`. The operator still sets one concrete desktop value; the mobile ratio is derived and lives in one place. **Signal = viewport `@media`, not container** (decided 2026-05-31): section breathing room is about screen size, and viewport avoids forcing a containment context onto every widget wrapper — the blast radius 330–331 deliberately avoided. Container queries stay for column/grid collapse; viewport `@media` for type and section spacing.

**Breakpoints are real fixed values — and currently triplicated.** They are **576 / 768 / 992 / 1200 / 1400**; the column collapse fires at **768**. They are declared in three places that happen to agree: SCSS `$bp-*` in `resources/scss/_variables.scss` (all five), a separate PHP copy in `TypographyCompiler::BREAKPOINT_MAXWIDTH` (lg/md/sm only), and hardcoded `576/768` literals in the Swiper configs in widget templates. **Precursor task — approach B (decided):** make SCSS `_variables.scss` the canonical source for all CSS; mirror it in one PHP constant for PHP/Blade (and feed the Swiper literals from PHP so the third copy dies); add a Pest test asserting the two match; and have **each location carry a comment pointing at the other**. A single *runtime* variable is impossible — CSS `@media`/`@container` *conditions* cannot read `var()` — so "one source" means one definition feeding both, drift-guarded.

**Carousel routing → real widgets, with decisions.** Content slider = `carousel` + `product_carousel`; pager = `blog_pager` (the numbered-pager discriminator holds). Decisions (2026-05-31, superseding the "Carousel routing rule" prose below):
- **Content slider on mobile → drop Swiper and stack the slides** (simpler than scroll-snap/peek for v1).
- **Pager → "Load more" is deferred** to the pre-release push as its own roadmap item — `blog_pager` has no load-more mode today, so building it is a feature, not a tune.

**Scope OUT: nav + footer.** Mobile nav (the hamburger menu, currently broken) and the footer column-layout collapse are carved into their **own dedicated session**, not this ruleset. This brief does not touch `nav` or footer layouts.

**Role → widget handles:** hero→`hero`; event listings→`events_listing` (+ `this_weeks_events`, `event_mini_calendar`); collapsing grid→`board_members` / `logo_garden` / `pricing_chart` / `blog_listing`; content carousel→`carousel` / `product_carousel`; pager→`blog_pager`; section wrapper→every widget's `appearance_config` padding/margin via the new primitive above.

---

## Reference sites

Capture each at the project's known widths — **desktop ≈ 1280, tablet = 768 (`$bp-md`), mobile ≈ 375** (the fixed breakpoint set is 576 / 768 / 992 / 1200 / 1400; see App reconciliation).

| Site | Focus — what to learn from it |
|---|---|
| stripe.com/use-cases/ecommerce | Hero, footer nav |
| npr.org | Dense tiles + text; load-more progressive disclosure (closest analog to a populated index) |
| sfmoma.org/events | Event listings reflowing |
| dribbble.com | Collapsing grid — column count, gutter compression, aspect-ratio handling |
| store.steampowered.com | Grid + content carousel (slide-count reduction on mobile) |

Marketing pages get redesigned. For each site, capture what is **actually rendered** at each width and report it. If an assigned pattern is absent, gated behind a login, or replaced, **flag it** — do not screenshot an empty splash and report success.

---

## Phase 1 — Research & Extract

For each site, at each width:

**1. Screenshot** for layout-mode gestalt — did columns stack, did nav collapse, did the carousel go 1-up, did the pager change form.

**2. Measure with `getComputedStyle`** on a representative instance of each element role. The screenshot says *what mode changed*; computed styles say *by how much*, exactly. Report which DOM element was treated as each role and why — identifying "the hero heading" or "a card" on an arbitrary site is its own judgment, and it should be visible.

Element roles to measure (starting set — extend if a site warrants it):
- h1 / h2
- body text
- section wrapper (outer padding/margin)
- card / tile
- button
- container gap / grid gutter

**3. Express each finding as a ratio** desktop→mobile (and the layout-mode change where relevant), not an absolute.

### Priors (seed, don't rediscover)

Treat the conventional collapse behaviors as known starting points. The screenshot work is for **tuning magnitudes and catching the non-obvious cases**, not for relearning responsive design:
- Multi-column grids stack.
- Section padding compresses.
- Headings step down.
- Content carousels reduce slides-per-view.

### Carousel routing rule

"Carousel" is two widgets for collapse purposes. Route on **role**, and key on a **structural** tell, not the desktop styling:

- **Content slider** (browsing a set you scroll through) → mobile = swipe / scroll-snap, reduce slides-per-view to 1 or ~1.2-up with peek.
- **Pager** (paging through a list; has a numbered pager; standing in for pages) → mobile = stacked tiles + a load-more control; drop the numbered pager.

The discriminator: **presence of a numbered pager / discrete pages = pager**, regardless of how it looks on desktop. Default the pager case to **load-more** (initial mobile payload stays small for lists that can grow).

### Tablet

Derive the intermediate width as an interpolated midpoint or nearest-of-the-two — not a third independent ruleset. Mobile is the target that matters; tablet is a check.

### Phase 1 deliverable

A ruleset document: a table of **widget type → property → desktop→mobile ratio (or mode change)**, with the **reference evidence** beside each row (which site, what was measured). Tag each rule with the widget type it came from. Then **stop.**

---

## PAUSE — review gate

The agent stops here. The proposed ruleset is reviewed and edited before anything touches styles. Phase 2 runs in the same session after approval.

---

## Phase 2 — Apply

Apply the **approved** ruleset only.

- Map every rule to existing widget fields and the existing mobile collapse mechanism. Nothing else.
- No new breakpoints, no new custom properties, no new systems. If a rule can't be expressed in the existing primitives, **stop and flag it** rather than inventing scaffolding to hold it.
- Report what was changed, per widget type, in plain language — what value moved, from what to what, and which approved rule it implements. Write the summary as if to someone who has not seen the code or the file layout: no abbreviations, no numbered references to things that can't be indexed from the chat.