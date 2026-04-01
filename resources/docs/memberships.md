---
title: Memberships
description: How to manage membership records that track a contact's formal membership status, type, and renewal dates.
version: "0.25"
updated: 2026-04-01
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

## Deleted Records and Trash

When you delete a membership, the record is soft-deleted — it is hidden from normal views but kept in the database so it can be restored if needed. Memberships can also be managed from the Memberships tab on a contact record, where the same trash controls are available.

### Viewing trashed records

Use the **Trashed** filter above the table to control which records appear:

- **Without trashed** (default) — only active records are shown.
- **With trashed** — active and deleted records are shown together. Deleted records can be identified by the Restore action in their row.
- **Only trashed** — only deleted records are shown.

### Restoring a deleted record

Find the record using the Trashed filter set to **With trashed** or **Only trashed**, then click **Restore** in the row actions. The record is immediately returned to active status.

### Permanently deleting (purge)

Force-delete permanently removes a record from the database. This action is restricted to super-admin users and cannot be undone. Force-delete appears as an action on trashed records only when you are logged in as a super-admin.
