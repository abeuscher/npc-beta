---
title: Custom Fields
description: How to define custom fields that extend contact, event, and page records with organization-specific data.
version: "0.24"
updated: 2026-03-16
tags: [tools, custom-fields, contacts, events, pages]
routes:
  - filament.admin.resources.custom-field-defs.index
  - filament.admin.resources.custom-field-defs.create
  - filament.admin.resources.custom-field-defs.edit
---

# Custom Fields

Custom Fields let you add organization-specific data fields to contact, event, and page records without code changes. Define a field once here, and it will appear on every record of that type.

## Custom Fields List

The Custom Fields index shows all defined fields grouped by the record type they apply to (Contact, Event, or Page).

## Creating a Custom Field

- **Label** (required) — the human-readable name for the field as it appears in forms and exports.
- **Type** — the kind of data this field stores:
  - **Text** — a short single-line string.
  - **Textarea** — a multi-line text block.
  - **Number** — a numeric value.
  - **Boolean** — a yes/no toggle.
  - **Date** — a calendar date.
  - **Select** — a dropdown with predefined options you specify.
- **Applies to** — choose Contact, Event, or Page.
- **Required** — if enabled, this field must be filled in when creating or editing a record.

## Managing Options (Select Fields)

For Select-type fields, enter the allowed options separated by commas or one per line in the Options field. These become the dropdown choices on the record form.

## Editing or Removing Fields

You can edit a field's label and options at any time. Deleting a custom field will remove the field and all data stored in it across all records — this cannot be undone.
