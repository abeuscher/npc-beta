---
title: Donations
description: How to record, view, and manage donation records linking donors to funds and campaigns.
version: "0.24"
updated: 2026-03-16
tags: [finance, donations, donors, contacts]
routes:
  - filament.admin.resources.donations.index
  - filament.admin.resources.donations.create
  - filament.admin.resources.donations.edit
---

# Donations

Donation records represent a gift from a donor (a contact) to your organization. Each donation can be linked to a fund and a campaign to track where money comes from and how it is designated.

## Donations List

The Donations index shows all recorded donations. You can filter by date range, fund, campaign, or donor. The list includes total amounts to help with quick reporting.

## Recording a Donation

- **Contact** (required) — the donor making the gift. Must be an existing contact record.
- **Amount** (required) — the dollar amount of the gift.
- **Date** — the date the gift was received (defaults to today).
- **Fund** — the fund this gift is designated to (e.g., General Operating, Building Fund).
- **Campaign** — the campaign that motivated or should receive credit for this gift.
- **Payment Method** — cash, check, credit card, EFT, etc.
- **Notes** — any relevant details about this gift.

## Soft Credits

If a gift comes in through a peer-to-peer fundraiser or in honor of someone, you can note a soft credit recipient in the notes field. Full soft credit tracking is a planned future feature.

## Acknowledgement Letters

Donation acknowledgement is a planned future feature. For now, record all gifts promptly and use your email system to send thank-you messages.
