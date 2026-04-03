---
title: Products
description: How to create and manage products, price tiers, and view purchases and waitlist entries.
version: "0.74"
updated: 2026-03-25
tags: [cms, products, checkout, stripe]
routes:
  - filament.admin.resources.products.index
  - filament.admin.resources.products.create
  - filament.admin.resources.products.edit
category: cms
---

# Products

Products are named finite-inventory entitlements your organisation offers — garden plots, parking spaces, CSA shares, naming rights packages, and similar items. Each product has a fixed capacity. When capacity is reached, visitors see a waitlist form instead of the buy buttons.

## Creating a Product

- **Name** (required) — the public-facing name of the product.
- **Slug** — URL-safe handle. Auto-generated from the name on create; editable afterward.
- **Description** — optional description displayed in the product widget.
- **Capacity** (required) — total number of units available.
- **Status** — Draft (not publicly visible) or Published.
- **Sort Order** — controls display order when products are listed.

## Price Tiers

Each product can have one or more price tiers. All tiers draw from the same inventory pool.

- **Label** — the name shown on the checkout button (e.g. "Standard", "Supporter").
- **Amount** — set to 0.00 for a free tier. Paid tiers are automatically synced to Stripe.
- **Stripe Price ID** — populated automatically. Do not edit manually.

## Purchases

The Purchases tab shows all completed transactions for this product, including contact, price tier, amount paid, and status. Purchases are created automatically when Stripe confirms payment — they cannot be created manually from this view.

## Waitlist

The Waitlist tab shows contacts who joined the waitlist when the product was at capacity. Waitlist processing is manual — use this list to notify and convert entries as capacity opens up.

## Adding a Product to a Page

Products are displayed using the **Product** widget. Add the widget to any page, then select the product by slug in the widget configuration. The widget renders price tiers and checkout buttons when capacity is available, or a waitlist form when the product is full.
