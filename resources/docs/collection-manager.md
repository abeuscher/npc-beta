---
title: Collection Manager
description: Super-admin tool for defining custom data collections and their fields, used by page builder widgets to display structured content.
version: "0.68"
updated: 2026-03-23
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
