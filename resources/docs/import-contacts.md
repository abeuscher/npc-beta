---
title: Import Contacts
description: How to import contacts from a CSV file, map columns to CRM fields, handle custom fields, and review import history.
version: "0.24"
updated: 2026-03-16
tags: [import, contacts, csv, crm]
routes:
  - filament.admin.pages.import-contacts
  - filament.admin.pages.import-history
  - filament.admin.pages.import-progress
---

# Import Contacts

The Import Contacts tool lets you bring in contacts from a CSV file exported from a spreadsheet, another CRM, or any system that supports CSV export.

## Preparing Your File

- Export your contacts as a CSV with a header row. Column names do not need to match the CRM exactly — you will map them in the next step.
- Remove any rows that are not contacts (summary rows, totals, etc.).
- Ensure each row represents one unique person.

## Mapping Columns

After uploading your file, the importer shows a field mapping screen. For each column in your CSV, select the corresponding CRM field. You can:

- Map a column to a standard field (name, email, phone, address).
- Map a column to a custom field if you have defined any.
- Skip columns you do not want to import.

## Duplicate Handling

The importer matches on email address. If a contact with the same email already exists, the import will update that record rather than create a duplicate.

## Import Progress

After submitting the mapping, the import runs in the background. The progress page refreshes automatically and shows how many records have been processed, created, and updated.

## Import History

The Import History page shows a log of all past imports — the date, file name, number of records, and outcome. Click any row to see details about that import, including any errors.
