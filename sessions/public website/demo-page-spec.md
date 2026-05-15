# Demo Page — Layout Specification

This document specifies the Demo page. Companion to `homepage-layout-spec.md`, `about-layout-spec.md`, and `pricing-layout-spec.md`. Same authority hierarchy and gap-surfacing protocol.

The Demo page deliberately deviates from the marketing-page pattern established by Home, About, and Pricing. The other three pages are content surfaces with section-band rhythm and multiple scrolling chapters. Demo is a conversion gate: the visitor arrives with the decision already made, and the page's only job is to get them through the door without ceremony.

The references for this deviation are the SaaS sign-up pages reviewed earlier in the project — Clay, Attio, and Gong's demo-access modal (see `references/` if those are still present; otherwise the principle is described below). All three use a stripped, bounded layout with a single action and no scrolling marketing content. Demo follows that pattern.

## Vocabulary

Same as the other specs — band, cell, heading sizes — but most of the vocabulary is unused here because the page has only one band.

**Link convention.** Same as the other specs.

## Page-level structural plan

One band. The page is the band.

- **Nav** at top (preserved from the rest of the site).
- **Single full-viewport band** — 50/50 horizontal split, dark left / light right.
- **Footer** at bottom (preserved from the rest of the site).

That's the entire page. No section-band rhythm, no scrolling chapters, no closing CTA. The page does not scroll beyond the viewport (or scrolls minimally on small viewports, with the band shrinking gracefully rather than introducing new content below).

---

## Band 1 — The split

**Layout:** 2-column grid, `grid_template_columns: "1fr 1fr"` (true 50/50 split, edge to edge). The columns are full-bleed — they extend to the viewport edges, not constrained by the standard content container.

If the column-layout primitive does not support full-bleed independent of the content-fills-page-width vs background-fills-page-width controls, log a gap and use whatever combination of those toggles gets closest to the edge-to-edge result. The dev-guide screenshots showed both toggles existing; the combination should be available.

**Height:** the band fills the viewport minus the nav and footer. Target: `min-height: calc(100vh - [nav height] - [footer height])` or equivalent. If layout containers do not have a viewport-height-aware sizing option, use a fixed generous height (e.g. 720px) as a workaround and log it as a gap. The intent is that nothing else is visible on screen when the page loads — the visitor's entire field of view is the split.

**Background:** none on the layout container itself. Each cell carries its own background color (see below). This is one of the rare cases where the system convention of "backgrounds on the layout, not on widgets" doesn't apply, because the visual point of the page is the two cells being different colors.

**Padding:** none on the layout container. Each cell has its own internal padding (see below).

### Left cell — Dark side

**Background:** solid `#0a2540` (the darker end of the brand gradient, used as a flat color here rather than a gradient).

**Internal padding:** generous — target `padding: 120px 80px` or equivalent. The intent is that the heading and paragraphs sit in the cell with significant whitespace around them, not cramped against the edges.

**Vertical alignment:** content vertically centered within the cell. The heading + paragraphs as a group should sit roughly in the middle of the cell's height.

**Content:**
- Text widget. White text via `appearance_config.text.color`.
- H1: *Instant Demo.*
- Body paragraph 1 *(lorem ipsum placeholder — real copy from user pending)*: *Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.*
- Body paragraph 2 *(lorem ipsum placeholder — real copy from user pending)*: *Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.*

The two paragraphs are placeholders for tonight's build; the user will provide real copy tomorrow. The lorem ipsum is at the target length (3-4 sentences each) so the visual rendering shows what the band will actually look like with real content. The agent should leave a comment in the JSON export (in the widget's label or a custom field) flagging both paragraphs as `pending-copy` so the eventual swap is mechanical.

### Right cell — Light side

**Background:** solid `#ffffff`.

**Internal padding:** same as the left cell — `padding: 120px 80px` or equivalent.

**Vertical alignment:** content vertically centered within the cell.

**Horizontal alignment:** content horizontally centered within the cell. The button is the only thing here; centering it gives it the visual prominence it needs.

**Content:**
- A single button. The button is the entire interaction.
- Label: *Click Me*
- Variant: `primary`
- Size: **larger than the standard primary button used elsewhere on the site.** Target roughly 1.25-1.5× the standard primary's size — taller, wider, slightly larger font. Not enormous. The intent is for the button to read as the unmissable focal point of the page without becoming a parody of a "BIG CALLOUT" element.

If the button system does not have a size variant beyond the standard primary, log it as a gap (call it G-demo-1: large button size variant) and use whatever closest approximation is available — standard primary with extra padding via `appearance_config`, or a manually-larger inline style. Acceptable workaround: the standard primary at standard size, but with generous whitespace around it doing the work of making it feel like the focal point. The button doesn't *have* to be physically larger to feel important; it just has to be the only thing on its side of the page.

**Action:** the button click takes the visitor into the shared demo instance. The href and the auto-login mechanism are owned by the coding agent and the fleet manager — this spec does not specify the technical implementation, only that clicking the button enters the demo. For the JSON build, the agent uses whatever href convention the system expects for "trigger demo login" actions; if no such convention exists yet, use `#` as a placeholder and log it as the conversion-action wiring task.

---

## What's deliberately absent

For the agent's awareness, the following are intentionally not on this page:

- **No hero band** in the conventional sense. The split *is* the hero.
- **No "what happens next" band**, no "what this includes" band, no expectations-setting band. The shared-demo experience handles its own context once the visitor is inside; the page itself just gets them there.
- **No privacy/code closing band.** That principle statement lives on the home page and on the about page. Repeating it here would crowd the conversion moment.
- **No final-CTA band.** The button *is* the final CTA. The page has no "below" because there's nowhere else to go.
- **No image.** Both the founder-portrait pattern (Home/About/Pricing heroes) and the supporting-image pattern (Small Data / What I won't build / etc.) are skipped. The solid color on the left carries the visual weight that an image would normally provide. An image here would crowd the page and dilute the purposefulness.
- **No secondary action.** No "request a 7-day trial instead," no "see pricing first," no "read about us." Visitors who want those things can use the nav. The page body does not offer escape routes.

These omissions are the point. The page is short because it's done.

---

## Cross-band rhythm

| Band | Layout shape | Background | Visual weight |
|---|---|---|---|
| 1. The split | 2-col, 50/50, full-viewport-height | Solid dark / solid white | Heavy |

One band. The "rhythm" is the contrast of the two cells against each other within that band, not a sequence across bands.

---

## Gaps this spec is likely to surface

For pre-flight planning:

- **G-demo-1: larger button size variant.** If the button system only has one size for `primary`, the button on this page will be the same size as buttons elsewhere on the site. Workaround acceptable; the page still works with a standard-size button surrounded by whitespace.
- **Viewport-height-aware layout sizing.** If layout containers can only be sized by fixed dimensions or padding, the "fills the viewport minus nav and footer" goal can't be expressed natively. Workaround: fixed generous height. Log it.
- **Full-bleed cells in a 2-column layout.** The column-layout primitive has toggles for content vs background filling the page width; the combination needed here is "cells extend to viewport edges, content within cells is padded inward from those edges." If this combination isn't expressible, log the specific gap. Workaround: the closest approximation, even if it leaves visible side margins.
- **Conversion-action wiring.** The button's actual behavior (auto-login into the shared demo) is owned outside this page. The href in the JSON is a placeholder until that wiring exists.

## Authority

Same hierarchy as the other specs. This spec deliberately overrides the system conventions for marketing pages (section-band rhythm, alternating backgrounds, generous padding within a constrained container) because the Demo page is not a marketing page. The deviation is intentional and documented. If the agent encounters resistance from the brief's conventions, this spec overrides them for the Demo page only.

The two paragraphs of lorem ipsum on the dark side are placeholders. Real copy from the user is pending. The build is correct when the structure works; the copy swap is a follow-up.