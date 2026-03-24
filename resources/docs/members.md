---
title: Members
description: A focused view of contacts who hold an active membership, with filtering by tier.
version: "0.25"
updated: 2026-03-23
tags: [members, crm, membership]
routes:
  - filament.admin.resources.members.index
---

# Members

The Members list is a read-only view of all contacts with an active membership. It is scoped automatically — only contacts with at least one active membership record appear here.

## Columns

- **Name** — the contact's full name; click to open their full Contact record for editing.
- **Email** — the contact's primary email address.
- **Membership Tier** — the tier associated with their active membership.
- **Status** — always Active in this view (the list is pre-filtered to active members).
- **Member Since** — the start date of their active membership.

## Filtering

Use the **Membership Tier** filter to narrow the list to a specific tier.

## Editing members

This view is read-only. To edit a member's details, click their name or the Edit button — you will be taken to their full Contact record where all fields and membership history are available.

## Creating members

New members are created by promoting an existing contact using the **Promote to Member** action on the Contact record. Members cannot be created directly from this view.
