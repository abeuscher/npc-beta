---
title: Importer
description: Landing page for all import tools — import contacts, events, and financial data.
version: "0.43"
updated: 2026-03-20
tags: [import, contacts, crm]
routes:
  - filament.admin.pages.importer-page
---

# Importer

The Importer is the central entry point for bringing data into the CRM from external systems.

## Available Importers

- **Import Contacts** — import a CSV file of contacts from a spreadsheet or another CRM. See the Import Contacts help article for the full workflow.
- **Import Events** — coming soon.
- **Import Financial Data** — coming soon.

## Import History

A link to **View Import History** is available at the bottom of the Importer landing page. It shows all past imports with their status, row counts, and any errors.

## Sensitive data rejection

The importer automatically rejects CSV files that appear to contain sensitive financial or personal identifiers. If a file is rejected, the entire import is cancelled — no contacts are created. See the Import Contacts help article for details on what is detected and how to prepare your file.

## Permissions

- **import_data** — required to access the Importer and run imports.
- **review_imports** — required to access the Review Queue, approve imports, and roll back imports.

These permissions are assigned separately. A user can import data without being able to approve it, and a reviewer can approve imports without being able to run them.
