# Home Page — Layout Specification

This document specifies the structural shape of every section on the home page. It is the missing piece between `copy.md` (the words) and the JSON format (the serialization). The agent should treat each section block below as the exact spec for a section in the page export — column counts, image placements, background colors, padding values, and heading sizes are all specified, not inferred.

If a section's spec calls for a config option or widget capability that doesn't exist in the system, log the gap and use the closest available substitute. Do not improvise structure to "look better" — improvisation is what produced the first pass. Predictability is what we want.

## Vocabulary used below

**Band** = a major full-bleed section. Each band is one `type: "layout"` block with a background color set on the layout container. Padding is 150px top and 150px bottom on the layout, unless otherwise specified.

**Cell** = a slot inside a column layout. Widgets inside cells lock `full_width: false` and use the inner padding scale (25/50 top/bottom).

**Heading sizes:**
- **H1** = used only for the hero headline (Band 1). The existing system H1 is 2.5rem / 40px; reference hero headlines run 50–80px. The hero headline should be overridden upward to approximately 3.5rem / 56px using whatever override mechanism the system allows — `appearance_config` text-size override, a custom CSS class assignable via appearance config, or inline style inside the Quill content. If no override mechanism exists, log gap G13 with the recommendation that the global typography scale needs a larger H1, and use the largest existing size for this pass while noting in the gap that the hero will read smaller than reference until G13 is resolved.
- **H2** = the existing system H2. Use for major band headings ("Small Data," "What it Does," "This is not a SaaS company," "Pricing," "What it doesn't do," "Try it.").
- **H3** = the existing system H3. Use for sub-section labels and column headings inside grids ("Content & Web," "Constituents," etc.).
- **Body emphasis** = bold via `<strong>` inside body text. `<h4>` is not used in this spec — when the copy calls for emphasized in-line text, bold it; don't drop a heading level.

If the existing typography scale doesn't have three distinct sizes that map cleanly to H1/H2/H3, log a gap. Do not skip the size differentiation — the visual hierarchy is what makes the page not read as a wall of text.

**Image placeholders.** Every image slot below specifies a label and an aspect ratio. The agent uses the sample image library to fill these and tags each placeholder with a label in the widget label or a custom field so the eventual photo swap is mechanical.

**Image widget audit (run before any band is built).** The spec calls for three aspect ratios across the page: 4:3, 1:1, and 4:5. Before serializing any band, the agent audits the Image widget's capability — does it accept an explicit aspect-ratio config, does it crop or fit at arbitrary ratios, what shapes are actually achievable. If all three ratios are supported, use them as specified. If they are not, fall back consistently to the closest available ratio per slot and log a gap noting which ratios couldn't be enforced and what was substituted. Do not silently pick different ratios per band — consistent image proportions across the page is part of what makes it look composed.

---

## Band 1 — Hero

**Layout:** 2-column grid, `grid_template_columns: "3fr 2fr"` (60/40 split, text left, image right).

**Background:** the existing gradient on the layout container — `linear-gradient(135deg, #0a2540, #60a5fa)`. Currently set on the hero widget itself in the existing export; move it to the layout container.

**Padding:** 200px top, 200px bottom on the layout (hero gets more breathing room than other bands).

**Fullscreen behavior:** the layout container should preserve the existing `fullscreen: true` behavior currently set on the hero widget. If that's not a layout-container option, leave it on the hero widget inside the left column and log a gap noting that fullscreen lives on the widget rather than the layout.

**Do not enable `overlap_nav`.** Gap G12 (logo has no light-variant path for dark backgrounds) is open and unresolved. The current home page has `overlap_nav` toggled off so the logo remains readable. The spec preserves that — do not re-enable `overlap_nav` on the hero or on its containing layout, regardless of what the existing export carries (the export I have as a format reference predates the G12 toggle-off and still shows `overlap_nav: true`; ignore that value). If G12 is resolved before this spec runs, this directive can be relaxed in a subsequent spec revision.

**Left cell:**
- Text widget. White text color via `appearance_config.text.color`.
- H1 (with size override to ~3.5rem / 56px per the typography section above; if no override mechanism exists, log gap G13 and use the largest existing H1 size): *A combination CMS, CRM, and a few other tools to help nonprofits manage their business.*
- Body: *Built and supported by one developer. Open source. Flat pricing.*
- CTA pair below body: `[Try the Demo]` (primary, → `/demo`) and `[See the Code]` (secondary, → `https://github.com/abeuscher/npc-beta/`).

**Right cell:**
- Image widget. Aspect ratio 4:3. Label: `hero-founder-portrait`. Placeholder from the sample image library; the eventual swap is the founder portrait.

If a single Hero widget instance must be used (rather than splitting headline/body/CTAs into a Text widget plus a separate Image widget), put the Hero in the left cell and the Image widget in the right cell. The Hero widget's existing background gradient should still move to the layout container — the Hero inside the cell renders on the gradient inherited from above.

---

## Band 2 — Small Data

**Layout:** 2-column grid, `grid_template_columns: "2fr 3fr"` (image left smaller, text right). Reversed proportions from the hero — keeps the page from feeling like every band is the same shape.

**Background:** white (`#ffffff`) on the layout.

**Padding:** 150/150 on the layout.

**Left cell:**
- Image widget. Aspect ratio 1:1. Label: `small-data-supporting-image`. Placeholder from the sample image library.

**Right cell:**
- Text widget. Black text.
- H2: *Small Data*
- Body: *Three principles:*
- Ordered list: *Privacy is important. / People are important. / People and privacy are both more important than money.*
- Closing paragraph: *This product reflects those values. So does the business behind it.*

---

## Band 3 — What it Does (heading band)

**Layout:** 1-column layout (single cell, full-width content). This band is just the section title.

**Background:** `#d4d4f2` (the tinted band, established by the existing home).

**Padding:** 120px top, 60px bottom — shorter than a regular band because the content grid below is logically part of the same section. The two bands together (heading + grid) form the "What it Does" composite.

**Single cell:**
- Text widget. Black text.
- H2: *What it Does* (centered).

---

## Band 4 — What it Does (4-cell grid)

**Layout:** 4-column grid, `grid_template_columns: "1fr 1fr 1fr 1fr"`, `align_items: "start"`, `grid_auto_rows: "1fr"`, gap `2rem`.

**Background:** white (`#ffffff`) on the layout. The tinted band above plus the white band here are the visual split.

**Padding:** 60px top (paired with the 60px bottom of the heading band above), 60px bottom (the CTA-footer layout below provides the band's closing padding — see "Below the grid" at the end of this section).

**Cell 1 — Content & Web:**
- Text widget.
- H3: *Content & Web*
- Bulleted list: *CMS with structured content and a page builder / Custom widgets, headers, and footers / Member portal / Daily backups*

**Cell 2 — Constituents:**
- Text widget.
- H3: *Constituents*
- Bulleted list: *CRM / User groups / Mailing list manager (integrates with Mailchimp)*

**Cell 3 — Commerce:**
- Text widget.
- H3: *Commerce*
- Bulleted list: *Online store: products, ticket sales, donations / Stripe integration for payments / QuickBooks integration for bookkeeping / Event handling / Donation tracking*

**Cell 4 — For developers:**
- Text widget.
- H3: *For developers*
- Bulleted list: *Open source. Public repo. Full docs. / Tailwind native. Blade, JS, and SCSS widget system. / Data sovereignty.*

If the 4-column layout breaks unreadably on a target viewport, fall back to a 2x2 grid (two rows of 2 columns each) — but log a gap noting the column-layout responsive behavior is the reason. Do not silently substitute.

**Below the grid — CTA footer.** Band 4's CTA is rendered as a separate 1-column layout block, a sibling of the 4-column grid layout in the page's widgets array. This second layout has:
- Background: white (`#ffffff`) on the layout container, matching the grid above so the two read as one visual band.
- Padding: 30px top, 120px bottom.
- Single cell containing a Text widget with the centered CTA: `[Try the demo →]` (primary, → `/demo`).

So Band 4 is structurally **two sibling layout blocks** in the widgets array — the 4-column grid layout immediately followed by the 1-column CTA-footer layout, both white-on-layout, with the CTA-footer's small top padding (30px) keeping the visual gap between grid and CTA tight enough that they read as one band rather than two. Combined with Band 3 (the heading) above, three sequential layouts compose the "What it Does" composite.

Background-color discipline note: with G1 resolved (layout-level `appearance_config` round-tripping correctly), backgrounds live on the layout containers, not on the inner widgets. The CTA Text widget inside the footer layout leaves its own background unset and inherits white from the parent layout. Same rule for the four grid cells. If the agent's audit at Phase 1 reveals G1 is still open at run time, fall back to setting white backgrounds on each inner widget instead, and log the regression.

If a CTA cannot be rendered cleanly in a Text widget with the existing button styles (primary/secondary/text-only), log a gap and use the closest workaround.

---

## Band 5 — This is not a SaaS company (dark)

**Layout:** 2-column grid, `grid_template_columns: "3fr 2fr"` (text left, image right — mirrors hero proportions).

**Background:** `#373c44` (the dark band, established by the existing home).

**Padding:** 150/150.

**Left cell:**
- Text widget. White text color via `appearance_config.text.color`.
- H2: *This is not a SaaS company.*
- Body: *My name is Al. I am a developer and I built a piece of software. I have been using CMS's and CRM's for 30 years, and building websites and web applications that whole time.*
- Body: *I tried to do it right. And I tried to do it with every client in mind, not just one.*
- Body: *What I didn't do:*
- Bulleted list: *I don't super-serve a single customer. / I don't take feature requests from sales and marketing. / I don't report to a C Suite.*
- CTA below list: `[More about how this works →]` (text-only or secondary, → `/about`).

**Right cell:**
- Image widget. Aspect ratio 4:5 (taller than wide). Label: `about-founder-portrait-dark`. Placeholder from the sample image library. Eventually a different founder photo than the hero.

If the existing button styles don't include a variant that reads well on the dark background (primary blue on dark gray may be acceptable; secondary outline may not be), log it as a gap suggesting a ghost-on-dark variant. For this pass, use whatever exists.

---

## Band 6 — Pricing

**Layout:** 1-column layout, content centered.

**Background:** white (`#ffffff`).

**Padding:** 150/150.

**Single cell:**
- Text widget. Black text. Centered alignment.
- H2: *Pricing*
- Large body emphasis (H3 or bold): *$150/month, flat.*
- Body: *Hosting, daily backups, in-system help, ongoing updates. Data import, design, and custom development available à la carte.*
- CTA below body: `[Full pricing →]` (secondary, → `/pricing`).

Pricing is a deliberately quiet band — restrained centered text on white. The hero, the dark "not a SaaS company" band, and the final CTA are the visual peaks; pricing is a flat moment between them. Resist the urge to give it an image or a multi-column layout.

---

## Band 7 — What it doesn't do

**Layout:** 2-column grid, `grid_template_columns: "2fr 3fr"` (image left small, text right). Mirrors Band 2's proportions to create a structural rhyme — the two "principles" sections of the page (Small Data, What it doesn't do) share a shape.

**Background:** white (`#ffffff`).

**Padding:** 150/150.

**Left cell:**
- Image widget. Aspect ratio 1:1. Label: `what-it-doesnt-do-supporting-image`. Placeholder from the sample image library.

**Right cell:**
- Text widget. Black text.
- H2: *What it doesn't do*
- Body: *In the interest of saving you time:*
- Bulleted list: *The CMS is field-based, not WYSIWYG. It's structured content, not a drag-and-drop editor. / No built-in analytics of any kind. You can install any package you like on your site — analytics just isn't what we do here. / No tracking cookies. This product doesn't use cookies for any non-functional purpose, and neither does this website. / No in-app refunds. Refunds are recorded but issued through Stripe.*

---

## Band 8 — Try it (final CTA)

**Layout:** 1-column layout, content centered.

**Background:** the existing gradient again — `linear-gradient(135deg, #0a2540, #60a5fa)`. Same gradient as the hero; bookends the page.

**Padding:** 150/150.

**Single cell:**
- Text widget. White text color via `appearance_config.text.color`. Centered alignment.
- H2: *Try it.*
- Body: *First month is $50 until I get some customers.*
- CTA pair below body: `[Start the demo]` (primary, → `/demo`) and `[Email me]` (secondary, → `mailto:al@example.com`, placeholder).

The bookending gradient is intentional — the page opens and closes on the same visual note. If the existing widget system has any issue with the same gradient being used on two separate layout containers, log it but do not substitute a different gradient.

---

## Cross-section rhythm summary

If the agent reads this doc and produces JSON that matches it, the resulting page has this rhythm:

| Band | Layout shape | Background | Visual weight |
|---|---|---|---|
| 1. Hero | 2-col, text-image | Gradient | Heavy |
| 2. Small Data | 2-col, image-text | White | Medium |
| 3. What it Does (heading) | 1-col centered | Tinted | Medium |
| 4. What it Does (grid) | 4-col | White | Heavy |
| 5. Not a SaaS company | 2-col, text-image | Dark | Heavy |
| 6. Pricing | 1-col centered | White | Light |
| 7. What it doesn't do | 2-col, image-text | White | Medium |
| 8. Try it | 1-col centered | Gradient | Heavy |

That rhythm — alternating column shapes, alternating background tones, alternating visual weights — is what makes the page not read as a stack of text blocks. The instruction that produced the first pass was effectively "preserve the existing structure"; this instruction is "produce these eight specific structures."

## Authority

This document overrides the layout interpretations in `brief.md` and `copy.md` for the home page only. The brief's system conventions (background-on-layout, padding scale, no animations, no invented widgets, gap-surfacing protocol) still apply. The copy in `copy.md` is still the source of truth for words. This doc adds: where things go on the page, in what column, with what image alongside, at what heading size.

Once this page is built and reviewed, an equivalent layout spec will be written for About, Pricing, Contact, and Demo. The home page is the pattern test for the spec approach.