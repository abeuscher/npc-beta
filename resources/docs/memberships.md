---
title: Memberships
description: How to manage membership records that track a contact's formal membership status, type, and renewal dates.
version: "0.24"
updated: 2026-03-16
tags: [memberships, crm, contacts]
routes:
  - filament.admin.resources.memberships.index
  - filament.admin.resources.memberships.create
  - filament.admin.resources.memberships.edit
---

# Memberships

Memberships track the formal membership relationship between a contact and your organization. Each membership record links a contact to a membership level and records start and end dates.

## Membership List

The Memberships index shows all active and historical membership records. You can filter by status, membership type, or date range.

## Creating a Membership

- **Contact** (required) — the contact this membership belongs to.
- **Type / Level** — the membership tier (e.g., Individual, Family, Sustaining).
- **Start Date** — when the membership period begins.
- **Expiration Date** — when the membership expires. Leave blank for indefinite memberships.
- **Status** — Active, Expired, or Lapsed.

## Renewing a Membership

To renew, open the existing membership record and update the expiration date and status. Alternatively, create a new membership record and mark the old one as Expired.

## Reporting

Membership records can be filtered and exported from the list view. Use the date range filter to find memberships expiring in the next 30–90 days to support renewal outreach.
