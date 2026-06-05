# Page Composition Fidelity — Finding template & pass playbook

Reference doc for an agent running a **Page Composition Fidelity** pass (session shape `design-comp`). The track doc at [`page-composition-fidelity.md`](page-composition-fidelity.md) is canonical for the track's premise, status, and discipline. This doc is the operational playbook: how to set the pass up, what to read before touching anything, how to run the match, how to compare, and how to record gaps.

It exists because the session-339 pass mis-started — it treated a comp's margin notes as the full spec, edited before understanding the composition model, and reported "matched" before comparing to the comp. Every rule below is a scar from that pass. Read it end to end before starting.

---

## 0. The one rule everything else serves

**The comp is the source of truth. The rendered page is the lie.** When they disagree, the page is wrong — not the comp. If a widget's default or current setting doesn't match the comp, **the widget changes to match the comp**; a default differing from the comp is a data edit to make, not a limitation to accept. The only things that *aren't* "just change it" are genuine **gaps** (§5), and those get named, not silently approximated.

A corollary the 339 pass learned the hard way: **the prompt's callouts are a subset of the comp.** A session prompt (or the green-margin notes a designer writes on a comp) typically highlights a few things — usually colours. The *whole comp* — layout, alignment, spacing, button styling, typography, copy — is the spec. Matching only the callouts and skipping what the comp visibly shows is the track's signature failure.

---

## 1. Designate the pass + read the comp

- **Locate the comp files** and note what each is (before vs. after, desktop vs. mobile, header/footer reference). Record the path in the session prompt.
- **Render the comp to read it.** Comp exports (SVG/PNG/Figma) usually have **outlined text** — not selectable. Render the SVG in a headless browser at its intrinsic size; for small notes, render high-DPI crops of the relevant region. (Playwright is available in the repo; `node` lives under `~/.nvm/...` and resolves `playwright` only from the project's `node_modules`, so run render scripts from the repo root.)
- **Sample exact values off the comp**, don't guess: hexes by reading canvas pixels; spacing/alignment by measuring pixel positions. Surface sampled colours for sign-off before applying (the owner usually doesn't have the hexes).
- **Read the *whole* comp, both breakpoints.** Desktop and mobile. Note alignment, section backgrounds, inter-section spacing, button fills *and outlines*, heading colours, copy changes.

---

## 2. Learn the composition model BEFORE editing — non-optional

The 339 pass edited the wrong layer twice (zeroed widget padding when the spacing lived on the column; assumed backgrounds were a theme token when they're per-band) because it started editing before understanding the model. Don't. Read both the docs and the *actual target page*.

**Docs (canonical for how the system works):**
- [`docs/widget-system.md`](../../docs/widget-system.md), [`widget-system-spec.md`](../../docs/widget-system-spec.md), [`widget-conventions.md`](../../docs/widget-conventions.md) — widget authoring, config vs. `appearance_config`, the resolver.
- [`docs/theme-color-tokens.md`](../../docs/theme-color-tokens.md) — the `--np-color-*` token contract.
- [`docs/schema/pages.md`](../../docs/schema/pages.md), [`page_layouts.md`](../../docs/schema/page_layouts.md), [`page_widgets.md`](../../docs/schema/page_widgets.md) — the composition tables.

**The model in one breath (verify against the live page, don't trust this summary blindly — it can drift):**
- A page is a **stack of bands**. A band is usually a **`page_layout`** (a column container) — *not* a widget. Memory: *columns are not widgets.*
- Each band holds **`page_widgets`** in column slots (`column_index`). Root widgets have `layout_id = null`.
- **Where appearance lives — the part that bites you:**
  - **Section background** → the **band/layout's** `appearance_config.background.color` (per-band; *not* the `--np-color-bg` theme token — bands carry explicit colours).
  - **Section vertical spacing** → the **band/layout's** `appearance_config.layout.padding` (the **column**, not the widget). Editing widget padding to fix section spacing does nothing.
  - **Button colours** → the **`button_styles`** site setting (`primary.bg_color`, `*.border_color`, `text.text_color`), compiled to `--btn-*`. **Not** the `--np-color-brand` token — button_styles wins. Changing only the brand token leaves buttons unchanged.
  - **Site-wide accent / links / nav** → the `theme_colors` site setting (`--np-color-*` tokens).
  - **Per-widget text colour, padding, border** → the widget's `appearance_config`.
- **Theme colours + button styles are settings compiled into the public bundle** — change them, then `build:public`, then verify the *rendered* site, not just the setting.

**Then inspect the real page.** Dump the target page's bands and widgets (their `appearance_config`) from the DB before planning edits. Read the actual structure; the comp's "sections" map to specific bands.

---

## 3. The import round-trip (what travels, what's applied separately)

- The target page is **exported off the live site and imported locally**; all work happens against that local state. The deliverable is the **re-export the owner pushes back** — so **no seeder edits**, ideally no code.
- **What a full-site export carries:** pages (incl. layouts, widgets, `appearance_config`, copy) + `design` (`theme_colors` + `button_styles` + typography) + media. So page edits **and** theme/button settings round-trip in one bundle — *if* the export includes design (the full-site export defaults it on).
- **What's applied separately on the destination:** the imported colour/button settings only reach the live CSS after a `build:public` (or deploy) on that server. Note this so the owner isn't surprised.
- **Editing local state:** use the running app's models (a throwaway bootstrap script run inside the app container is fine — `tinker` may be absent in a prod-deps container). Delete the script before commit; it is not part of the deliverable.

---

## 4. Run the match, then COMPARE

- Make the edits as **data/settings only**. `build:public` after any colour/button change.
- **Render the local result — desktop and mobile — and compare region-by-region to the comp.** This is the step the 339 pass skipped and paid for. "Looks about right" is not a comparison; put the two side by side, section by section. **Measure** when anything is in doubt — the 339 spacing miss was a literal 2× (column padding 50/50 vs. the comp's ~25/25) that only a pixel measurement caught.
- Re-render after each fix and re-compare; iterate to convergence.

---

## 5. Record gaps

A **gap** is something the comp asks for that the stack **cannot express as data without code** (or only via an ugly workaround). Distinguish from a plain data edit (a default that differs — just change it).

### Gap row format

| Column | What it carries |
|--------|-----------------|
| **Comp asks** | What the comp shows, concretely (e.g. "final-CTA heading orange, subtitle white, in one centred block"). |
| **Stack today** | What the current stack can/can't do, with the specific reason and a `file:line` or setting citation (e.g. "`HtmlSanitizer` strips inline `style` from text tags — `app/Support/HtmlSanitizer.php`"). |
| **Gap** | The precise thing that can't be expressed as data. |
| **Workaround** | What the pass did instead (or "none — left unmatched"). Workarounds are allowed; silent approximations are not. |
| **Proposed change** | The concrete system change that would close it — the row that hands to Widget Autonomy. |

### Worked examples (the session-339 findings — G1–G4 in the track doc)

| Comp asks | Stack today | Gap | Workaround | Proposed change |
|-----------|-------------|-----|------------|-----------------|
| Final-CTA **heading orange, subtitle white** in one block | `HtmlSanitizer` strips inline `style` from `span`/`strong` (`app/Support/HtmlSanitizer.php`); only `img` allows `style`. Widget `text.color` colours the whole block. | Can't colour *part* of a rich-text block. | Split the heading into its own band (one widget per band, both `#252b33`, abutting). | Sanitizer-safe colour: an allow-listed colour class, or a heading-colour `appearance_config` key on text_block. |
| Hero text **flush to the page container** | Hero widget template wraps content in its own `.site-container` (`app/Widgets/Hero/template.blade.php`). Inside an already-contained column it double-contains and insets ~50px. | Hero can't sit flush when used as a column child. | Swapped the hero widget → plain text block (renders flush). | Make the inner container conditional (full-bleed only), or document hero is not a column-child widget. |
| Orange **heading + white subtitle + buttons** stacked in the dark CTA band | `.widget--text_block { height: 100% }` (`resources/scss/_custom.scss`); two text blocks in one grid column each fill it and overflow the band. | Two text blocks can't share one grid column. | One band per block. | Stack-safe multi-widget columns (drop unconditional `height:100%`, or a stacking mode). |
| **80px between the "What it Does" cells when stacked on mobile**, with a tight desktop | Section-spacing primitive (s335) emits vertical padding as `--np-*` vars scaled *down* on mobile (`resources/scss/_layout.scss`). One setting can't be tight on desktop and larger on mobile. | No per-breakpoint section spacing. | Prioritised the desktop comp; mobile cells stack evenly (the uneven/tall bug was a separate `grid_auto_rows:1fr` fix) but at the scaled gap, not 80px. | Mobile-specific spacing override, or a min-gap floor for stacked column children. |

Gaps land in the **session log** and in the track doc's **gap backlog**, which feeds Widget Autonomy.

---

## 6. Close checklist for a pass

- [ ] Comp read in full, both breakpoints; sampled values signed off.
- [ ] Composition model read (docs + live page) before editing.
- [ ] Match done as data/settings only; `build:public` run; rendered site verified (not just settings).
- [ ] Result compared region-by-region to the comp, desktop **and** mobile; discrepancies measured, not eyeballed.
- [ ] Every unmatched thing recorded as a gap row (log + track backlog) — nothing silently approximated.
- [ ] Throwaway edit scripts deleted; deliverable is exportable local state, no seeders, no shipped code.
