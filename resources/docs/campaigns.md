---
title: Campaigns
description: How to create and manage fundraising campaigns that group related solicitations and track progress toward a goal.
version: "0.25"
updated: 2026-04-01
tags: [finance, campaigns, fundraising, donations]
routes:
  - filament.admin.resources.campaigns.index
  - filament.admin.resources.campaigns.create
  - filament.admin.resources.campaigns.edit
category: finance
---

# Campaigns

Campaigns represent a fundraising effort with a defined goal, timeframe, and theme. They let you group related donations together so you can measure the success of a specific solicitation (e.g., "2026 Annual Fund," "Giving Tuesday," "Spring Gala").

## Campaigns List

The Campaigns index shows all campaigns with their name, goal, total raised, and date range. Progress toward goal is displayed to give a quick view of how each campaign is performing.

## Creating a Campaign

- **Name** (required) — the campaign's name as it will appear in reports.
- **Goal** — the fundraising target in dollars. Optional but recommended for progress tracking.
- **Start Date / End Date** — the date range of the campaign.
- **Description** — internal notes about the campaign's purpose and strategy.
- **Active** — inactive campaigns will not appear as an option when recording donations.

## Linking Donations to Campaigns

When recording a donation, select the appropriate campaign from the dropdown. Linking gifts to campaigns lets you run reports that show total revenue by campaign.

## Campaign Reporting

Filter the Donations list by campaign to see all gifts attributed to a specific effort. Use this data to calculate cost-per-dollar-raised, response rates, and year-over-year comparisons.

## Deleted Records and Trash

When you delete a campaign, the record is soft-deleted — it is hidden from normal views but kept in the database so it can be restored if needed. Donations linked to a deleted campaign keep their association and are not affected.

### Viewing trashed records

Use the **Trashed** filter above the table to control which records appear:

- **Without trashed** (default) — only active records are shown.
- **With trashed** — active and deleted records are shown together. Deleted records can be identified by the Restore action in their row.
- **Only trashed** — only deleted records are shown.

### Restoring a deleted record

Find the record using the Trashed filter set to **With trashed** or **Only trashed**, then click **Restore** in the row actions. The record is immediately returned to active status.

### Permanently deleting (purge)

Force-delete permanently removes a record from the database. This action is restricted to super-admin users and cannot be undone. Force-delete appears as an action on trashed records only when you are logged in as a super-admin.
