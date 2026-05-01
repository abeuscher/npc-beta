---
title: Data Imports
description: Umbrella reference for every importer in the CRM — contacts, events, donations, memberships, invoice details, notes, and the shared review / history workflow. Covers CSV upload, source selection, column mapping, duplicate handling, approval, and rollback.
version: "0.46"
updated: 2026-05-01
tags: [import, contacts, events, donations, memberships, invoice-details, notes, csv, crm, review]
routes:
  - filament.admin.pages.import-contacts-page
  - filament.admin.pages.import-progress-page
  - filament.admin.pages.import-events-page
  - filament.admin.pages.import-events-progress-page
  - filament.admin.pages.import-donations-page
  - filament.admin.pages.import-donations-progress-page
  - filament.admin.pages.import-memberships-page
  - filament.admin.pages.import-memberships-progress-page
  - filament.admin.pages.import-invoice-details-page
  - filament.admin.pages.import-invoice-details-progress-page
  - filament.admin.pages.import-history-page
category: tools
---

# Data Imports

Every import in the CRM follows the same shape — pick a source, upload a CSV, map columns, preview, run. Approval and rollback happen through the shared **Review Queue**. This page is the cross-cutting reference; per-type specifics live in their own sections below.

Access any importer via **Tools → Importer** and click the card for the data type you want.

## Available importers

| Importer | What it brings in |
|---|---|
| Import Contacts | People and organisations. Always run this first — other importers match against existing contacts. |
| Import Events and Event Registrations | Events, their registrations, and (optionally) linked transaction rows. One CSV row per registration. |
| Import Donations | Donations and matching transaction rows. Contacts must already exist or be auto-created from row columns. |
| Import Memberships | Membership records against existing contacts. |
| Import Invoice Details | Historical transactions grouped into invoices by a shared invoice number. |
| Import Notes | Structured interactions (calls, meetings, emails, tasks) — see the dedicated [Import Notes](import-notes) article for the full workflow. |

## Queue lock — one active import per type

Only one import of each content type may be in the pending or reviewing state at a time. If a previous import of that type is awaiting review, its card is shown as disabled with an explanation. Approve or roll back the outstanding import before starting a new one of the same type.

## Common workflow

### Step 1 — Import Source

Before uploading a file, select or create a named **import source**. An import source represents the external system the data comes from (e.g. "Old CRM", "Salesforce 2024").

- **Select an existing source** from the dropdown if you have imported from this system before. This enables re-import matching: if you map an External ID column, the importer stages updates to existing records rather than creating duplicates.
- **Create a new source** by leaving the dropdown blank and typing a name in the field that appears.

### Step 2 — Upload

Upload your CSV file. The importer accepts CSV files up to 10 MB. Wait for the upload bar to fully disappear before clicking Next.

Your file should have a header row. Column names do not need to match the CRM — you will map them in the next step.

### Step 3 — Map Columns

For each column in your CSV, select the CRM field it should map to. You can:

- Map a column to a standard field for the target type (name, email, phone, date, amount, etc.).
- Map a column to **External ID** — this stores the source system's ID for that record, enabling staged updates on re-import from the same source.
- Map a column to a **Custom field** — supported for contacts, donations, events, memberships, notes, and invoice details. If the field does not exist, the importer will create it. You must pick a Field type (text, number, date, boolean, select, rich text) for each new custom field before the row counts as mapped.
- Leave a column as **ignore** to skip it. To clear a column you previously mapped, click the small × on the right edge of its destination value.

#### Row indicators and search

Each mapping row carries a status badge to its left. A red **×** means the row is not yet fully mapped — destination not picked, or a custom-field row missing its Field type / label / handle. The badge flips to a green **✓** once the row is fully resolved, so you can scan a long mapping list at a glance and find the rows that still need attention.

The destination dropdown is searchable: click into it and start typing the field name to filter the option list. The search matches across both group names (e.g. "Contact", "Address") and individual field labels.

#### Duplicate column mappings

If you map two columns to the same destination field, both rows are pulled out of the main list and grouped into a **Duplicate column mappings** section at the bottom of the page, so you can compare them side-by-side and pick a resolution (prefer one column, split into a custom field, or drop the others). A notification at the top of the page tells you when this has happened and points you to the bottom of the form.

The importer detects common export formats and pre-fills mappings where it can. Adjust any that are incorrect.

#### Duplicate-row strategy (bottom of the page)

After mapping all columns, the **When an imported row matches an existing record** block at the bottom of the page asks how you want to handle CSV rows that match an existing record. You must pick one — there is no default — and your choice applies to every row in this import.

### Step 4 — Preview and Confirm

Review the first five rows of your file against the field mappings. When satisfied, click **Run Import**.

---

## Contacts

The Contacts importer is the foundation — it populates people and organisations that every other importer matches against. Access it via **Tools → Importer → Import Contacts**.

### Custom fields

The Contacts importer supports mapping CSV columns to custom contact fields. If a custom field does not yet exist, the importer will create it based on the column name.

### How existing contacts are handled

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

### Duplicate handling

The Contacts importer resolves duplicates in order:

1. **External ID match** — if a column is mapped to External ID and the source ID was imported before, the proposed changes to the existing contact are staged for reviewer approval.
2. **Email match** — if a row has an email that already exists in the system, the importer either skips, stages an update, or creates a duplicate based on the strategy chosen in the Map Columns step.
3. **New contact** — if no match is found, a new contact is created with `source = import` and held for reviewer approval.

---

## Events and Event Registrations

The Events importer brings in events together with their registrations from a single CSV. **One row represents one registration**; event-level columns (title, start date, venue, capacity) are read from the first row that introduces each event. Access it via **Tools → Importer → Import Events and Event Registrations**.

### Prerequisite — Contacts

Each registration row must resolve to a contact. If the registrant already exists in the CRM, map the row's email or External ID to let the importer match. If a registrant does not exist, the importer can auto-create a contact from the row's contact columns (first name, last name, email, phone, etc.) — the auto-created contact is tied to the import session so rollback cascades.

### Transaction linking (optional)

If your row carries a paid-registration amount and a matching transaction can be identified (by a `transaction_id` / external reference column), the importer links the registration to the transaction. Otherwise the registration is imported without a transaction link and can be reconciled later.

### Duplicate handling

Registrations are matched on `(event, contact)` pairs within an import source. Re-importing the same pair stages an update to the registration; the duplicate strategy (skip / update) selected at Map Columns determines behaviour on match.

---

## Donations

The Donations importer brings historical donations (and matching transaction rows) into the CRM. Access it via **Tools → Importer → Import Donations**.

### Prerequisite — Contacts

Every donation row must resolve to a donor contact. Map the row's email, External ID, or an "Org Contact" sentinel column (for corporate donations) to enable matching. Rows whose contact cannot be matched and cannot be auto-created land in the error table.

### Donation vs Transaction columns

The importer distinguishes donation-level fields from transaction-level fields:

- **Donation columns** (what was given): amount, fund, campaign, anonymous flag, in-memory-of, pledge vs one-time.
- **Transaction columns** (how it was paid): transaction date, method, processor, processor reference, status.

Session 202 locked these label pairs (`Donation Amount` / `Transaction Amount`) to remove ambiguity — refer to the canonical template download if you are unsure which column to map.

### Duplicate handling

Donations deduplicate on `(import_source, external_id)`. Re-imports with a matching external ID stage an update to the existing donation under the selected strategy.

---

## Memberships

The Memberships importer brings membership records in against existing contacts. Access it via **Tools → Importer → Import Memberships**.

### Prerequisite — Contacts and Membership Tiers

- **Contacts must already exist.** Rows whose member contact cannot be matched (by email or External ID) are reported as errors.
- **Membership tiers must already be configured.** If your CSV carries a tier name that does not match any tier in the CRM, the row errors out. Create or rename the tier under **CRM → Membership Tiers** before rerunning.

### Columns

Each membership supports: tier, status (active / expired / cancelled), start date, end date, external ID. The contact is inferred from the matched row.

### Duplicate handling

Memberships deduplicate on `(import_source, external_id)` within the member's contact. Re-imports stage updates under the selected strategy.

---

## Invoice Details

The Invoice Details importer brings historical transactions into the CRM grouped by a shared invoice number. Use this when your source system exports per-line-item transactions (one CSV row per line) that should collapse into invoice records when the same invoice number repeats across rows. Access it via **Tools → Importer → Import Invoice Details**.

### Prerequisite — Contacts

Every invoice must resolve to a contact (the bill-to party). Mapping rules mirror the Donations importer.

### Grouping semantics

Rows that share the same invoice number are grouped into a single invoice on commit. Line items remain as transactions under the invoice. The importer detects duplicate invoice numbers across the CSV and warns at Preview if a previously-imported invoice number appears again in the current file.

### Duplicate handling

Invoices deduplicate on `(import_source, invoice_number)`. Re-imports with a matching number stage updates under the selected strategy.

---

## Notes

Structured interactions (calls, meetings, emails, tasks, letters, SMS, plain notes) import through a dedicated workflow because the column semantics — type, subject, status, occurred_at, duration — do not fit the other importers' shapes.

See the full [Import Notes](import-notes) article for details, including when to use the dedicated Notes importer versus the Contacts importer's free-text Notes column.

---

## Sensitive data rejection

Before processing any rows, every importer scans the CSV for data that must not enter the system. If a violation is found, the entire import is **rejected immediately** — no records are created.

The importer checks two things:

**Column headers** — if any column is named after a sensitive field type, the file is rejected before any row is read. Blocked names include: `ssn`, `social_security`, `social_security_number`, `credit_card`, `credit_card_number`, `card_number`, `pan`, `routing_number`, `account_number`, `bank_account`, `aba_routing`, `driver_license`, `drivers_license`, `dl_number`.

**Cell contents** — every cell in every row is scanned for:

- Credit card numbers (13–19 digit sequences that pass the Luhn check, with or without spaces/dashes)
- Social Security Numbers (`###-##-####` format, or any 9-digit sequence)
- ABA bank routing numbers (9-digit numbers whose first two digits indicate a Federal Reserve routing range)

When a file is rejected you will see the message: **"This import was rejected because it contains data that may include sensitive financial or personal identifiers. Remove the flagged data and try again."** A detail line identifies the specific row and column that triggered the rejection.

To fix a rejected import: open the original file, remove or blank out the flagged column(s) or cell(s), save it, and start a new import.

---

## Review Queue

Users with the **Review Imports** permission can access the review queue from **Tools → Importer** to:

- See all pending import sessions with their metadata (source, filename, row count, who imported, date).
- **Preview** the first 20 new records and the first 20 staged updates (showing current field values and proposed changes side-by-side).
- **Approve** an import — makes all new records visible across the system and applies all staged updates to existing records.
- **Roll back** an import — permanently deletes all new records from the import and discards all staged updates. Source ID mappings are preserved for future reference. Cannot be undone.

Once approved, imports cannot be rolled back. The Rollback button is only available on pending and reviewing imports.

Approve, roll back, and delete all operate at the **import session** level — not on individual staged updates. A reviewer with `review_imports` permission can act on the full session and every staged update inside it regardless of who authored them.

## Import History

The **Import History** page lists every past import with its status, row counts, source, uploader, and any errors. Access it from **Tools → Import History** or via the link at the bottom of the Importer landing page. History is read-only — it is the auditable record of what came in, when, and by whom.

## Permissions

- **import_data** — required to access any importer and run imports.
- **review_imports** — required to see the Review Queue, approve imports, and roll back imports.

Either permission grants access to the Importer page. A user can import data without being able to approve it, and a reviewer can approve imports without being able to run them. The `review_imports` permission is a deliberate, narrow grant — it is not assigned to any role by default.
