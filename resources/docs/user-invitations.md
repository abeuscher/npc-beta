---
title: User Invitations
description: How to invite new admin users by email, resend an invitation, or revoke a pending invite.
version: "0.25"
updated: 2026-03-23
tags: [settings, users, invitations, access]
routes:
  - filament.admin.resources.users.index
  - filament.admin.resources.users.edit
---

# User Invitations

Instead of setting a password for a new admin user yourself, you can send them an invitation email. The invited user follows a secure link to set their own password and activate their account.

## Sending an Invitation

1. Go to **Settings → Users**.
2. Find the user you want to invite and click **Invite** in the row actions, or open the user record and click **Send Invitation** in the page header.
3. A three-step preview wizard opens. Step 1 lets you confirm or adjust the user's role. Step 2 shows a preview of the invitation email. Step 3 has the final **Send Now** button.

The user will receive an email containing a secure link. The link expires after 48 hours.

> **Note:** The Invite action is not shown for users who already have an active login session.

## What the Invited User Sees

The email contains a link to a set-password page. The user enters and confirms a password (minimum 12 characters), then is automatically logged in to the admin panel.

## Resending an Invitation

If the original link has not yet been used, you can resend it from the user's edit page. Click **Resend Invitation** in the page header. A preview wizard opens showing the invitation email before you confirm. This generates a fresh link and invalidates the previous one.

## Revoking an Invitation

To cancel a pending invitation without deactivating the user account, click **Revoke Invitation** on the user's edit page. This deletes the pending token. The user record itself is not changed.

## Expired Links

Invitation links expire after 48 hours and are invalidated immediately when used. If a user's link has expired, use **Resend Invitation** to generate a new one.
