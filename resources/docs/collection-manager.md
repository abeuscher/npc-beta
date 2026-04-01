---
title: Collection Manager
description: Super-admin tool for defining custom data collections and their fields, used by page builder widgets to display structured content.
version: "0.69"
updated: 2026-04-01
tags: [admin, cms, collections, developer]
routes:
  - filament.admin.resources.collections.index
  - filament.admin.resources.collections.create
  - filament.admin.resources.collections.edit
---

# Collection Manager

Collections are structured data stores that page builder widgets can read from. A collection defines a shape — a set of typed fields — and a set of items that conform to that shape. For example, a "Team Members" collection might have fields for name, photo, title, and bio; a widget on the about page then renders those items as a staff grid.

Access is restricted to super-admin users. This is a developer-facing tool — changing collection definitions can break widgets that depend on them.

## System Collections

Two collections are pre-installed and maintained automatically:

- **blog_posts** — all published blog posts, sourced from the Pages table.
- **events** — all published events.

System collections are read-only. Their field definitions cannot be edited or deleted, and they cannot be removed.

## Custom Collections

To create a custom collection, click **New Collection** and fill in:

- **Name** — a human-readable label (e.g. "Team Members").
- **Handle** — a machine-readable identifier auto-generated from the name (e.g. `team_members`). The handle is used by widgets to reference this collection and cannot be changed after creation.
- **Source type** — set to **Custom** for manually managed collections.
- **Description** — internal notes about what this collection is for.
- **Public** — when enabled, this collection can be queried by public-facing page widgets. CRM and financial data is excluded from the public surface regardless of this setting. Disable for any collection containing sensitive or internal information.
- **Active** — inactive collections are hidden from widget configuration but their data is preserved.

## Fields

After creating a collection, use the **Fields** repeater to define its shape. Each field has:

- **Key** — the machine-readable field identifier (e.g. `photo`, `job_title`).
- **Label** — the human-readable label shown in the item editor.
- **Type** — the data type: `text`, `textarea`, `rich_text`, `number`, `date`, `toggle`, `image`, `url`, `email`, or `select`.
- **Required** — whether this field must have a value for the item to save.
- **Help text** — optional guidance shown below the field in the item editor.
- **Options** — for `select` fields only: a list of allowed values.

Fields cannot be reordered after items have been added without risk of data misalignment — plan the field structure before adding content.

## Collection Items

Once a collection is defined, click through to its edit page and use the **Items** tab to add, edit, reorder, and delete records. Each item presents a form based on the collection's field definitions.

## Deleted Records and Trash

When you delete a custom collection or collection item, the record is soft-deleted — it is hidden from normal views but kept in the database so it can be restored if needed. System collections cannot be deleted.

### Viewing trashed records

Use the **Trashed** filter above the table to control which records appear:

- **Without trashed** (default) — only active records are shown.
- **With trashed** — active and deleted records are shown together. Deleted records can be identified by the Restore action in their row.
- **Only trashed** — only deleted records are shown.

The same Trashed filter is available on the Items tab within each collection.

### Restoring a deleted record

Find the record using the Trashed filter set to **With trashed** or **Only trashed**, then click **Restore** in the row actions. The record is immediately returned to active status.

### Permanently deleting (purge)

Force-delete permanently removes a record from the database. This action is restricted to super-admin users and cannot be undone. Force-delete appears as an action on trashed records only when you are logged in as a super-admin. Force-deleting a collection will also permanently destroy all of its items.
