---
title: Roles
description: How to create and manage roles that control what each user can see and do in the admin panel.
version: "0.24"
updated: 2026-03-16
tags: [settings, roles, permissions, access]
routes:
  - filament.admin.resources.roles.index
  - filament.admin.resources.roles.create
  - filament.admin.resources.roles.edit
---

# Roles

Roles define what a user can see and do in the admin panel. Every user must be assigned a role. Permissions are granted at the role level — not to individual users.

## Roles List

The Roles index shows all defined roles. The built-in Administrator role has full access and cannot be deleted.

## Creating a Role

- **Name** (required) — a descriptive label for the role (e.g., "Development Staff," "Communications," "Read Only").
- **Permissions** — select which resources and actions this role can perform. Permissions are organized by resource (Contacts, Donations, Pages, etc.) and action (view, create, edit, delete).

## Common Role Patterns

- **Administrator** — full access to all resources and settings.
- **Development Staff** — access to CRM and Finance sections; no access to CMS or Settings.
- **Communications** — access to CMS (Pages, Posts, Events); no access to Finance or Settings.
- **Read Only** — view-only access to selected resources; cannot create, edit, or delete.

## Deleting a Role

Before deleting a role, reassign all users currently holding that role to a different role. Deleting a role while users still hold it will remove their access immediately.

## Best Practice

Use the principle of least privilege: assign users the most restricted role that still lets them do their job. Review role assignments periodically as staff responsibilities change.
