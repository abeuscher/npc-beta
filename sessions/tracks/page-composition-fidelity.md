# Track: Page Composition Fidelity

A recurring stress-test of the page-composition stack — widgets, theme tokens, page-builder primitives — against **real external page designs**. Where the Widget Autonomy track asks *"can each widget stand on its own?"*, this track asks *"can the stack as a whole reproduce a design someone hands us, and where it can't, is the gap named clearly?"*

Two purposes, one method:

1. **Find holes before a client does.** Reproducing an outside design with the current stack surfaces the things the stack can't express — *before* a paying client's comp lands on the same limitation.
2. **Build the muscle for executing comps faithfully.** Every pass rehearses the discipline a client comp demands: the comp is the source of truth, the result is compared to it region-by-region, and whatever can't be matched is delineated rather than glossed.

Recurring and opportunistic, not bounded — a pass runs whenever we want to validate the stack against a fresh design (a competitor's page, a chosen reference site, or an actual client comp). Gaps it finds **feed the Widget Autonomy track's backlog**; they are not fixed inside a fidelity pass.

This doc carries the **status snapshot** and the **forward plan**. The reusable per-pass methodology lives in the companion [`page-composition-fidelity-finding-template.md`](page-composition-fidelity-finding-template.md) — read it before running a pass. Closed-pass history will live in a `page-composition-fidelity-retrospectives.md` once the first formal pass closes (created lazily, same convention as the Code Shape & Fit track).

---

## Status snapshot

**Last update:** 2026-06-05 (session 340 close — gap **G2** closed).

**Opened:** session 339. The public-site restyle (a designer comp executed against the imported home page) was the first pass in everything but name — it produced the methodology now captured in the finding-template, and the first gap findings (below). It was *not* run as a clean fidelity pass; it was a real revision that happened to exercise the same machinery, which is why the early miscommunications happened and why this track exists.

**Active:** between passes. The finding-template is the durable output; the next deliberate pass picks an external page to reproduce.

**Next trigger:** opportunistic — when the owner wants to validate the stack against a new design, or when a client comp arrives. No cadence number; this track is demand-driven, not growth-triggered.

---

## Forward plan

### Pass shape

One session per pass (split if it balloons, per the standard workflow rule). Session shape is **`design-comp`** (see the finding-template). The arc of a pass:

1. **Designate + read.** Point at the comp files. Render the comp (text is usually outlined — see the template). Internalise that **the comp is the spec in full** — not just any margin notes on it.
2. **Learn the composition model before touching anything.** Read the widget-system docs *and* inspect the actual target page's structure (bands → layouts → widgets, and where appearance actually lives). This step is non-optional; skipping it is what produced the 339 misfires.
3. **Import the target page** locally and attempt the match using **only** widget / theme / page-builder data + settings. No code in the match.
4. **Render and compare region-by-region** against the comp — desktop *and* mobile.
5. **Log the gaps.** Every place the stack couldn't express the comp as data goes in the pass's gap table, with the workaround used (if any) or an explicit "can't match without code." These are the deliverable.

**No code changes during a pass.** A fidelity pass that "fixes" the widget system mid-match has stopped measuring the system and started changing it. Code-shaped gaps are *findings*, handed to Widget Autonomy. (A pass may still need a throwaway script to write settings/page data — that's tooling, not a stack change.)

### What a gap is

A gap is a place where the comp asks for something the current stack **cannot express as widget / theme / page-builder data** without a code change. Not every deviation is a gap — a default that happens to differ from the comp is a *data edit*, not a gap (change the widget to match; that's the revision rule, not a limitation). A gap is a genuine *can't*, or a *can only via an ugly workaround*. Each gap row carries: what the comp asks → what the stack does today → the gap → the workaround used (or "none") → the proposed system change. Format and worked examples are in the finding-template.

### Discipline — the failure mode this track guards against

The characteristic failure is **declaring a match that isn't one**: treating the session prompt's callouts as the spec, skipping the comp's actual design (alignment, spacing, button styling, typography), and reporting "done." The 339 pass hit this — colours were matched from the green margin notes while the layout, spacing, and button outlines the comp *showed* were under-read. Three guards:

1. **Comp is the source of truth; the page is the lie.** When the rendered page disagrees with the comp, the page is wrong. If a widget's defaults don't match the comp, the widget changes to match it.
2. **Compare to the comp explicitly at close** — render the result, put it beside the comp region-by-region. "Looks about right" is not a comparison. Measure when in doubt (the 339 spacing gap was a 2× column-padding error that only a measurement caught).
3. **Delineate, don't gloss.** Anything the stack couldn't match is named in the gap table. Silent "close enough" defeats the entire point of the track.

### Relationship to other tracks and docs

- **Feeds [Widget Autonomy](widget-autonomy.md).** Gap findings are that track's input — a gap is a concrete "the stack can't do X yet."
- **Reference, not duplication.** The finding-template links the technical references (`docs/widget-system.md`, `-spec`, `-conventions`, `docs/theme-color-tokens.md`) rather than restating them. Those docs are canonical for *how the system works*; this track is *how to run a fidelity pass against it*.

### What lives where

- **This doc** — track premise, status, pass shape, discipline.
- **[`page-composition-fidelity-finding-template.md`](page-composition-fidelity-finding-template.md)** — the per-pass methodology + gap row format + worked examples. Read before every pass.
- **`sessions/session-outlines.md`** — a one-line pointer here under active tracks.
- **Per-pass detail** — the session log (`sessions/NNN. … — Log.md`), including that pass's gap table.

---

## Gap backlog (open)

First findings, from the session 339 pass. Each is a candidate for the Widget Autonomy backlog. Full row detail (what-the-comp-asked / workaround) is in the finding-template's worked examples.

| # | Gap | Workaround used in 339 | Proposed system change |
|---|-----|------------------------|------------------------|
| G1 | **Rich text can't colour part of a block.** The HTML sanitizer strips inline `style` from text tags, so a heading-in-one-colour over body-in-another inside a single text block is impossible. | Split the heading into its own band (one widget per band). | A sanitizer-safe text-colour mechanism (allow-listed colour class, or a heading-colour appearance key on text_block). |
| ~~G2~~ | ~~**Hero widget double-contains inside a column.**~~ **Closed s340.** The Hero widget wraps its content in its own `.site-container`; placed inside an already-contained column layout it insets ~50px and won't sit flush to the page container. | Swapped the hero to a plain text block. | **Done (s340):** the `.hero--pos-*-left` variants in `_custom.scss` neutralize the inner `.site-container`'s width cap / centering / horizontal padding so left-aligned content sits flush to the outer container edge (left-position variants only). |
| G3 | **Two text blocks can't share one grid column.** `.widget--text_block { height: 100% }` makes each fill the column; two in one column fight and overflow the band. | Gave each block its own band. | Stack-safe multi-widget columns (drop the unconditional `height:100%`, or a column stacking mode). |
| G4 | **No per-breakpoint section spacing.** The section-spacing primitive (s335) only scales *down* on mobile, so tight-desktop and larger-mobile spacing can't come from one setting (the comp's "80px between mobile cells" vs. a tight desktop). | Prioritised the desktop comp; mobile cells stack evenly but at the scaled-down gap, not 80px. | A mobile-specific spacing override, or a min-gap floor for stacked column children. |

Retirement: a gap leaves this table when Widget Autonomy lands the change (cite the session) or when a later pass proves it isn't actually a gap.

---

## Pass Retrospectives

Closed-pass history will live in `page-composition-fidelity-retrospectives.md`, created when the first deliberate pass closes. Until then, the 339 pass's detail is in its session log and the gap backlog above.
