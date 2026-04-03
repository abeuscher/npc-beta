---
title: Organizations
description: How to create and manage organization records representing businesses, foundations, or other entities with a relationship to your nonprofit.
version: "0.25"
updated: 2026-04-01
tags: [organizations, crm, contacts]
routes:
  - filament.admin.resources.organizations.index
  - filament.admin.resources.organizations.create
  - filament.admin.resources.organizations.edit
category: crm
---

# Organizations

An Organization record represents a business, foundation, government body, or other non-individual entity. Contacts can be linked to an organization to reflect their professional affiliation.

## Organization List

The Organizations index shows all organization records. You can search by name and see how many contacts are associated with each.

## Creating an Organization

- **Name** (required) — the organization's full legal or common name.
- **Website** — optional URL for the organization's website.
- **Phone** — main phone number.
- **Address** — primary mailing address.

## Linking Contacts

Individual contacts can be linked to an organization via the **Organization** field on the contact form. A contact can belong to one organization. There is no limit on how many contacts can be linked to a single organization.

## Use Cases

- Tracking a corporate sponsor and all of their employee contacts together.
- Recording foundation contacts for grant management.
- Grouping volunteers from a company that sends teams to events.

## Deleted Records and Trash

When you delete an organization, the record is soft-deleted — it is hidden from normal views but kept in the database so it can be restored if needed. Contacts linked to the organization keep their link while the record is in the trash.

### Viewing trashed records

Use the **Trashed** filter above the table to control which records appear:

- **Without trashed** (default) — only active records are shown.
- **With trashed** — active and deleted records are shown together. Deleted records can be identified by the Restore action in their row.
- **Only trashed** — only deleted records are shown.

### Restoring a deleted record

Find the record using the Trashed filter set to **With trashed** or **Only trashed**, then click **Restore** in the row actions. The record is immediately returned to active status.

### Permanently deleting (purge)

Force-delete permanently removes a record from the database. This action is restricted to super-admin users and cannot be undone. Force-delete appears as an action on trashed records only when you are logged in as a super-admin. When an organization is force-deleted, contacts that referenced it will have their organization link cleared.
