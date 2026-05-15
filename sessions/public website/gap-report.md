# Public Website Build — Gap Report

Each row follows the brief's four-field shape: **what was attempted / what blocked it / workaround used / recommended action.** Rows accumulate across phases. The user accepts each gap individually — either as "won't fix — accepted as degraded" or as a lift into a follow-on session per `sessions/tracks/public-marketing-website.md`'s gap-resolution discipline.

Surfaced at PMW1 (session 284). Subsequent phases append.

---

## PMW1 findings

### G1 — Layout-level `appearance_config` does not round-trip through export/import ✅ resolved at 285

- **What was attempted:** setting `background.color: #d4d4f2` on a column layout so the "What it does" section's 2-column grid sits on the same tinted slab as its heading, per the brief's *"Background lives on the type: 'layout' block, not on the widgets inside it"* convention.
- **What blocked it:** `App\Services\ImportExport\ContentExporter::serializeLayout` does not emit a layout's `appearance_config`. The renderer (`AppearanceStyleComposer::composeForLayout`) reads from it; the `page_layouts` table has the column; the admin UI can set it. Only the bundle round-trip drops the value silently. Confirmed by reading the importer (`ContentImporter::hydrateLayout`) — `appearance_config` is not passed to `$owner->layouts()->create`.
- **Workaround used:** kept the existing pattern on the home page (heading-tinted slab, grid widgets on white per-widget backgrounds). Tinted band ends at the heading, not at the grid. Per-widget `background.color` carries the slab tint where needed.
- **Recommended action:** extend `ContentExporter::serializeLayout` to include `appearance_config`, and extend `ContentImporter::hydrateLayout` to pass it through on layout creation. **High priority for the track** — the brief's "background lives on the layout block" convention is the canonical pattern for section-band slabs across all five marketing pages. Without this fix, every roundtripped page silently loses its layout-level styling, including pages that are perfectly authored in the admin but exported/re-imported for backup.
- **Resolution (285):** exporter emits and importer hydrates `appearance_config` on column layouts. Two Pest round-trip cases cover populated and empty payloads.

### G2 — Dark-band widget cannot carry a CTA ✅ resolved at 285

- **What was attempted:** adding the `[More about how this works →]` CTA that copy.md specifies at the end of the "This is not a SaaS company" dark band.
- **What blocked it:** the dark band renders via `text_block`, which has no `ctas` schema field — only `hero` carries CTAs. Converting the dark band to a `hero` would swap widget types and absorb rendering differences (hero's bg-image / video / overlap-nav behaviors don't all fit a section band).
- **Workaround used:** dark band ships CTA-less in this iteration.
- **Recommended action:** either (a) add CTA capability to `text_block` (small schema add), or (b) add a lightweight standalone-CTA widget that sits beneath a text block, or (c) convert the dark band to `hero` and accept the rendering differences. (b) is closer to the brief's no-invented-widgets posture; (a) is the most flexible. Decision can wait — the band reads fine without the CTA in the meantime.
- **Resolution (285):** path (a). TextBlock gained a `ctas` field mirroring Hero's full shape plus a `cta_alignment` field (`inherit / left / center / right`; inherit follows Quill `ql-align-*` classes).

### G3 — Buttons need a "regular + night-mode" colorway per variant ✅ interim path resolved at 285; long-term direction open

- **What was attempted:** placing primary + secondary CTAs on Hero 1's blue gradient and planning the dark-band CTA from G2. Both surface the same underlying problem: every button variant is colored for light backgrounds only.
- **What blocked it:** the five button variants (`primary`, `secondary`, `text`, `destructive`, `link`) are each a single colorway. `primary` solid blue reads ok on light, off-tone on dark gradients. `secondary` transparent + gray-border disappears against anything dark. `text` blue is low-contrast on dark. No variant has a "ghost on dark" companion.
- **Workaround used (interim, session 284 explicit ask):** tuned `secondary` to a white-fill pill with dark border + dark text (`bg #ffffff, text #111827, border #111827`). Reads on light *and* dark backgrounds. Loses some of the "ghost" feel on light but stays consistent. Persisted in the `button_styles` SiteSetting; public CSS bundle rebuilt.
- **Recommended action (long-term, user direction at session 284):** **per-variant "regular + night-mode" colorways.** Each of the five variants gains a paired dark-bg colorway (`primary-dark`, `secondary-dark`, etc.) — or the system gains a single colorway-pair shape on each variant. Operator picks which colorway to use per CTA (Hero CTAs already have a "Button Style" select per CTA — extend the options list). Open architectural question: does the operator pick explicitly, or does the system infer from the surrounding band's background tone? Inference is friendlier but couples button rendering to surrounding-widget context. Explicit is simpler but doubles the dropdown options. **High priority — this is the underlying pattern behind G2, G3, G13 below, and likely more as About / Pricing / Demo land.**
- **Resolution (285, interim path):** one new `secondary-dark` variant added — transparent fill, white text, white outline, opacity hover. Surfaced in DesignSystemPage as "Secondary (Dark)" and selectable in Hero + TextBlock CTA style pickers. Bundle rebuilt. The long-term direction (per-variant pairing or context-aware inference) stays open as a future session.

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

### G13 — Hero headline scale undersized vs reference pages ✅ resolved at 285

- **What was attempted:** identifying differences between the cleaned home and the four reference pages (Attio / Marketo / Clay / Gong). Hero headline reads "small" by comparison.
- **What blocked it:** `TypographyResolver` defaults set `h1 = 2.5rem` (40px) and `h2 = 2.0rem` (32px). Reference hero headlines run ~50–80px (Attio ~50px h1; Clay ~80px; Gong ~70px uppercase; Marketo ~36px h2). The existing home Hero 1 uses `<h2>` (32px), which is at the small end of all four references. References generally use 3–5× body size for hero headlines; NPC default is 2×.
- **Workaround used:** none this phase. Surfaced for the user's decision before tuning typography defaults.
- **Recommended action:** **tune typography defaults via the Theme page** (CMS → Theme → Text Styles). Suggested deltas to consider: `h1 → 4rem` (64px), `h2 → 2.75rem` (44px), `h3 → 1.875rem` (30px). Margin-bottom on headings (currently 0) → `1.25rem` so headings breathe before body. Aligns with E5 (Mobile Type Scaling), which is sequenced after PMW1 specifically so PMW1's audit informs it. This is a track-wide tuning decision — touches every page, not just the home.
- **Resolution (285):** pushed `h1 → 3.5rem` (56px), `h2 → 2.5rem` (40px), `h3 → 1.75rem` (28px) via the `typography` SiteSetting JSON. H2↔H3 gap widened from 0.5rem to 0.75rem so the step-down reads cleaner. Heading margin-bottoms covered under G14.

### G14 — Intra-section vertical rhythm is tighter than reference pages ✅ resolved at 285

- **What was attempted:** sizing the gap between heading, body, and CTAs within a band against the reference pages.
- **What blocked it:** `TypographyResolver` sets all element margins (top / right / bottom / left) to 0 by default. Inside Quill content, this defers to browser-default margins (~1em on `<p>`, browser-specific on headings). The references show generous gaps — 24–40px between heading and body, 32–48px between body and CTA buttons. NPC's default is at-or-below browser defaults.
- **Workaround used:** none this phase. Same surface as G13 — tunes once at the theme level.
- **Recommended action:** alongside G13's scale tuning, set heading margin-bottom: `h1 1.5rem`, `h2 1.25rem`, `h3 1rem`. Paragraph margin-bottom: `1rem`. Hero widget's `.hero-ctas` margin-top: ~`2rem` (template SCSS, not typography). These are global tuning decisions — discuss before committing.
- **Resolution (285):** pushed all four margin-bottom values (`h1 1.5rem / h2 1.25rem / h3 1rem / p 1rem`) into the `typography` SiteSetting. Hero `.hero-ctas` margin-top was not touched this session — TextBlock got its own `.widget-text-block__ctas` margin-top of `1.5rem` for its new CTA wrapper, which is the closer analogue.

---

## Session 286 findings

### G15 — Hero widget renders its own `.site-container` inside a layout cell, causing nested containers

- **What was attempted:** placing the Hero widget in the left cell of Band 1's 2-column layout per the homepage-layout-spec's fallback path ("If a single Hero widget instance must be used... put the Hero in the left cell and the Image widget in the right cell"). The Hero carries the headline + body + CTAs; the gradient sits on the parent layout container.
- **What blocked it:** the Hero blade template wraps its content in `<div class="hero-body"><div class="site-container">...</div></div>`. The parent column layout already wraps its grid in a `.site-container` (when `content_full_width: false`). Two nested `.site-container` elements result — the inner one is effectively a no-op because both cap to the same max-width, but it's architectural noise that lingers. Same issue applies to any future band that places a Hero inside a column-layout cell.
- **Workaround used:** accepted the nested wrappers — visual rendering looks correct and the hero band reads as intended. No code change required for the home to ship.
- **Recommended action:** **two options.** (a) Detect at template time whether the Hero is rendered inside a layout slot (the renderer already passes context) and skip the inner `.site-container` wrapper when so. (b) Restructure Band 1 to use a Text widget instead of Hero in the left cell — with G2 resolved, Text + CTAs is now expressible. (b) is a JSON-only change and side-steps the issue. (a) is a one-template-line conditional that helps any future Hero-in-cell pattern across all five marketing pages. Decide as part of the layout-spec-approach validation review.

### G16 — Hero `fullscreen: true` inside a layout cell stretches the cell to 100vh

- **What was attempted:** preserving the existing Hero's `fullscreen: true` behavior when relocating the Hero into Band 1's left layout cell, per the layout spec's "preserve the existing fullscreen: true behavior currently set on the hero widget" directive.
- **What blocked it:** `fullscreen: true` adds the `hero--fullscreen` CSS class, which sets `min-height: 100vh` on the hero element. Inside a column-layout cell, that propagates: the cell stretches to 100vh, the layout's grid row matches (with `align-items: center`), and the Image cell adjacent stretches to the same height. The layout's explicit `padding: 200/200` is no longer the controlling factor for band height — viewport height is. On a tall desktop viewport this reads fine (taller hero matches reference scale); on a short laptop screen it makes the hero feel oversized.
- **Workaround used:** kept `fullscreen: true` per the spec's directive. Logged here so the surface is visible.
- **Recommended action:** **two options, similar to G15.** (a) Move `fullscreen` to a layout-container option (extend `layout_config` with a `fullscreen` boolean that sets `min-height: 100vh` on the `.page-layout` outer rather than on the widget). (b) Drop `fullscreen` from the Band 1 hero and let `padding: 200/200` on the layout control band height — reads more deterministically across viewports. (a) is the cleaner long-term shape (a hero band's fullscreen-ness is really a band property, not a widget property); (b) is the JSON-only escape hatch. The spec already anticipates this as a gap.

### G17 — TextBlock requires non-empty content to render cleanly as a CTA-only band

- **What was attempted:** rendering Band 4's CTA footer (centered "Try the demo →" button below the 4-cell grid) using a Text widget with empty content + `ctas` set, per the spec's "Below the grid — CTA footer" pattern with G2's new CTA capability.
- **What blocked it:** TextBlock renders content + CTAs as sibling elements. With no content, the `.widget-text-block__content` div is empty but still in the DOM, and the CTAs render below it. A `<p><br></p>` placeholder was used to give the empty paragraph a defined shape so the vertical spacing is predictable across browsers.
- **Workaround used:** placeholder `<p class="ql-align-center"><br></p>` in the content field. Renders fine; placeholder is invisible.
- **Recommended action:** **low priority.** Either (a) Text widget's template skips the content wrapper entirely when content is empty (one conditional), or (b) accept the placeholder pattern as the convention for CTA-only Text widget usage. (a) is cleaner if many bands across the five pages end up as CTA-only Text widgets; (b) is fine if the pattern stays rare. Decide as the About / Pricing / Contact / Demo builds surface how common CTA-only bands are.

### G18 — Image widget has no built-in container width / placement controls

- **What was attempted:** placing the Image widget in column cells of bands 1 (4:3), 2 (1:1), 5 (4:5), and 7 (1:1), per the layout spec. Images fill their column cells edge-to-edge.
- **What blocked it:** the Image widget's `max_width` field (added at 285) controls the image's max horizontal size but doesn't address whether the image sits flush to its column edge, has internal padding, or is centered within a wider cell. The 4:5 portrait in Band 5's dark band stretches to the full column width and reads as dominant. The 1:1 image in Band 7's narrow column reads as smaller (correctly, given 2fr proportions).
- **Workaround used:** left `max_width` blank (full-cell fill) across all four image placeholders. Reads acceptable; the eventual real photography swap can revisit per-band.
- **Recommended action:** **defer.** With real photography in place (founder portraits, framed product shots), the right call may be obvious. Until then, full-cell fill is the predictable default. If the real-photo pass surfaces a consistent need for centered-within-cell or padded behavior, lift then.

---

## Session 287 findings

### G19 — `appearance_config` has no border knob for pull-quote treatment

- **What was attempted:** rendering Band 2 widget 2 of the About page as a visually-distinct pull-quote per the about-layout-spec — "left padding to indent the block, a left border (1-4px solid in a neutral accent color), increased font weight or italic via Quill formatting inside the content, slightly larger size if available."
- **What blocked it:** `appearance_config` carries `layout.padding`, `layout.margin`, `background.color`, `background.gradient`, `text.color`, `text.shadow` — no border-color / border-width / border-style fields on any side. The composer (`AppearanceStyleComposer`) cannot emit a left border from existing schema. The typography system has no pull-quote element row (the nine rows are h1–h6, p, ul_li, ol_li).
- **Workaround used:** spec's named fallback path — left padding (32px), heavier vertical padding (50/50) to set the block apart from surrounding paragraphs, bold + italic Quill formatting inside the content, right-aligned attribution paragraph below. Renders as visually distinct but lacks the left-border accent the spec's primary path called for.
- **Recommended action:** two options. **(a)** Extend `appearance_config` with a `border` group (`border.color`, `border.width`, `border.style`, `border.sides` — top/right/bottom/left or shorthand) and wire it through `AppearanceStyleComposer`. Reusable across any future band-side treatment, not just pull-quotes. **(b)** Add a new typography element row `blockquote` with its own size / weight / margin / padding / border defaults, and use Quill's `<blockquote>` block format inside the content. (b) is closer to the typography-as-source-of-truth pattern; (a) is more flexible per-instance. Either keeps the eventual real-pull-quote treatment from depending on inline styles. Low-priority — the workaround reads acceptably and the slot is a placeholder until the user fills the Cherny quote.

### G20 — Global `.widget--text_block { height: 100% }` rule breaks stacked text widgets in a single layout cell

- **What was attempted:** widening About Band 2 from the spec's `1fr 2fr 1fr` spacer-cell pattern to a full-container-width single-column layout (`columns: 1, grid_template_columns: "1fr"`), keeping the four spec'd text widgets stacked inside the single layout cell (heading + lead → pull-quote → outsourced argument → closing).
- **What blocked it:** the global SCSS rule at `resources/scss/_custom.scss:144` sets `.widget--text_block { height: 100%; }`. In the original 3-column spec'd layout the rule was harmless because the four widgets stacked block-flow inside an auto-height column and the `100%` resolved to natural-content. In the single-column variant the grid track became a definite height (643px — the heading + lead paragraph's natural content height), every text_block then resolved `height: 100%` to that definite 643px, and the four widgets stacked at 4×643=2572px while their parent column reported 643px. Result: the heading filled the visible cell, the next three widgets rendered below the dark Band 3, and the page looked structurally broken.
- **Workaround used:** split Band 2 into four sibling `page-layout` blocks (`Band 2a / 2b / 2c / 2d`), each `columns: 1` single-column white-background, each holding one text_block. With one widget per grid cell the `height: 100%` rule resolves to the single widget's natural content height and everything renders correctly. Inter-layout padding (150/0 on 2a, 50/50 on 2b, 0/0 on 2c, 25/100 on 2d) preserves the band's vertical rhythm; the shared white background and tight padding make the four layouts read as one composite band, same pattern as home Band 4's grid + CTA-footer composite.
- **Recommended action:** narrow the `height: 100%` rule's scope so it only applies when a text_block is the **only** widget in its column slot — e.g. `.layout-column > .widget--text_block:only-child { height: 100%; }`, or move the rule onto a class the renderer applies conditionally. The rule's intent (vertical-balance text widgets across multi-column rows) is preserved; the collision with stacked-siblings-in-one-cell goes away. Alternative: drop the rule entirely and rely on per-widget `vertical_align` for cross-cell balancing. Either avoids the trap recurring on Pricing / Contact / Demo when stacked-text bands are likely to be needed.

### G15 / G16 — not exercised on About

- **G15 (Hero widget renders its own `.site-container` inside a layout cell):** not exercised on the About rebuild. Band 1 hero uses Text widget + Image widget per the 286 revision-pass precedent, side-stepping the nested-container surface entirely. Status: open from 286, no new manifestation this session.
- **G16 (Hero `fullscreen: true` inside a layout cell):** same — not exercised. Band 1 height is controlled by layout padding (100/200) alone, per the spec's explicit directive ("the Hero widget's `fullscreen` attribute is not used on this band"). Status: open from 286, no new manifestation this session.

### G17 — not exercised on About

- The About spec has no CTA-only band. Band 3 carries body content + section heading + body + sub-heading + body + body + three CTAs in a single Text widget. The placeholder-content workaround was not needed. Status: open from 286, no new manifestation this session.

### G18 — continued manifestation on About

- Both image cells (Band 1 `about-hero-portrait` 4:3, Band 3 `about-execution-supporting-image` 1:1) ship with `max_width` blank and no per-cell placement controls. Full-cell-fill default applies, same as the four image cells on the home. Status: open from 286, same workaround acceptable.

---

## Session 288 findings

### G-pricing-1 — border styling in `appearance_config` (widget-level partial)

- **What was attempted:** building the new `pricing_chart` widget with the visual emphasis on the recommended column expressed as a border (or bordered card treatment). Per the widget spec's design notes — "v1.1 emphasis = thicker / differently-colored border."
- **What blocked it:** same root cause as G19 — `appearance_config` has no border knob, and the composer (`AppearanceStyleComposer`) cannot emit border CSS from existing schema.
- **Workaround used:** the widget ships v1 with the spec's accepted background-tint emphasis (`#f8f9fa` on emphasized columns; pure white on the others). The Blade template carries an empty border slot via CSS custom properties (`--pc-border-color`, `--pc-border-width`, `--pc-border-color-emphasized`), all defaulting to transparent / 0. v1.1 lights up the slot via `appearance_config` extension — config change, not a template rewrite.
- **Recommended action:** still G19's options — extend `appearance_config` with a border group and wire it through `AppearanceStyleComposer`. The pricing-chart widget is now the second concrete consumer (after the About pull-quote); both light up cleanly when the system extension lands. Low priority — the workaround reads acceptably for v1, the user can lift independently.

### G19 — pull-quote / panel border (continued from 287)

The pricing-chart widget hits the same underlying `appearance_config` border-knob surface as About's pull-quote (G19). Both consumers ship with the same v1 workaround (background tint + padding instead of border accent). When the appearance-config border knob lands, both surfaces upgrade in one extension. No new row needed; logged here so the second consumer is visible.

### `presets()` shape constraint surfaced (not a new gap, design observation)

The release-plan entry for 288 called for a v1 "Marketing site tiers" preset on the new widget. `WidgetManifestTest`'s preset rule forbids content-group keys in `preset.config` — and the widget's `columns` field is content-group. Resolved by exposing the marketing-site tier configuration as a public `marketingSiteTiers()` method on the definition class plus baking it into `demoConfig()`. Session 289 reaches into the helper for its one-click setup; same outcome as a preset, different mechanism.

This isn't a gap to fix — the preset rule is correct (presets are designed as appearance-only "skin swaps" that preserve content). It does suggest a future pattern: structured-content arrays like `columns` may want a separate "starter content" / "template" concept distinct from appearance presets. Not a session 288 fix; logged for awareness.

---

## Session 289 findings

### G15 / G16 — not exercised on Pricing

- **G15 (Hero widget renders its own `.site-container` inside a layout cell):** not exercised. Band 1 hero is Text widget + Image widget per About Band 1's precedent (286 revision-pass + 287 application), side-stepping the nested-container surface. Status: open from 286, no new manifestation this session.
- **G16 (Hero `fullscreen: true` inside a layout cell):** not exercised. Band 1 height is controlled by layout padding (100/200) alone — `fullscreen` not used. Status: open from 286, no new manifestation this session.

### G17 — not exercised on Pricing

- No CTA-only Text widget bands on the Pricing page. Band 5 (Final CTA) carries H2 + body + smaller-italic question paragraph + two CTAs in a single Text widget — non-empty content + CTAs, so the placeholder-content workaround was not needed. Status: open from 286, no new manifestation this session.

### G18 — continued manifestation on Pricing

- Both image cells (Band 1 `pricing-hero-portrait` 4:3, Band 4 `pricing-pii-supporting-image` 1:1) ship with `max_width` blank and no per-cell placement controls. Full-cell-fill default applies, same as the home and About image cells. Status: open from 286, same workaround acceptable.

### G19 — not exercised on Pricing

- No pull-quote treatment on the Pricing page. Status: open from 287, no new manifestation this session.

### G20 — not exercised on Pricing (avoided via 4-slot/2-column-CSS layout)

- Band 3 (À la carte) renders four service descriptions as a 2×2 visual grid. The stacked-siblings-in-one-cell trap (the surface G20 describes) was avoided by structuring the inner layout as `columns: 4, grid_template_columns: "1fr 1fr"` — four `.layout-column` slots, one Text widget per slot, CSS auto-wraps into two visual rows of two. Each `.layout-column` has exactly one Text widget, so the global `.widget--text_block { height: 100% }` rule resolves to that single widget's natural content height. The session prompt's "Default: standard `columns: 2, grid_template_columns: '1fr 1fr'` layout container with four Text widgets, one per cell" reads as ambiguous between this shape and the 2-slots-with-2-stacked-widgets shape (the latter is the G20-triggering case); chose the former on G20-avoidance grounds. Status: open from 287, no new manifestation this session.

### G-pricing-1 — continued; v1 emphasis surfaced as visibly subtle

- **What was attempted:** Band 2 (comparison chart) shipped via the new `pricing_chart` widget with v1 background-tint emphasis on the Monthly column (`#f8f9fa` per the spec's accepted path 2 / 288's resolution). The widget itself owns the band — heading, subheading, three columns, and footnote all live in the widget's config; no surrounding composite. The "Recommended" eyebrow appears in caps above the Monthly column title.
- **What blocked it (unchanged):** `appearance_config` still has no border knob; the widget's built-but-unfilled border slot remains transparent / 0.
- **Workaround used:** as shipped — emphasis = background tint only.
- **Observation from the live page:** the `#f8f9fa` tint is **visibly subtle** against the white card backgrounds; the differentiation reads primarily from the "RECOMMENDED" eyebrow and the longer attribute list rather than the background. Whether this is "subtle enough" or "too subtle" is the user-judgment question the spec anticipated. Surfaced for review at session end.
- **Recommended action:** unchanged from 288 — G19's appearance-config border-group extension would light up the widget's v1.1 border slot; lift to a separate session if the user signals.

### Footnote rendering — continued italic + lighter-weight workaround

- **What was attempted:** Band 2 footnote rendered in smaller body text per the spec ("Smaller body size if available... if the typography system does not have a smaller body-text size, log it as a gap and use italic + lighter weight as the workaround").
- **What blocked it:** typography system has no caption / small-body element row (the nine rows are h1–h6, p, ul_li, ol_li — confirmed in audit-summary).
- **Workaround used:** the widget renders the footnote rich text as-is; the footnote content is wrapped in `<em>` inside the Quill payload, matching the `demoConfig()` pattern. Renders as italic body text with the asterisk-joke intact.
- **Observation from the live page:** italic-body reads as smaller-feeling against the upright body in the cards above, but it is not actually smaller — the joke's "fine print at the bottom of a pricing sheet" visual setup is partially preserved by the italic shift in voice. May read as too subtle.
- **Recommended action:** typography small-body / caption row addition — same forecast the spec called out as a likely surfaced gap. Lift independently if the user signals; not a blocker for the page.

### Pricing Band 5 secondary text-link rendering — minor compromise vs spec

- **What was attempted:** Band 5 final-CTA per the spec — "Primary CTA `[Try the demo]` (primary, → `/demo`); Secondary line below the CTA, smaller text: 'Want a personal instance loaded with your data?' `[Request a 7-day trial →]` (`secondary-dark` variant ... → `/contact`)."
- **What blocked it:** the spec's anatomy is *primary CTA → small question text → secondary text-link as a separate visual block*, but Text widget renders its `ctas` array as a single CTA row below the content block. Two CTAs in one widget = two buttons side by side, with the question text above both rather than between them.
- **Workaround used:** single Text widget with H2 + body + smaller-italic question paragraph in the content, plus two CTAs (`Try the demo` primary, `Request a 7-day trial →` secondary-dark) in `ctas` with `cta_alignment: center`. Reads as "headline + body + question + two CTA row." Different from the spec's stacked anatomy; preserves both routes and the secondary-dark variant for gradient readability.
- **Recommended action:** **low priority.** Either (a) a Text widget mode that splits CTAs into per-line groupings (rare pattern, probably not worth a system change), or (b) accept the row-layout compromise as the convention for paired primary + secondary CTAs on a band. (b) is fine if the question-as-intro reads as clearly tied to the secondary CTA. Surface for user judgment at review.

---

## Session 287 — Demo-LP slug observation (not a new gap)

The base prompt inherited from the brief noted that About should link to `/my-nonprofit` and `/my-nonprofit-workshop`. Confirmed via DB query that neither slug resolves in the local install. The about-layout-spec — canonical for layout interpretation per its authority section — explicitly cuts the link-row from the current About page and does not call for those links in any of the three new bands. No band-side gap surfaced; the brief-vs-spec divergence is intentional and documented inside the spec. The slugs may still be needed for future sessions (Pricing / Contact / Demo) if those specs call for them.

---

## Session 286 — visible-change validation note

The 286 rebuild **validates the layout-spec approach** the spec was authored to test. The eight bands appeared in spec order on first import (zero importer warnings). The cross-section rhythm (column shape × background tone × visual weight) reads as the spec's table predicts. The change from PMW1's cleaned-but-conservative home is clearly visible — added image placements across four cells, dark/gradient bookend, and the explicit eight-band structure replace the prior "preserve existing structure" outcome. The layout spec proved more directive than the brief-only approach. Equivalent specs for About / Pricing / Contact / Demo should follow the same pattern.
