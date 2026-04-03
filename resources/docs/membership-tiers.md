---
title: Membership Tiers
description: How to configure membership tier definitions — billing intervals, default prices, and display order.
version: "0.24"
updated: 2026-03-23
tags: [memberships, tiers, settings]
routes:
  - filament.admin.resources.membership-tiers.index
  - filament.admin.resources.membership-tiers.create
  - filament.admin.resources.membership-tiers.edit
category: crm
---

# Membership Tiers

Membership tiers define the types of membership your organisation offers. Each tier specifies a billing interval (monthly, annual, one-time, or lifetime) and an optional default price.

A single default tier — **Standard (Annual)** — is created on installation. Organisations that only need one tier never need to visit this page.

## Creating a Tier

- **Name** (required) — the display name shown to admins when enrolling a member (e.g. "Patron", "Lifetime Friend").
- **Billing Interval** (required) — controls how the membership is categorised. Lifetime tiers hide the "Expires on" field when enrolling.
- **Default Price** — optional. Leave blank if the tier is complimentary or if pricing varies.

## Advanced Options

- **Description** — internal notes about the tier; not shown publicly.
- **Renewal Notice Days** — reserved for a future automated renewal reminder flow. Set to 30 by default.
- **Is Active** — inactive tiers are hidden from the enrolment modal.
- **Sort Order** — controls the order tiers appear in dropdowns.

## Permissions

Membership Tiers are visible to super admins only.
