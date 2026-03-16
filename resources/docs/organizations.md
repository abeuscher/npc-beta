---
title: Organizations
description: How to create and manage organization records representing businesses, foundations, or other entities with a relationship to your nonprofit.
version: "0.24"
updated: 2026-03-16
tags: [organizations, crm, contacts]
routes:
  - filament.admin.resources.organizations.index
  - filament.admin.resources.organizations.create
  - filament.admin.resources.organizations.edit
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
