# Pricing Page — Layout Specification

This document specifies the structural shape of the Pricing page. Companion to `homepage-layout-spec.md` and `about-layout-spec.md`. Same conventions, same authority hierarchy, same gap-surfacing protocol.

The Pricing page answers one question: what does this cost. Everything on the page either answers that question directly or qualifies the answer. The page does not introduce the product (the home page does that), explain the founder (the About page does that), or argue against SaaS (both other pages do that). It's the shortest of the three so far — five bands.

## Vocabulary

Same as `homepage-layout-spec.md` and `about-layout-spec.md` — band, cell, heading sizes, image audit, etc.

**Link convention.** Same as About spec — internal links to routes that may not yet exist use `#`. The user is handling outbound link verification before the site goes live; the agent does not log these as gaps.

**New widget pattern referenced in this spec: comparison table.** The Pricing page's central content is a three-column comparison chart styled after the Salesforce pricing reference (saved as `reference-pricing-table.png`). This is a new pattern in the system — there is no built-in "pricing comparison" widget. The spec describes the structure in terms of layout containers + text widgets. See Band 2 for the full breakdown and the border-styling gap note.

## Page-level structural plan

Five bands:

1. **Hero** — page title, $150/month flat, $50 first-month offer.
2. **The comparison chart** — three-column comparison (Instant Demo / 7-Day Trial / Monthly), with an Annual option as one row inside the Monthly column. Includes the fine-print joke.
3. **À la carte** — data import, design, development, custom features.
4. **What I won't build** — PII screening framed as a feature. Dark band.
5. **Final CTA** — try the demo (primary) plus get-a-personal-demo (secondary text link).

---

## Band 1 — Hero

**Layout:** 2-column grid, `grid_template_columns: "3fr 2fr"` (60/40 split, text left, image right). Same proportions as the Home and About heroes — structural rhyme across all three pages.

**Background:** the existing gradient — `linear-gradient(135deg, #0a2540, #60a5fa)`.

**Padding:** 100px top, 200px bottom. Matches what shipped on Home and About.

**Fullscreen behavior:** none. Same as About — `fullscreen` attribute is not used; band height is controlled by padding alone. Do not enable `overlap_nav` (G12 still applies).

**Left cell:**
- Text widget. White text via `appearance_config.text.color`.
- H1: *Pricing.*
- Body paragraph: *$150 a month, flat. Everything is included.*
- Body paragraph: *The first month is $50 until I have steady customers. That's not a trial — it's a discount on month one, because the product is new and you'd be doing me a favor by trying it.*

The hero opens the conversation by stating the answer (price) and the offer (founder discount) in the first two sentences. No buildup, no pitch — the page is honest about what it's for.

**Right cell:**
- Image widget. Aspect ratio 4:3. Label: `pricing-hero-portrait`. Placeholder from the sample image library; eventual swap is a third distinct founder portrait that pairs with but does not duplicate the Home or About hero portraits.

---

## Band 2 — The comparison chart

This is the band with the most structural novelty. The reference is `reference-pricing-table.png` (Salesforce Small Business pricing). The agent should look at the reference to understand the *visual rhythm* the chart should achieve — three cards side by side, each with a heading, a price, a list of attributes, and a CTA — and use the system's existing column-layout primitive to build that rhythm.

**Layout:** 1-column outer layout containing a heading text block, then a 3-column inner layout containing the three pricing cards, then a 1-column footer for the fine-print joke. The agent should structure these as sibling layout blocks in the page's widgets array, all sharing the same background, so the band reads as one visual chapter.

**Background:** white (`#ffffff`) on all three sibling layouts.

**Padding:** 150px top on the heading layout, no internal padding gaps between the three sibling layouts, 150px bottom on the fine-print layout. (Standard band rhythm — full band reads as 150/150 with the chart sitting between.)

### Band 2 substructure

**Sub-layout 2A — Heading.** 1-column, full-width content.
- Text widget. Black text. Centered alignment.
- H2: *Three ways to try this.*
- Body paragraph below H2: *Pick the one that fits where you are.*

**Sub-layout 2B — The three cards.** 3-column grid, `grid_template_columns: "1fr 1fr 1fr"`, `align_items: "stretch"` so all three cards reach the same height regardless of content length, gap `1.5rem`.

Each card is a single text_block widget — but each widget needs to render as a visually bordered card. This is where the border-styling gap surfaces (see "Gap callout" below). The agent should attempt to render each card as a single text widget with `appearance_config` doing the card visual (border, padding, optional background tint). If `appearance_config` does not support the border specification cleanly, see the fallback in the gap callout.

**Card 1 — Instant Demo.** Left column.
- H3: *Instant Demo*
- Body line 1, large/emphasized: *Free*
- Body line 2: *24 hours*
- Plain-text list (formatted to read as a small attribute table):
  - **Setup:** Self-serve, no email required
  - **Data:** Shared sandbox, resets every 24 hours
  - **At the end:** Comes back fresh tomorrow with new data
- CTA at the bottom of the card: `[Try the demo]` (primary, → `/demo`)

**Card 2 — 7-Day Trial.** Middle column.
- H3: *7-Day Trial*
- Body line 1, large/emphasized: *Free*
- Body line 2: *7 days*
- Plain-text list:
  - **Setup:** Email me; I set it up for you
  - **Data:** Your data, your isolated instance
  - **At the end:** We talk about next steps
- CTA at the bottom of the card: `[Request a trial]` (secondary, → `/contact`)

**Card 3 — Monthly.** Right column. **Visually emphasized** — this is the conversion target. The agent should give this card a slightly different treatment than the other two: subtly darker border, a small "recommended" or "most flexible" eyebrow label above the H3, slightly heavier visual weight via background tint or thicker border. Reference the Salesforce middle column's emphasis treatment in `reference-pricing-table.png` (but applied to the right column here).
- Eyebrow label above the H3: *Recommended*
- H3: *Monthly*
- Body line 1, large/emphasized: *$150 per month*
- Body line 2: *Ongoing*
- Plain-text list:
  - **Setup:** Email me or use the contact form
  - **Data:** Your data, your instance
  - **First month:** $50 until I have steady customers*
  - **Annual:** $1,500 per year (two months free) if you want to pay up front
  - **At the end:** Cancel anytime
- CTA at the bottom of the card: `[Buy a year]` (primary, → `/contact`) — or `[Get started]` if "Buy a year" reads too transactional; agent's call between those two.

**Sub-layout 2C — Fine-print footer.** 1-column, full-width content.
- Text widget. Smaller body size if available (the standard small/caption size in the typography system), centered alignment.
- Body: *\* You ever notice how people put really small writing at the bottom of pricing sheets? What's that about? Seems sketchy.*

The joke depends on (a) the asterisk anchoring back to the Monthly card's "First month" row, and (b) the text actually being smaller than the surrounding body — without the size contrast, the joke is invisible. If the typography system does not have a smaller body-text size, log it as a gap and use italic + lighter weight as the workaround.

### Gap callout — Border styling

The card-with-border visual treatment is not well-represented in the current widget system's `appearance_config`. The agent will likely find that text widgets can specify background colors and padding cleanly, but border styling (color, width, radius) is either missing or under-developed. This is a known gap — surface it explicitly as **G-pricing-1: border styling in appearance_config**.

Two acceptable paths for this pass:
1. Best path: extend `appearance_config` to support border-color, border-width, and border-radius. This is a small system-level extension that benefits every text widget going forward, not just this page. Worth doing if the lift is genuinely small; log it as the recommended fix in the gap report and proceed only if the user signs off.
2. Workaround path: use only background-color and padding to differentiate the three cards visually. The Monthly card gets a slightly tinted background (very light gray, `#f8f9fa` or similar) while the other two stay pure white. No borders. The cards read as "panels" rather than "cards." This is a degraded version of the reference but is honest and works within current capability.

Pick path 2 unless the user signals otherwise. Path 1 is a meaningful system extension that should not be done unilaterally; flag it and wait for input.

---

## Band 3 — À la carte

**Layout:** 1-column outer layout containing a heading text block, then a 2-column inner layout (or 4-column on wider viewports, agent's call based on what reads cleanly) containing the four service descriptions.

**Background:** `#d4d4f2` (the tinted band, established by the home page). Switches the visual tone after the white chart band.

**Padding:** 150px top, 150px bottom.

### Band 3 substructure

**Sub-layout 3A — Heading.** 1-column, full-width content.
- Text widget. Black text. Centered alignment.
- H2: *À la carte.*
- Body paragraph: *Some things are not part of the monthly fee because not everyone needs them. If you do, here's what they cost.*

**Sub-layout 3B — The four service descriptions.** 2-column grid, `grid_template_columns: "1fr 1fr"`, `align_items: "start"`, gap `2rem`. (Four cells in a 2x2 grid, not a 4-up row — the descriptions need vertical room and 4-up makes them cramped.)

**Cell 1 — Data import.**
- Text widget.
- H3: *Data import*
- Body: *$250 flat. I bring your existing constituent data into the system, cleaned and matched. Includes two review sessions — one when you give me the data, one before you launch.*

**Cell 2 — Design.**
- Text widget.
- H3: *Design*
- Body: *$125 per hour. Custom theme work, branded templates, page design. I offer design services for your site if you need help in this area.*

**Cell 3 — Development.**
- Text widget.
- H3: *Development*
- Body: *$100 per hour. Customization, new widgets, integrations, automations. For projects over 10 hours we'll talk through scope before starting.*

**Cell 4 — Custom features.**
- Text widget.
- H3: *Custom features*
- Body: *Negotiated. I'll consider building features that meet the security and data-handling criteria the product is built on. If they don't meet those criteria, see the next section.*

The "see the next section" sentence is a deliberate handoff to Band 4. It tells the visitor that "what I won't build" isn't a separate complaint — it's the criteria that govern the negotiated work above it.

---

## Band 4 — What I won't build

**Layout:** 2-column grid, `grid_template_columns: "2fr 3fr"` (image left smaller, text right). Mirrors About Band 3's proportions — structural echo for "principles" content.

**Background:** `#373c44` (the dark band).

**Padding:** 150px top, 150px bottom.

**Left cell:**
- Image widget. Aspect ratio 1:1. Label: `pricing-pii-supporting-image`. Placeholder from the sample image library.

**Right cell:**
- Text widget. White text via `appearance_config.text.color`.
- H2: *What I won't build.*
- Body paragraph 1: *Some feature requests can't be built because the system itself rejects them. The product screens out a short list of patterns at the input layer — Social Security numbers, credit card numbers, bank routing numbers, and a few others. If you store one by accident, the system refuses it.*
- Body paragraph 2: *That's a security feature, not a limitation, and I won't build around it. The most common way small nonprofits get into trouble is staff entering sensitive data into fields that weren't designed to hold it. This product makes that specific mistake impossible.*
- Body paragraph 3: *If you have a feature idea that requires storing patterns like those, the answer is no, and I'd rather lose the sale than pretend otherwise.*

The closing line is doing real positioning work — it's the page's most direct "we're not like other vendors" moment, and it earns the right to be blunt because it's about protecting the customer, not the product. Do not soften it.

---

## Band 5 — Final CTA

**Layout:** 1-column layout, content centered.

**Background:** the existing gradient — `linear-gradient(135deg, #0a2540, #60a5fa)`. Bookends the page same as Home does.

**Padding:** 150px top, 150px bottom.

**Single cell:**
- Text widget. White text via `appearance_config.text.color`. Centered alignment.
- H2: *Try it before you commit.*
- Body paragraph: *The demo is a working copy of the product. Spend an hour with it.*
- Primary CTA: `[Try the demo]` (primary, → `/demo`)
- Secondary line below the CTA, smaller text: *Want a personal instance loaded with your data?* `[Request a 7-day trial →]` (`secondary-dark` variant for readability on the gradient — the `text-only` variant is blue-on-light and fails the contrast check on dark backgrounds; this matches the pattern established on the Home and About dark/gradient bands. → `/contact`)

The two-CTA structure offers both the low-friction option (instant demo) and the high-intent option (personal trial). The visitor self-selects based on where they are in their decision; the page doesn't force them up the ladder.

---

## Cross-band rhythm

| Band | Layout shape | Background | Visual weight |
|---|---|---|---|
| 1. Hero | 2-col, text-image | Gradient | Heavy |
| 2. Comparison chart | 1-col heading + 3-col cards + 1-col footnote | White | Medium |
| 3. À la carte | 1-col heading + 2-col grid | Tinted | Medium |
| 4. What I won't build | 2-col, image-text | Dark | Heavy |
| 5. Final CTA | 1-col centered | Gradient | Heavy |

Five bands, three of them heavy (hero, dark band, final CTA), two medium (chart, à la carte). The visual rhythm goes heavy → medium → medium → heavy → heavy — the page lifts off, settles into pricing information, lifts back up for the principles statement, lands on the conversion ask. The chart and à la carte bands are deliberately quieter because they're informational; the heavy bands are where the page makes arguments.

## Authority

Same hierarchy as Home and About specs. This document overrides layout interpretations from `brief.md` and `copy.md` for the Pricing page only. The Salesforce reference image is structural-spec material only — for the chart's three-card rhythm and the visual emphasis on the conversion-target column. Surface decoration (Salesforce's specific blue, the cloud illustration backgrounds, the chatbot widget, the trailblazer mascots) is not borrowed.

## New gaps this spec is likely to surface

For pre-flight planning:

- **G-pricing-1: border styling in `appearance_config`.** Card visual treatment in Band 2. Workaround specified; user input requested if path 1 (system extension) is on the table.
- **Smaller body-text size** for the fine-print joke. If the typography system doesn't have a caption or small-body size, the joke loses its visual setup. Workaround: italic + lighter weight.
- **Eyebrow label styling** for the "Recommended" tag above the Monthly card's H3. If there's no existing small-caps or label style, the agent will improvise with bold uppercase text in a smaller size. Probably fine; log it for awareness.
- **Visual emphasis treatment** on the Monthly card. With border styling unresolved, the emphasis is doing all its work via background tint. May read as too subtle. If it does, surface as a follow-up gap.