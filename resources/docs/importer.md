---
title: Importer
description: Landing page for all import tools — import contacts, events, and financial data.
version: "0.41"
updated: 2026-03-20
tags: [import, contacts, crm]
routes:
  - filament.admin.pages.importer
---

# Importer

The Importer is the central entry point for bringing data into the CRM from external systems.

## Available Importers

- **Import Contacts** — import a CSV file of contacts from a spreadsheet or another CRM. See the Import Contacts help article for the full workflow.
- **Import Events** — coming soon.
- **Import Financial Data** — coming soon.

## Permissions

- **import_data** — required to access the Importer and run imports.
- **review_imports** — required to access the Review Queue, approve imports, and roll back imports.

These permissions are assigned separately. A user can import data without being able to approve it, and a reviewer can approve imports without being able to run them.
