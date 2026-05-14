# Public Website Build — Gap Report

Each row follows the brief's four-field shape: **what was attempted / what blocked it / workaround used / recommended action.** Rows accumulate across phases. The user accepts each gap individually — either as "won't fix — accepted as degraded" or as a lift into a follow-on session per `sessions/tracks/public-marketing-website.md`'s gap-resolution discipline.

Surfaced at PMW1 (session 284). Subsequent phases append.

---

## PMW1 findings

### G1 — Layout-level `appearance_config` does not round-trip through export/import

- **What was attempted:** setting `background.color: #d4d4f2` on a column layout so the "What it does" section's 2-column grid sits on the same tinted slab as its heading, per the brief's *"Background lives on the type: 'layout' block, not on the widgets inside it"* convention.
- **What blocked it:** `App\Services\ImportExport\ContentExporter::serializeLayout` does not emit a layout's `appearance_config`. The renderer (`AppearanceStyleComposer::composeForLayout`) reads from it; the `page_layouts` table has the column; the admin UI can set it. Only the bundle round-trip drops the value silently. Confirmed by reading the importer (`ContentImporter::hydrateLayout`) — `appearance_config` is not passed to `$owner->layouts()->create`.
- **Workaround used:** kept the existing pattern on the home page (heading-tinted slab, grid widgets on white per-widget backgrounds). Tinted band ends at the heading, not at the grid. Per-widget `background.color` carries the slab tint where needed.
- **Recommended action:** extend `ContentExporter::serializeLayout` to include `appearance_config`, and extend `ContentImporter::hydrateLayout` to pass it through on layout creation. **High priority for the track** — the brief's "background lives on the layout block" convention is the canonical pattern for section-band slabs across all five marketing pages. Without this fix, every roundtripped page silently loses its layout-level styling, including pages that are perfectly authored in the admin but exported/re-imported for backup.

### G2 — Dark-band widget cannot carry a CTA

- **What was attempted:** adding the `[More about how this works →]` CTA that copy.md specifies at the end of the "This is not a SaaS company" dark band.
- **What blocked it:** the dark band renders via `text_block`, which has no `ctas` schema field — only `hero` carries CTAs. Converting the dark band to a `hero` would swap widget types and absorb rendering differences (hero's bg-image / video / overlap-nav behaviors don't all fit a section band).
- **Workaround used:** dark band ships CTA-less in this iteration.
- **Recommended action:** either (a) add CTA capability to `text_block` (small schema add), or (b) add a lightweight standalone-CTA widget that sits beneath a text block, or (c) convert the dark band to `hero` and accept the rendering differences. (b) is closer to the brief's no-invented-widgets posture; (a) is the most flexible. Decision can wait — the band reads fine without the CTA in the meantime.

### G3 — Buttons need a "regular + night-mode" colorway per variant

- **What was attempted:** placing primary + secondary CTAs on Hero 1's blue gradient and planning the dark-band CTA from G2. Both surface the same underlying problem: every button variant is colored for light backgrounds only.
- **What blocked it:** the five button variants (`primary`, `secondary`, `text`, `destructive`, `link`) are each a single colorway. `primary` solid blue reads ok on light, off-tone on dark gradients. `secondary` transparent + gray-border disappears against anything dark. `text` blue is low-contrast on dark. No variant has a "ghost on dark" companion.
- **Workaround used (interim, session 284 explicit ask):** tuned `secondary` to a white-fill pill with dark border + dark text (`bg #ffffff, text #111827, border #111827`). Reads on light *and* dark backgrounds. Loses some of the "ghost" feel on light but stays consistent. Persisted in the `button_styles` SiteSetting; public CSS bundle rebuilt.
- **Recommended action (long-term, user direction at session 284):** **per-variant "regular + night-mode" colorways.** Each of the five variants gains a paired dark-bg colorway (`primary-dark`, `secondary-dark`, etc.) — or the system gains a single colorway-pair shape on each variant. Operator picks which colorway to use per CTA (Hero CTAs already have a "Button Style" select per CTA — extend the options list). Open architectural question: does the operator pick explicitly, or does the system infer from the surrounding band's background tone? Inference is friendlier but couples button rendering to surrounding-widget context. Explicit is simpler but doubles the dropdown options. **High priority — this is the underlying pattern behind G2, G3, G13 below, and likely more as About / Pricing / Demo land.**

### G4 — `mailto:` address is a placeholder

- **What was attempted:** writing the "Email me" CTA on the Final CTA band per copy.md.
- **What blocked it:** copy.md instructs the agent to use `mailto:al@example.com` and surface a request for the real address; the real address is not in scope for this track to decide.
- **Workaround used:** shipped `mailto:al@example.com`. The same placeholder will land on Pricing's à la carte band, Contact's hero / email band, and any other page that gets an Email CTA.
- **Recommended action:** **user supplies the real `mailto:` address before PMW4 close.** One-line fix across all five pages once known. Track this as a single open item rather than per-page.

### G5 — No "Try the demo →" CTA below the "What it does" grid

- **What was attempted:** verifying the closing CTA that copy.md specifies under the four-column "What it does" grid is present.
- **What blocked it:** the existing export's "What it does" section ends at the second 2-column grid row with no closing CTA widget. The section reads abruptly without it.
- **Workaround used:** left absent in this iteration; the home cleanup is strictly structural, and adding a brand-new widget for missing content felt outside scope. Flagged here.
- **Recommended action:** add a closing CTA widget — a `hero` with empty content and one primary CTA — between the second grid row and the dark band, when the gap is being lifted. Sequence with PMW2 or as a quick follow-on.

### G6 — No tabbed-feature-explainer widget

- **What was attempted:** forecasting widget inventory against the four reference pages. Pricing's "What $150/month gets you" + "What's not included" pairing, About's structural expansion, and Demo's "What happens next" all read as places a tabbed or grouped feature explainer could be the natural shape.
- **What blocked it:** no `tabbed_*` or `feature_explainer` widget in `WidgetRegistry`. Closest substitute: stacked text blocks under their own headings, or a vertical `three_buckets`.
- **Workaround used:** not exercised this phase. Home does not need it.
- **Recommended action:** defer to PMW2. The brief names this as the most likely missing-widget candidate. PMW2's Pricing build-out will surface whether the substitute (stacked text blocks) is good enough; if not, lift to a follow-on session.

### G7 — No headline-only widget separate from `hero`

- **What was attempted:** noting widget overhead in the cleaned home — the new Pricing band and Final CTA band each use `hero` for what is essentially "heading + body + CTAs," not a true full-bleed banner. The hero widget carries fullscreen / overlap-nav / scroll-indicator / nav-color knobs that are inert in band-shape usage.
- **What blocked it:** `text_block` has no CTAs (G2); `hero` carries too much. No middle-weight widget exists for "heading + body + CTAs on a section band."
- **Workaround used:** used `hero` with band-shape `appearance_config`. Renders fine; just feels heavier than the section needs.
- **Recommended action:** **low priority.** Decide at PMW4 close whether the pattern is uncomfortable enough across all five pages to want its own widget, or whether `hero`'s coverage is good enough. May also resolve naturally if G2's fix (CTA capability on `text_block`) lands first.

### G8 — Sample-image library has no product-screenshot category

- **What was attempted:** planning where the "real product screenshot inside a soft container" pattern (per `reference-restrained-crm-page.png`) would slot in to home / about / pricing / demo.
- **What blocked it:** the four `SampleImage` categories are `logos`, `portraits`, `product-photos`, `still-photos`. None hold real product screenshots. The brief excludes real photography from this track ("real photos come post-track") — but the placeholder swap requires a screenshot-shaped placeholder so the eventual position is reserved.
- **Workaround used:** home cleanup contains no product-screenshot placeholder this phase; the existing page also doesn't have one. About / Pricing likely will want one.
- **Recommended action:** add a fifth `CATEGORY_SCREENSHOTS` (or `CATEGORY_PRODUCT_SCREENSHOTS`) on `App\Models\SampleImage` with three-to-five starter screenshot images. Or — accept that still-photos stand in until real photography lands. Decide at PMW2 once About / Pricing surface the need.

### G9 — Hero 1 "Try the Demo" CTA was routed to `/about`

- **What was attempted:** verifying CTAs match the brief's discipline ("every marketing CTA across all pages routes to /demo").
- **What blocked it:** the existing export had Hero 1's primary CTA wired to `/about`, not `/demo`.
- **Workaround used:** **re-routed to `/demo` in this cleanup.** One-attribute fix.
- **Recommended action:** done. Row exists in case a future re-export comes back from admin with the same value (i.e. someone edits home in the admin and routes a CTA wrong).

### G10 — Hero 2 in the existing export merged Pricing and "Try it" bands

- **What was attempted:** aligning home with copy.md's band rhythm: *Pricing band → What it doesn't do → Final CTA band.*
- **What blocked it:** the existing Hero 2 carried both "Pricing" and "Try it" content concatenated into one widget, with only `[Full pricing →]` wired and the copy.md-specified `[Start the demo]` + `[Email me]` CTAs missing. Order in the existing page was *Pricing+TryIt → What it doesn't do.*
- **Workaround used:** **split Hero 2 into two `hero` widgets** in the cleanup. Re-ordered to match copy.md's rhythm. Each carries the CTAs copy.md specifies.
- **Recommended action:** done. Row exists so the structural choice is recorded — splitting a merged band is the precedent for any similar pattern in the rest of the track.

### G11 — Hero CTA horizontal alignment is locked to the headline-position knob, with a `str_contains` bug

- **What was attempted:** matching Hero 1's CTA horizontal position to the surrounding text content. The existing Hero 1 had text Quill-default (left-aligned) but CTAs were centered — visually misaligned per session-284 user feedback.
- **What blocked it:** the Hero blade template derives CTA alignment from `config.text_position` via `str_contains($position, 'center') ? 'center' : (… 'right' ? 'right' : 'left')`. The check matches *both* the vertical "center" axis (in `center-anything`) and the horizontal "center" axis (in `anything-center`). So `center-left` (vertically-centered, horizontally-left) returns `'center'` for CTA alignment, not `'left'`. There is no way to express "vertically-centered, horizontally-left CTAs" through the existing schema.
- **Workaround used:** kept `text_position: center-center` and added `ql-align-center` classes to the headline's `<h2>` and body `<p>` so the text matches the centered CTAs. Aligned visually; both sides now centered.
- **Recommended action (cheap; user flagged for "not this pass" consideration):** add a `button_alignment` schema field to the Hero widget (default `'inherit'`, options `inherit / left / center / right`). When set, it overrides the str_contains-derived value in the template. Roughly 10 minutes of work. Decouples CTA placement from headline-position, supports any layout where the operator wants centered headline + left-aligned CTAs (or vice versa). Also fixes the `center-left` bug as a side effect.

### G12 — Logo contrast on overlap-nav heroes

- **What was attempted:** keeping Hero 1 in its existing `overlap_nav: true` shape (gradient bleeds behind the nav) while making the logo readable.
- **What blocked it:** the Logo widget renders with `color: inherit`, defaulting to the page template's text color (dark). It has no `appearance_config.text.color` configured. The Hero widget exposes `nav_link_color` and `nav_hover_color` knobs to override nav-text color over a dark hero, but no equivalent reaches the Logo widget — they're sibling widgets in the page template's header. The CSS variable `--nav-link-color` set by the Hero only scopes to `.widget-nav` styling. Setting Logo's appearance_config text-color globally would break other pages where the logo sits on a light background.
- **Workaround used:** **toggled Hero 1's `overlap_nav: true → false`** in the cleaned home. The hero gradient now starts below the page header. Logo sits on the template's (light) header background, regains its native contrast. Cleared `nav_link_color` / `nav_hover_color` on Hero 1 since they're moot when nav doesn't overlap. Side effect: lost the "gradient bleeds behind nav" full-bleed look — none of the four reference pages do this, so the trade-off is reference-aligned.
- **Recommended action:** **two paths, dependent on G3's resolution.** (a) If G3's per-variant night-mode colorways land, generalize the same pattern to Logo (`appearance_config.text.color_dark`?) — or add a Hero-widget `nav_logo_color` knob that wires through to a `--logo-color` CSS variable the Logo SCSS honors. (b) If overlap-nav is rarely needed, accept the workaround as the convention. The references suggest (b) is the right call.

### G13 — Hero headline scale undersized vs reference pages

- **What was attempted:** identifying differences between the cleaned home and the four reference pages (Attio / Marketo / Clay / Gong). Hero headline reads "small" by comparison.
- **What blocked it:** `TypographyResolver` defaults set `h1 = 2.5rem` (40px) and `h2 = 2.0rem` (32px). Reference hero headlines run ~50–80px (Attio ~50px h1; Clay ~80px; Gong ~70px uppercase; Marketo ~36px h2). The existing home Hero 1 uses `<h2>` (32px), which is at the small end of all four references. References generally use 3–5× body size for hero headlines; NPC default is 2×.
- **Workaround used:** none this phase. Surfaced for the user's decision before tuning typography defaults.
- **Recommended action:** **tune typography defaults via the Theme page** (CMS → Theme → Text Styles). Suggested deltas to consider: `h1 → 4rem` (64px), `h2 → 2.75rem` (44px), `h3 → 1.875rem` (30px). Margin-bottom on headings (currently 0) → `1.25rem` so headings breathe before body. Aligns with E5 (Mobile Type Scaling), which is sequenced after PMW1 specifically so PMW1's audit informs it. This is a track-wide tuning decision — touches every page, not just the home.

### G14 — Intra-section vertical rhythm is tighter than reference pages

- **What was attempted:** sizing the gap between heading, body, and CTAs within a band against the reference pages.
- **What blocked it:** `TypographyResolver` sets all element margins (top / right / bottom / left) to 0 by default. Inside Quill content, this defers to browser-default margins (~1em on `<p>`, browser-specific on headings). The references show generous gaps — 24–40px between heading and body, 32–48px between body and CTA buttons. NPC's default is at-or-below browser defaults.
- **Workaround used:** none this phase. Same surface as G13 — tunes once at the theme level.
- **Recommended action:** alongside G13's scale tuning, set heading margin-bottom: `h1 1.5rem`, `h2 1.25rem`, `h3 1rem`. Paragraph margin-bottom: `1rem`. Hero widget's `.hero-ctas` margin-top: ~`2rem` (template SCSS, not typography). These are global tuning decisions — discuss before committing.
