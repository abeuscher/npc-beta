---
title: Donors
description: View and filter all donors, generate year-end tax receipts, and send acknowledgement emails.
version: "0.25"
updated: 2026-03-26
tags: [finance, donations, receipts, tax, donors]
routes:
  - filament.admin.pages.donors
---

# Donors

The Donors page lists all contacts who have made active donations, with filtering by tax year and minimum total. From this page you can also send year-end tax receipt emails.

## Filters

**Tax Year** — Select a specific calendar year to show only donations active in that year, or choose *All time* to show total giving across all years.

**Minimum Total** — Show only donors whose total giving meets or exceeds this amount. Defaults to $250 (the IRS single-contribution acknowledgement threshold). Set to 0 to show all donors regardless of total.

**Include donors below threshold** — Check this to show all donors in the selected year alongside those who meet the threshold, so you can see the full picture.

## Columns

The table shows donor name, org roles, total donated, and date of most recent donation. Phone, email, and address columns are available but hidden by default — use the column toggle to show them.

## Sending tax receipts

Use the **⋯** menu (top right) to send year-end acknowledgement emails:

- **Send System Emails to Pending Recipients** — sends a receipt to every eligible donor in the selected year who has not yet received one. A confirmation modal is shown before sending.
- **Force Re-send System Emails to All** — sends a receipt to every eligible donor in the selected year, including those already receipted. Each send creates a new receipt record for the audit trail. A confirmation modal with a warning is shown before sending.

Receipts are sent using the configured system email (Resend). The email template can be customised under System Emails.

## What receipts contain

Each receipt includes a fund-by-fund breakdown of all active donations in the tax year, the restriction type for each fund, the grand total, and a standard IRS acknowledgement line stating no goods or services were exchanged.

## Activity log

Every time a tax receipt email is sent — whether via **Send System Emails to Pending Recipients** or **Force Re-send System Emails to All** — a `receipt_sent` event is written to the activity log, recording the tax year, total amount, and whether it was a re-send. The activity log is visible on the individual contact's record.

## Create Mailing List

The **Create Mailing List** button creates a new mailing list from the contacts currently visible in the Donors table.

A modal lets you set the list name (defaulted to `Donors — {year} — ${threshold}+`), the tax year, and the minimum total. The list is a static snapshot of whichever donors matched at the moment of creation — it will not update automatically as donations change. After creation you are redirected to the new list's edit page in Mailing Lists, where you can rename it, review the contacts, or export to CSV.
