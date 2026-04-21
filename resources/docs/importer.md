---
title: Importer
description: Landing page for all import tools — import contacts, events, and financial data. Also hosts the review queue for users with the review_imports permission.
version: "0.44"
updated: 2026-03-20
tags: [import, contacts, crm]
routes:
  - filament.admin.pages.importer-page
category: tools
---

# Importer

The Importer is the central entry point for bringing data into the CRM from external systems. It also serves as the **Review Queue** for users who have the `review_imports` permission.

## Available Importers

- **Import Contacts** — import a CSV file of contacts from a spreadsheet or another CRM. See the Import Contacts help article for the full workflow.
- **Import Events** — import events, registrations, and (optionally) linked transactions from one CSV. One row per registration.
- **Import Donations** — import donations and matching transaction rows. Contacts must already exist (or be auto-created via the row's contact columns).
- **Import Memberships** — import membership records against existing contacts.
- **Import Invoice Details** — import historical transactions, grouped into invoices by a shared invoice number.
- **Import Notes** — import structured interactions (calls, meetings, emails, tasks) from an activities CSV. See the Import Notes help article for the full workflow.

## Queue lock — one active import per content type

Only one import of each content type (e.g. contacts) may be in the pending or reviewing state at a time. If a previous import of that type is awaiting review, its card is shown as disabled with an explanation. You must approve or roll back the outstanding import before starting a new one of the same type.

## Review Queue

Users with the `review_imports` permission see a **Review Queue** section at the bottom of the Importer page. It lists all import sessions in `pending` or `reviewing` status and allows reviewers to:

- **Preview** the first 20 new contacts and the first 20 staged updates to existing contacts.
- **Approve** — makes all new contacts visible, applies all staged field changes to existing contacts, and adds an audit note to each affected contact.
- **Roll back** — permanently deletes all new contacts from the import, discards all staged updates (adding a note to each affected contact), and removes the import session. Source ID mappings are preserved.

## Import History

A link to **View Import History** is available at the bottom of the Importer landing page (visible to users with `import_data`). It shows all past imports with their status, row counts, and any errors.

## Sensitive data rejection

The importer automatically rejects CSV files that appear to contain sensitive financial or personal identifiers. If a file is rejected, the entire import is cancelled — no contacts are created. See the Import Contacts help article for details on what is detected and how to prepare your file.

## Permissions

- **import_data** — required to access the Importer and run imports.
- **review_imports** — required to see the Review Queue, approve imports, and roll back imports.

Either permission grants access to the Importer page. A user can import data without being able to approve it, and a reviewer can approve imports without being able to run them. The `review_imports` permission is a deliberate, narrow grant — it is not assigned to any role by default.
