---
title: Users
description: How to manage admin panel user accounts, including creating new users, assigning roles, and resetting passwords.
version: "0.24"
updated: 2026-03-16
tags: [settings, users, roles, access]
routes:
  - filament.admin.resources.users.index
  - filament.admin.resources.users.create
  - filament.admin.resources.users.edit
---

# Users

Users are the accounts that can log into the admin panel. Each user is assigned a role that determines what they can see and do.

## Users List

The Users index shows all admin accounts with their name, email, and role. Only administrators can access this screen.

## Creating a User

- **Name** (required) — the user's display name.
- **Email** (required) — used to log in. Must be unique.
- **Password** — set an initial password. The user should change it on first login.
- **Role** (required) — the role that controls this user's permissions. See the Roles help article for details.

## Editing a User

Open a user record to change their name, email, or role. To reset a password, edit the user record and enter a new password in the password field.

## Deactivating a User

To remove admin access without deleting the account, change the user's role to one with no permissions, or delete the user record. Deleting a user does not delete records they created.

## Your Own Account

You can update your own name, email, and password from the profile menu in the top-right corner of the admin panel. You cannot change your own role.
