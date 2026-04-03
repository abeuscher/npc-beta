---
title: Donations
description: How to view and audit Stripe-backed donation records — one-off and recurring.
version: "0.25"
updated: 2026-03-25
tags: [finance, donations, stripe, contacts]
routes:
  - filament.admin.resources.donations.index
  - filament.admin.resources.donations.view
category: finance
---

# Donations

Donation records are created automatically when a supporter completes a checkout session via the Donation Form widget. The admin resource is view and audit only — donations cannot be created or edited manually.

## Donations List

The Donations index shows all donation records. Each row shows the donor contact, donation type (one-off or recurring), amount, frequency, current status, and the date the donation became active.

Recurring donations with a linked Stripe subscription include a **View in Stripe** action that opens the subscription record in the Stripe dashboard.

## Viewing a Donation

Click a donation row to open the detail view. This shows all fields including the Stripe subscription and customer IDs.

The **Transactions** tab lists every payment event associated with this donation. Each transaction links directly to its Stripe record (checkout session or invoice).

## Donation statuses

- **pending** — checkout initiated but not yet completed
- **active** — payment confirmed by Stripe
- **past_due** — a recurring billing cycle failed
- **cancelled** — subscription ended
