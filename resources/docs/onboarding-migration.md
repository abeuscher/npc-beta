---
title: Onboarding — Migration
description: Walkthrough for moving an organization's existing records into the CRM — what to expect, the order to run imports in, edge cases to watch for, and a checklist for a first migration.
standalone: true
version: "0.1"
updated: 2026-05-04
tags: [onboarding, import, migration, csv, checklist]
category: tools
---

# Onboarding — Migration

This guide walks through moving an organization's existing records into the CRM. It's written for the person doing the migration — usually an administrator with their data already exported as spreadsheets — and assumes you know your records but not the inside of the system.

If you are looking at the **Importer** page and wondering where to start, this is the right place. The [Data Imports](import-contacts) reference covers each importer's screens in detail; this guide covers the *whole flow* and the things that catch people out.

---

## What to expect

For an organization in the **5,000–10,000 contact** range with reasonably clean spreadsheet exports, a single administrator can usually:

- Import contacts, organizations, events, donations, memberships, invoice details, and notes in a single afternoon.
- Spot-check the imported records against the original spreadsheets.
- Hand the system over for the first customer-visible activity (a thank-you email, a donation form, a membership renewal) within a week.

The migration is most predictable when your spreadsheets come from a single source system with consistent column headings. Mixed sources — a contacts list from one system, a donations list from another — work too, but require more care at the column-mapping step.

---

## The big picture

The migration tools live under **Tools → Importer**. Each kind of record has its own importer (Contacts, Organizations, Events, and so on), and they all share the same four-step shape:

1. **Upload** — choose a source name and pick a CSV file.
2. **Review Data** — the system checks for duplicate column headings and shows sample rows so you can confirm the file looks right.
3. **Map Columns** — connect each spreadsheet column to a destination field in the CRM.
4. **Preview & Confirm** — see the first few rows mapped to their destinations.

After confirming, the import **stages** the rows in a pending state. Nothing is visible in the system at large until a reviewer **approves** the import session on the Importer index page. Approval is one click. Mistakes can be rolled back any time before approval (and after, until follow-up imports start to depend on the rows).

This staged-then-approved shape is on purpose. It's the reason mistakes don't reach the rest of the system before someone has looked at them.

---

## Run the imports in this order

Each importer can create the contacts it references if needed, but running the dependency root first keeps things predictable.

1. **Contacts** — every other importer matches against these.
2. **Organizations** — sponsors, employers, donor entities. The Contacts importer can also create organizations as it goes via a column-level setting.
3. **Events** — the event calendar's content. Each event can optionally reference a sponsor organization.
4. **Donations** — gift records. Choose **Auto-create missing contacts** for the friendliest behavior; the preview shows which would be created so you can catch typos.
5. **Memberships** — membership lifecycle records. Same auto-create option.
6. **Invoice Details** — line-item financial detail.
7. **Notes** — contact-keyed history (calls, meetings, follow-ups). Notes is the only importer that **errors** on a missing contact rather than skipping the row, so run it after every other importer is done — that way the contacts it references already exist.

For each importer:

- On **Upload**, decide whether to turn on *Create custom fields for unrecognized columns*. Turn it **on** if you want every column from the spreadsheet preserved as-is. Turn it **off** if you want a tighter match and are willing to map columns manually.
- On **Map Columns**, every column gets a destination or is set to *ignore*. A red ✗ means a column hasn't been resolved yet; a green ✓ means it has. The match key (the field used to recognize duplicate rows on re-import) is set here too.
- On **Preview & Confirm**, the Contacts importer shows a per-row duplicate check against existing records — a useful last sanity stop.
- On **Stage Import**, the rows process in chunks and the page shows live counts: Imported / Staged / Skipped / Errors.
- On the **Importer** index page, click **Approve** on the row's actions menu. The records become visible in the system at large.

---

## Migration out

The Contacts list page has an **Export CSV** action under the ⋮ More actions menu — useful as a sanity check after a migration in.

Pulling all of an organization's records back out of the system in preparation for switching providers is a separate workflow with its own considerations (how to keep settings in payment providers and email tools, how to make a smooth handoff of financial records between systems, what to keep and what to retire). That guidance lives in a separate **Offboarding** doc — not yet published.

---

## Custom fields

Custom fields let you preserve organization-specific information that doesn't fit the CRM's standard fields. Six types are supported: **text, number, date, boolean (yes/no), select (a fixed list of choices), and rich text** (with formatting).

Custom fields can be created three ways:

1. **Manually**, in CRM → Custom Fields, before you import.
2. **Automatically by the importer**, when *Create custom fields for unrecognized columns* is on. Fields default to text type; you can change the type on the column row before staging if you'd rather they be number, date, etc.
3. **Per-column at import time**, by picking *Create as custom field* on a column's destination dropdown.

Rich-text values keep their formatting through the round-trip; HTML in your spreadsheet survives intact.

**Custom fields with a list of choices pulled from another collection or model** (a "lookup" field type) is not currently supported. The workaround for now is a *select* field with a static list of choices, updated when the source list changes. Lookup-style custom fields are on the post-launch list if there is demand for them.

---

## Approval and visibility

The importer holds imported records in a staging area until a reviewer accepts them. Before approval, imported records aren't visible to administrators who don't have reviewing rights — they don't appear in the Contacts list, in search, or in mailing-list selections. After approval they appear everywhere a contact normally would.

This is intentional. It prevents an unreviewed import from leaking into a thank-you-email queue or a renewal mailing before someone has confirmed the data.

Operationally:

- Run the import as a user with reviewing rights, or
- Run the import as anyone, and have a reviewer approve the import session afterward on the Importer index page.

When a customer asks "where did my contacts go?" five minutes after import, the answer is almost always *the import is staged but not yet approved* — show them the Importer index page.

---

## Edge cases to watch for

These come up often enough that they're worth knowing in advance.

### Pre-clean the spreadsheet

The importer is forgiving about messy input — it will accept rows with leading or trailing whitespace, mixed-case status values, and odd characters. That's a feature for keeping imports moving, but it also means messy input lands as messy data. A quick spreadsheet pass before upload — trim whitespace, lowercase status fields, sanity-check email addresses — pays for itself in cleaner records.

### Event status values must be lowercase

Event status fields need to match a fixed set of values exactly (`draft`, `published`, `cancelled`). A column with values like `Draft` or `DRAFT` will cause those rows to fail on import. Lowercase the column in the spreadsheet before upload.

### Some column names won't auto-recognize

The system recognizes most common column-naming patterns out of the box — entity-prefixed forms like *Event Title* or *Registration Ticket Type*, separator variants like *postal_code* / *postalcode* / *Postal Code*, and many natural alternatives (*Mobile Phone*, *Date of Birth*, *Salutation*). What it usually won't recognize without help is your organization's own custom-field names — *Volunteer Status*, *Dietary Pref*, *Anonymous*, *PO Number*, and so on. If a column lands on *— ignore —* on the Map Columns step, pick the matching destination from the dropdown, or turn on *Create custom fields for unrecognized columns* on the Upload step to have those preserved as custom fields automatically. Either way, the mapping is saved with the source, so the next import from the same source will remember it.

### Cross-importer references depend on matching emails

Donations, memberships, registrations, and notes reference contacts by email (or external ID, if you map that column). If your contacts spreadsheet has a different email for someone than your donations spreadsheet does, they won't match — the donation will either auto-create a new contact (the friendly default) or skip the row, depending on your setting. Notes is stricter: it will report an error for a missing contact rather than create one.

To avoid surprises, run **Contacts first**, run **Notes last**, and review the *Skipped* count on the donations / memberships / events imports. A high skipped count usually points to mismatched emails between sources.

### Source choice matters

The source you select on the Upload step is how the system recognizes "the same data, re-imported." If you re-import from the same source, rows are matched against existing records and your duplicate-handling choice (Skip / Update / Create-anyway) takes over. If you re-import the same data under a *different* source name, the system will treat them as separate records. Choose deliberately.

### Re-importing to fill in blanks

If you import a partial spreadsheet now (say, just contacts) and want to update those records later with more detail (say, adding phone numbers from a different export), use the same source and choose **Update** as the duplicate strategy on the second import. The Update strategy is *fill blanks only* — it adds values to fields that are empty without overwriting fields that already have values. It's the safest re-import strategy for additive enrichment.

---

## First-migration checklist

Before pulling the trigger:

1. **Confirm the source.** Where are the spreadsheets coming from? Are the column headings consistent across the contacts / donations / events / memberships exports?
2. **Pre-clean each spreadsheet.** Trim whitespace, lowercase event status values, sanity-check emails, remove any control characters or unusual encoding.
3. **Skim the column headings.** Anything that looks generic ("Notes," "Status," "Type") is worth a closer look at the Map Columns step — those names can map to several different destinations.
4. **Decide on automatic custom-field creation per importer.** On if you want every column preserved; off for a tighter schema.
5. **Run Contacts first. Run Notes last.**
6. **Approve sessions deliberately.** Roll back any session before approval if it doesn't look right.
7. **Spot-check after approval.** Pick five rows from the start, middle, and end of the source spreadsheet and verify the imported records match.
8. **Run a Contacts export as a sanity pass.** The exported row count should equal what you imported plus what was already there.
9. **Tell the user about the approval gate.** Imported records aren't visible until a reviewer approves the session — this is the most common source of surprise.

---

## Reading guide

If you read only one section: the **First-migration checklist**.

If you are about to start the first import: the **Run the imports in this order** section + the **Edge cases to watch for**.

If something has gone unexpectedly wrong: the **Edge cases to watch for** + the **Approval and visibility** sections.

If you need detail on a specific importer's screens: the [Data Imports](import-contacts) reference.

---

## Appendix — Blank CSV templates

Each content type has a blank CSV template with the expected column headers. Use these as the starting point if you're building an export from a system without a built-in matching format, or to confirm what columns the importer expects.

The templates live on the **Importer** index page (Tools → Importer), in a section near the bottom titled **CSV Templates**. Click the button for the content type you want to download a blank file with the right column headers.

Available templates:

- **Contacts** — people, the primary record family.
- **Organizations** — organizations linked to contacts (sponsors, employers, donor entities).
- **Events** — events plus per-row registration data and optional linked transactions.
- **Donations** — gift records plus matching transaction rows.
- **Memberships** — membership records with tier, status, and lifecycle dates.
- **Invoice Details** — historical line-item financial detail, grouped into invoices by a shared invoice number.
- **Notes** — structured interactions (calls, meetings, emails, tasks).

Each template carries the column headers the importer recognizes most cleanly. You can add additional columns to your CSV beyond the template's defaults — those become custom fields on import (with the *Create custom fields for unrecognized columns* setting on) or you can map them manually at the Map Columns step.
