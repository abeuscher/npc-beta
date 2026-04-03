---
title: Notes
description: How to add and manage notes attached to contact records to track interactions, calls, and relationship history.
version: "0.68"
updated: 2026-04-01
tags: [notes, crm, contacts]
routes:
  - filament.admin.resources.contacts.notes
category: crm
---

# Notes

Notes allow staff to record interactions, observations, and follow-up reminders tied to individual contacts. A good note log creates a shared relationship history that the whole team can reference.

## Accessing a Contact's Notes

Notes are managed from the dedicated Notes sub-page on each contact record. Open a contact, then click **Notes** in the page header (or use the breadcrumb trail) to reach the notes view for that contact. All notes are scoped to the contact — there is no global notes list.

## Adding a Note

Click **Create note** in the top-right of the Notes sub-page. A modal form appears with two fields:

- **Note** (required) — the content of the note. Be specific: include what was discussed, any agreed-upon next steps, and relevant context.
- **Occurred At** — when the interaction took place. Defaults to the current date and time.

The author is recorded automatically as the currently logged-in user.

## Editing and Deleting Notes

Each note row has inline **Edit** and **Delete** actions. Edit opens the same modal form. Deletion is immediate with no confirmation prompt — take care before clicking.

## Deleted Records and Trash

When you delete a note, the record is soft-deleted — it is hidden from normal views but kept in the database so it can be restored if needed.

### Viewing trashed records

Use the **Trashed** filter on the notes list to control which records appear:

- **Without trashed** (default) — only active records are shown.
- **With trashed** — active and deleted records are shown together. Deleted records can be identified by the Restore action in their row.
- **Only trashed** — only deleted records are shown.

### Restoring a deleted record

Find the record using the Trashed filter set to **With trashed** or **Only trashed**, then click **Restore** in the row actions. The record is immediately returned to active status.

### Permanently deleting (purge)

Force-delete permanently removes a record from the database. This action is restricted to super-admin users and cannot be undone. Force-delete appears as an action on trashed records only when you are logged in as a super-admin.

## Best Practices

- Record notes promptly after calls or meetings while details are fresh.
- Use consistent language across the team so notes are searchable and understandable by anyone.
- Avoid storing sensitive personal information beyond what is necessary for your mission.
