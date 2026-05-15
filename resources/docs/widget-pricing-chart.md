---
title: Pricing Chart Widget
description: Configuring a side-by-side pricing comparison table — columns, attribute rows, emphasis, and the recommended-tier treatment.
tags: [widget, page-builder, pricing, comparison, cms]
category: cms
standalone: true
parent: widgets
---

# Pricing Chart Widget

The Pricing Chart widget renders a comparison-style pricing table with N columns side by side. Each column is a pricing tier — typically a free / starter / paid option, or a free trial / monthly / annual option. One column can be visually emphasized as the "recommended" choice.

The widget is built around a single design idea: each column carries the same kinds of rows (a price, a list of attributes, a call-to-action button), and those rows line up horizontally across columns so the comparison reads at a glance. Type the attribute rows in matching order across columns and the cards stay aligned automatically.

## When to use this widget

Use Pricing Chart when you have two to four pricing tiers to compare side by side, each with its own price, a short list of features or terms, and a call-to-action. Typical uses: a marketing pricing page, a "membership tiers" page, a per-event ticket-tier explainer.

For a single-tier "what this costs" statement, a Text widget reads better. For more than four tiers the table gets cramped on tablet widths — break the comparison into two charts or move some content out into a separate page.

## Inspector — Content tab

### Top-level fields

- **Eyebrow label** — a small all-caps label rendered above the heading (e.g. `PRICING`, `PLANS`). Optional; most uses leave this blank.
- **Heading** — a section heading rendered above the columns (e.g. *Three ways to try this.*). Optional.
- **Subheading** — a short intro paragraph below the heading (rich text). Optional.
- **Footnote** — fine-print or asterisk-anchored content rendered below the columns in smaller, lighter type (rich text). The classic use case is asterisk-anchored fine print: an attribute row in one of the columns ends with an `*`, and the footnote slot below carries the matching note. Format the footnote in italic for the small-print look until a dedicated caption-text size lands in the typography system.

### Columns

Click **Add Column** to add a pricing tier. Each column carries:

- **Emphasize this column** — a toggle. When on, the column gets visual weight to differentiate it from the others (currently a subtle background tint). This is the "recommended choice" treatment. Multiple columns can be emphasized at once if you want, but in practice you'll usually pick one.
- **Eyebrow** — a small label above the column title (e.g. `Recommended`, `Most popular`, `Best for teams`). Optional. The widget styles it consistently across columns so you don't have to.
- **Title** — the tier's name (e.g. `Pro`, `Enterprise`, `Monthly`).
- **Price** — the price line as rich text. Usually one short line — `$25 / user / month` or `Free`. Rich text because you might want a bold price with a smaller billing-period suffix.
- **Lead content** — a short content block at the top of the column body, above the attribute rows. Use this for "Everything in the previous tier, plus:" framing, or any per-tier intro that doesn't fit the attribute-row structure. Most columns leave this blank.
- **Attribute rows** — the structured comparison rows. Each row has:
  - **Label** — a short attribute name, rendered bold (e.g. `Setup`, `Data`, `Support`).
  - **Value** — the per-column variation (rich text). Usually short — a phrase, a number, a check-equivalent like `✓` or `Yes`.
  Click **Add Row** inside a column to add an attribute row. The row order is the order the rows render in.
- **CTAs** — usually one button per column, occasionally two. Each button has the standard text / URL / style configuration (Primary / Secondary / Secondary (Dark) / Text Only).

### Heading alignment

A small dropdown under Heading: choose left / center / right alignment for the heading row above the columns. Defaults to center.

### Custom gap

A blank-by-default text field that takes a CSS gap value (e.g. `1.5rem`, `2rem`). Leave blank for the default spacing between cards. Set this if the cards look too close together (or too far apart) at the page width you've placed the widget at.

## Inspector — Appearance tab

Standard widget appearance fields apply (background, text color, padding, full-width). The widget defaults to a white background with 100px top / bottom padding — matches the standard band rhythm.

The "card" visual on each column is currently driven by background-color and padding. The emphasized column gets a slightly tinted background (`#f8f9fa`); the other columns stay pure white on a white page. A future system update will let you set border colors and widths for cards directly through the appearance config.

## Common patterns

### Marketing-site pricing page (3 tiers, one recommended)

The most common shape — three columns with the middle (or right) column emphasized as the conversion target. Each column has a title, a price, three to five attribute rows in matching order, and one primary CTA. The emphasized column carries an "Recommended" eyebrow.

### Free-vs-paid (2 tiers)

Two columns, no emphasis on either — the visitor self-selects. Each column has a title, a price (or "Free"), a short list of what's included, and a different CTA shape (the free tier might be `secondary` style, the paid tier `primary`).

### Event ticket tiers (3+ tiers)

Three or more columns for an event with general / VIP / patron tickets. Use **lead content** for "Includes everything in General, plus:" framing on the higher tiers. The emphasized column might be the middle tier (the "best value" one).

## Gotchas

- **Type attribute rows in the same order across columns.** The widget aligns rows by source order, not by label matching. If column A's first row is "Setup" and column B's first row is "Data," they line up at the same vertical position but the comparison won't make sense visually. Match the row order across columns and the alignment becomes the visual comparison.
- **Cards line up to the same height regardless of attribute count.** The widget uses CSS subgrid to size the cards as a single grid. Even if one column has three attribute rows and another has five, all cards reach the same total height — the shorter cards just have more whitespace between their last row and their CTA. This is intentional ("honest" empty space rather than visually mismatched cards).
- **The emphasis treatment is subtle in v1.** Until borders land in the appearance system, the emphasized column gets a background tint only — no thicker or differently-colored border. On a pure-white page the tint reads clearly; on a tinted-background page (a band with `#d4d4f2` or similar) the tint may not be visible. If the visual emphasis isn't reading, place the widget on a white background — `appearance.background.color: #ffffff` on the widget itself, regardless of the surrounding band's color.
- **Two emphasized columns is supported but rare.** The toggle is per-column, not widget-level mutual-exclusion. Use it sparingly — emphasizing two columns out of three weakens the "recommended" signal.
- **Mobile collapse is automatic.** At narrow viewports the columns stack vertically. Emphasized columns stay emphasized when stacked but lose their horizontal prominence (they're just one of N stacked cards).
- **The footnote does not auto-link to asterisks.** If you write `$50 first month*` in an attribute row's value, you have to write the matching `* The first month is $50 because...` in the footnote yourself. The widget doesn't draw the connection automatically.
