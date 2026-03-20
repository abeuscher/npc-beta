---
title: Import Contacts
description: How to import contacts from a CSV file, select an import source, map columns to CRM fields, handle custom fields, and understand the review and approval workflow.
version: "0.41"
updated: 2026-03-20
tags: [import, contacts, csv, crm, review]
routes:
  - filament.admin.pages.importer
  - filament.admin.pages.import-contacts
  - filament.admin.pages.import-history
  - filament.admin.pages.import-progress
  - filament.admin.pages.import-review
---

# Import Contacts

The Importer tool lets you bring contacts in from a CSV file exported from a spreadsheet, another CRM, or any system that supports CSV export. Access it via **Tools → Importer**, then click **Import Contacts**.

## Step 1 — Import Source

Before uploading a file, select or create a named **import source**. An import source represents the external system the data comes from (e.g. "Old CRM", "Salesforce 2024").

- **Select an existing source** from the dropdown if you have imported from this system before. This enables re-import matching: if you map an External ID column, the importer will update existing contacts rather than creating duplicates.
- **Create a new source** by leaving the dropdown blank and typing a name in the field that appears.

## Step 2 — Upload

Upload your CSV file. The importer accepts CSV files up to 10 MB. Wait for the upload bar to fully disappear before clicking Next.

Your file should have a header row. Column names do not need to match the CRM — you will map them in the next step.

## Step 3 — Map Columns

For each column in your CSV, select the CRM field it should map to. You can:

- Map a column to a standard field (name, email, phone, address, etc.).
- Map a column to **External ID** — this stores the source system's ID for that record, enabling update-on-re-import from the same source.
- Map a column to a **Custom field** — if the field does not exist, the importer will create it.
- Leave a column as **ignore** to skip it.

The importer detects common export formats (Bloomerang, Wild Apricot) and pre-fills mappings where it can. Adjust any that are incorrect.

## Step 4 — Preview and Confirm

Review the first five rows of your file against the field mappings. When satisfied, click **Run Import**.

## After Import — Review and Approval

Imported contacts are **not immediately visible** to regular users. They are held in a pending state until a reviewer approves the import.

If you have the **Review Imports** permission, you will see a link to the **Review Queue** once the import finishes. Otherwise, the system will display a message confirming that your import is awaiting review.

## Review Queue

Users with the **Review Imports** permission can access **Tools → Review Queue** to:

- See all pending import sessions with their metadata (source, filename, row count, who imported, date).
- **Preview** the first 20 contacts in an import.
- **Approve** an import — this makes all contacts from that import visible across the system.
- **Roll back** an import — this permanently deletes all contacts from that import. The source ID mappings are preserved for future reference. This action cannot be undone.

Once approved, contacts cannot be rolled back. The Rollback button is only available on pending and reviewing imports.

## Duplicate Handling

The importer resolves duplicates in order:

1. **External ID match** — if a column is mapped to External ID and the source ID was imported before, the existing contact is updated.
2. **Email match** — if a row has an email that already exists in the system, the importer either skips or updates based on the strategy chosen in the Map Columns step.
3. **New contact** — if no match is found, a new contact is created with `source = import`.
