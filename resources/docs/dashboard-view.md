---
title: Dashboard View
description: Arrange per-role admin dashboard widgets using the page builder.
version: "0.70"
updated: 2026-04-28
tags: [tools, admin, dashboard, widgets]
routes:
  - filament.admin.pages.dashboard-view
category: tools
---

# Dashboard View

Dashboard View lets an admin arrange the tiles that appear on the admin landing page (`/admin`). Each role (e.g. *Super Admin*, *CMS Editor*) can have its own arrangement; logged-in users see the dashboard belonging to their first assigned role.

Access requires the `manage_dashboard_config` permission (granted to `super_admin` by default).

## How it works

- Choose a role from the dropdown at the top of the page.
- If that role doesn't have a dashboard yet, click **Create dashboard for {role}** to seed one.
- Use the page builder that appears below to add, reorder, configure, or remove widgets. The picker only shows widgets that opt into the dashboard slot (currently *Memos*, *Quick Actions*, *This Week's Events*).
- Only **Background** and **Text** appearance controls are editable for dashboard widgets — padding, margin, and full-width are intentionally hidden because the dashboard grid controls those.

## Multi-role users

When a user has more than one role, the dashboard resolver picks the role with the lowest `roles.id` — which in practice means the first role ever created in the installation. To change which dashboard a user sees, change which role is first in their assignment list or (simpler) configure the role they end up resolving to.
