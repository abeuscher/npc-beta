---
title: Import Contacts
description: How to import contacts from a CSV file, select an import source, map columns to CRM fields, handle custom fields, and understand the review and approval workflow.
version: "0.44"
updated: 2026-03-20
tags: [import, contacts, csv, crm, review]
routes:
  - filament.admin.pages.import-contacts-page
  - filament.admin.pages.import-history-page
  - filament.admin.pages.import-progress-page
category: tools
---

# Import Contacts

The Importer tool lets you bring contacts in from a CSV file exported from a spreadsheet, another CRM, or any system that supports CSV export. Access it via **Tools → Importer**, then click **Import Contacts**.

## Queue lock — one active import at a time

Only one contact import may be in the pending or reviewing state at a time. If a previous import is awaiting review, the Import Contacts card is disabled. Approve or roll back the outstanding import before starting a new one.

## Step 1 — Import Source

Before uploading a file, select or create a named **import source**. An import source represents the external system the data comes from (e.g. "Old CRM", "Salesforce 2024").

- **Select an existing source** from the dropdown if you have imported from this system before. This enables re-import matching: if you map an External ID column, the importer will stage updates to existing contacts rather than creating duplicates.
- **Create a new source** by leaving the dropdown blank and typing a name in the field that appears.

## Step 2 — Upload

Upload your CSV file. The importer accepts CSV files up to 10 MB. Wait for the upload bar to fully disappear before clicking Next.

Your file should have a header row. Column names do not need to match the CRM — you will map them in the next step.

## Step 3 — Map Columns

For each column in your CSV, select the CRM field it should map to. You can:

- Map a column to a standard field (name, email, phone, address, etc.).
- Map a column to **External ID** — this stores the source system's ID for that record, enabling staged updates on re-import from the same source.
- Map a column to a **Custom field** — if the field does not exist, the importer will create it.
- Leave a column as **ignore** to skip it.

The importer detects common export formats (Bloomerang, Wild Apricot) and pre-fills mappings where it can. Adjust any that are incorrect.

## Step 4 — Preview and Confirm

Review the first five rows of your file against the field mappings. When satisfied, click **Run Import**.

## After Import — Review and Approval

Imported contacts are **not immediately visible** to regular users. They are held in a pending state until a reviewer approves the import.

If you have the **Review Imports** permission, you will see a link to the **Review Queue** once the import finishes. Otherwise, the system will display a message confirming that your import is awaiting review.

## How existing contacts are handled

When the importer finds a match (by External ID or by email, if the "Update" duplicate strategy is chosen), the existing contact is **not changed immediately**. Instead, the proposed changes are **staged**: they are held and associated with the import session, awaiting reviewer approval.

A note is added to the contact confirming that a match was found and changes are pending.

When the reviewer approves the import:
- All staged field changes are applied to the matched contacts.
- Any import tags are added to those contacts (without removing existing tags).
- An audit note is written to each contact recording who approved the change.

If the reviewer rolls back the import:
- All staged changes are discarded.
- A note is written to each affected contact recording the rollback.
- No changes are made to the existing contacts' data.

## Review Queue

Users with the **Review Imports** permission can access the review queue from **Tools → Importer** to:

- See all pending import sessions with their metadata (source, filename, row count, who imported, date).
- **Preview** the first 20 new contacts and the first 20 staged updates (showing current field values and proposed changes side-by-side).
- **Approve** an import — this makes all new contacts from that import visible across the system and applies all staged updates to existing contacts.
- **Roll back** an import — this permanently deletes all new contacts from that import and discards all staged updates. The source ID mappings are preserved for future reference. This action cannot be undone.

Once approved, contacts cannot be rolled back. The Rollback button is only available on pending and reviewing imports.

## Sensitive data rejection

Before processing any rows, the importer scans your CSV for data that must not enter the system. If a violation is found, the entire import is **rejected immediately** — no contacts are created.

The importer checks two things:

**Column headers** — if any column is named after a sensitive field type, the file is rejected before any row is read. Blocked names include: `ssn`, `social_security`, `social_security_number`, `credit_card`, `credit_card_number`, `card_number`, `pan`, `routing_number`, `account_number`, `bank_account`, `aba_routing`, `driver_license`, `drivers_license`, `dl_number`.

**Cell contents** — every cell in every row is scanned for:
- Credit card numbers (13–19 digit sequences that pass the Luhn check, with or without spaces/dashes)
- Social Security Numbers (`###-##-####` format, or any 9-digit sequence)
- ABA bank routing numbers (9-digit numbers whose first two digits indicate a Federal Reserve routing range)

When a file is rejected you will see the message: **"This import was rejected because it contains data that may include sensitive financial or personal identifiers. Remove the flagged data and try again."** A detail line identifies the specific row and column that triggered the rejection.

To fix a rejected import: open the original file, remove or blank out the flagged column(s) or cell(s), save it, and start a new import.

## Duplicate Handling

The importer resolves duplicates in order:

1. **External ID match** — if a column is mapped to External ID and the source ID was imported before, the proposed changes to the existing contact are staged for reviewer approval.
2. **Email match** — if a row has an email that already exists in the system, the importer either skips or stages an update based on the strategy chosen in the Map Columns step.
3. **New contact** — if no match is found, a new contact is created with `source = import` and held for reviewer approval.
