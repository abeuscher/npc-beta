---
title: Record Detail Views
description: Configure widget sets that render in the sidebar of admin record-edit pages, scoped per record type.
version: "0.70"
updated: 2026-04-27
tags: [tools, admin, views, widgets, record-detail]
routes:
  - filament.admin.resources.record-detail-views.index
  - filament.admin.resources.record-detail-views.create
  - filament.admin.resources.record-detail-views.edit
category: tools
---

# Record Detail Views

Record Detail Views configure the widget sets that render in the sidebar slot of an admin record-edit page (for example, the footer widgets on a Contact's edit page). Each View is bound to one record type and identified by a unique handle.

Access requires the `manage_record_detail_views` permission (granted to `super_admin` by default).

## Creating a View

- **Record Type** (required) — the model class the View renders against. Adding a new record type to the dropdown is a code change; no dynamic discovery.
- **Handle** (required) — per-record-type slug used by the registry, e.g. `contact_overview`. Lowercase with underscores. Must be unique within a record type.
- **Label** (required) — the human-readable label used by the sub-nav primitive when more than one View is bound to the same record type.
- **Order** — lower numbers sort first within a record type.

## Editing the widget set

Open the View's edit page and scroll past the metadata form. The page builder mounts beneath the form in `record_detail` mode:

- The picker only shows widgets that opt into the `record_detail_sidebar` slot.
- Column layouts are allowed.
- Only **Background** and **Text** appearance controls are editable; padding, margin, and full-width are intentionally hidden because the host edit page controls those.

## Chrome Views are excluded

The `page_template_header` and `page_template_footer` rows seeded against `Template` are anchors used by the Templates editor, not authoring surfaces. They are filtered out of the Record Detail Views table; chrome editing remains in the Templates editor.

## Permissions

This page is gated by `manage_record_detail_views`. The page-builder API endpoints that read and mutate widgets are gated by the same permission and additionally enforce that every widget belongs to the View in the URL (IDOR guard).
