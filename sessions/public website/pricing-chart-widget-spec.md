# Pricing Chart Widget — Specification

A new widget for the system. Renders a comparison-style pricing table with N columns side by side. Used immediately on the Pricing page for this site's marketing build; built to also be available to customers building their own pricing pages in the CMS.

This document specifies the data shape and the visual intent. Implementation details (Blade structure, SCSS approach, repeater conventions, how horizontal row alignment is achieved) are left to the coding agent, which has the widget-development context this document does not.

## Reference

`references/reference-pricing-table.png` (Salesforce Small Business pricing). Look at this image for the visual rhythm — three cards side by side, equal heights, headlines/prices/attribute lists/CTAs in consistent vertical positions across columns. Surface decoration (Salesforce's specific blue, cloud illustrations, chatbot widget) is not borrowed.

## Data shape

### Storage shape

Columns are an **in-widget config repeater** — they live attached to the widget instance, edited in the inspector. Not a content collection. Rationale: a customer's pricing tiers are widget-specific and live where the widget lives; collections are for indefinite-N customer-managed lists, which doesn't fit pricing tiers.

### Widget-level config

- **Eyebrow_label** *(text, optional)* — for a small "Pricing" or "Plans" label above the widget if desired. Most uses will leave this blank.
- **Heading** *(text, optional)* — a section heading rendered above the columns, e.g. "Three ways to try this."
- **Subheading** *(rich text, optional)* — a short intro paragraph below the heading, e.g. "Pick the one that fits where you are."
- **Footnote** *(rich text, optional)* — fine-print or asterisk-anchored content rendered below the columns in smaller type.

### Columns (in-widget repeater)

Each column has:

- **Emphasize** *(toggle, default false)* — when true, this column gets the visual emphasis treatment. Per-column rather than widget-level, so multiple columns can be emphasized if the customer wants (rare, but supported). At v1 with the border-styling gap unresolved, emphasis = subtle background tint plus any border the appearance system can express; at v1.1 (when borders land) emphasis = thicker / differently-colored border.
- **Eyebrow** *(text, optional, short)* — a small label above the column title. Used for "Recommended" / "Most popular" / "Best for teams" / etc. Rendered consistently across columns by the widget so customers don't have to style it.
- **Title** *(text)* — the column's name. "Instant Demo," "Pro," "Enterprise."
- **Price** *(rich text)* — the price line. Usually one short line with possible smaller sub-line for billing period or unit (e.g. "$25 / user / month"). Rich text because price formatting varies.
- **Lead_content** *(rich text, optional)* — a short content block at the top of the column body, above the attribute rows. This is the slot for "Everything in [previous column] plus:" type framing, or any per-column intro that doesn't fit the attribute-row structure. Most columns will leave this blank; it's there when needed.
- **Attribute_rows** *(repeater)* — the structured comparison rows. Each row has:
  - **Label** *(text)* — a short attribute name, rendered bold. "Setup," "Data," "Support," etc.
  - **Value** *(rich text)* — the per-column variation. Usually short (a phrase, a number, a checkmark equivalent), but rich text allows occasional longer values without breaking the model.
- **CTAs** *(repeater of button objects)* — usually one button per column, occasionally two. Each button has the standard button object shape (label, href, variant per the system's existing button styles).

## Design notes

These are visual intents, not implementation directives.

**Row alignment via CSS subgrid.** The cards render as a parent CSS Grid (one column per pricing tier) and each card is itself a subgrid that inherits the parent's row tracks. This makes the Nth row of every card line up with the Nth row of the others automatically — Card A's row 1 sits at the same vertical position as Card B's row 1, etc. Cards with extra rows (e.g. the Monthly tier with 5 attribute rows vs the Free tier with 3) extend below the others, with their CTA naturally landing lower. Customer convention: type attribute rows in the same order across cards so matching labels land at matching positions. Subgrid is a soft alignment mechanism — it does not require row counts to match, and getting the order wrong does not break the widget, only the visual comparison. Browser support is fine for the 2026 baseline (Chrome 117+, Firefox 71+, Safari 16+ all shipped).

**Equal-height cards / CTA pinned at card-bottom.** With each card's CTA being the last grid item in the subgrid and the parent grid's row track for the CTA row sized consistently, the CTAs line up at the same vertical position when card content lengths match. When content lengths differ (the explicit-extra-rows case), longer cards push their CTA lower; subgrid handles this naturally without flex-based `margin-top: auto` workarounds.

**Mobile responsive behavior.** At narrow viewports the parent grid switches to a single column. Cards stack vertically as block elements and lose subgrid context — each card renders self-contained. Use a media query at the relevant breakpoint to flip `grid-template-columns` from the multi-column desktop value to `1fr` for mobile. Emphasized columns retain their tint / border treatment when stacked.

**Emphasized column treatment.** When a column's `emphasize` toggle is set to true, that column gets visual weight that differentiates it from the others. The exact treatment depends on what `appearance_config` and the design system can express — currently the border-styling gap (G-pricing-1) limits options, so v1 emphasis is background tint (very light gray, ~`#f8f9fa`) plus any border the appearance system can express. When border support lands in `appearance_config`, the emphasized column gets a thicker or differently-colored border. Either way, the emphasis is subtle, not aggressive — it nudges the eye toward the conversion target without screaming. Multiple columns may be emphasized simultaneously (the toggle is per-column, not widget-level mutual-exclusion); this is rare but supported.

**Card visual at v1.** With border styling unresolved, v1 cards are differentiated from the page background via background-color (probably pure white on light pages, very light tint on white backgrounds) plus padding plus subtle shadow if the system supports it. Borders come in v1.1 once `appearance_config` is extended. Acceptable v1 trade.

**Footnote rendering.** The footnote slot is rendered in smaller body text (caption / small-body size in the typography system). The classic use case is asterisk-anchored fine print — the value field in an attribute row contains an asterisk, and the footnote slot contains the matching note. The widget does not auto-link these; the customer writes the asterisk in both places manually. Standard web pattern.

**Responsive behavior.** On narrow viewports the columns stack vertically. The emphasized column stays emphasized when stacked (still gets its visual weight) but loses its horizontal prominence (it's just one of N stacked cards). Acceptable degradation.

## Scope boundaries for v1

Explicitly **not** in v1:

- **Monthly/annual toggle.** The interaction layer needed for this is more than the appearance system handles cleanly right now. v2.
- **Checkmark/dash mode.** Text values are more flexible and arguably more honest. If a customer wants checkmarks, they put "Yes" or "✓" in the value field. The widget does not provide a special mode for this.
- **Per-row icons.** Same as above — if a customer wants an icon, they can include one in the value's rich text. The widget doesn't structure for it.
- **Section headers within attribute rows** (e.g. "Top Features" → list, "Sales Features" → list). For small-shop pricing tables this is overkill. If a real customer asks for it later, revisit.
- **Currency conversion / locale switching.** Single-currency display only.

These are deferrable. The v1 scope above covers the immediate Pricing page need and the great majority of pricing tables a nonprofit would build.

## v1 Preset

Ship one preset with v1: **"Marketing site tiers"** — three columns matching the Pricing page's Band 2 configuration (Instant Demo / 7-Day Trial / Monthly, with Monthly emphasized). The preset doubles as (a) a one-click setup for session 289's Pricing page rebuild, and (b) the agent's own correctness check that the subgrid row-alignment, the emphasis treatment, and the columns-repeater shape all work end-to-end against a realistic configuration. The preset's content matches the pricing-page spec's Band 2 verbatim — column titles, prices, attribute rows, CTAs, eyebrow on the emphasized column.

## Implementation note for the agent

The agent owns the implementation. Two things worth the agent's attention:

1. **Row alignment via subgrid is the recommended approach** (see Design notes above for the full description). The widget renders each card with its own attribute rows in author-supplied order; CSS subgrid does the cross-column positional alignment automatically. No widget-side label-union computation, no blank-cell logic. If the agent discovers a problem with subgrid that the design notes don't anticipate, surface it before falling back to a different mechanism.

2. **Border styling (G-pricing-1) is open.** The widget should be built to render correctly without borders in v1, and to take advantage of borders cleanly once `appearance_config` supports them. Don't hardcode a no-border assumption into the widget's template — build the border slot, leave it empty until appearance config can fill it. Make the v1.1 upgrade a config change, not a template change.

## Authority

This spec describes the widget's data shape and design intent. The coding agent owns implementation. If the spec asks for something that conflicts with the widget-development conventions in `resources/docs/widget-development.md`, the conventions win; surface the conflict in the gap report.