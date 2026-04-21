---
title: Import Notes
description: How to import notes and interactions (calls, meetings, emails, tasks) from a separate activity CSV. One row per note. Contacts must already exist; rows whose contact cannot be matched are reported as errors.
version: "0.45"
updated: 2026-04-20
tags: [import, notes, interactions, csv, crm]
routes:
  - filament.admin.pages.import-notes-page
  - filament.admin.pages.import-notes-progress-page
category: tools
---

# Import Notes

The Notes importer brings structured interactions — calls, meetings, emails, tasks, letters, SMS, and plain notes — into the CRM from a separate activities CSV. One row per note. Access it via **Tools → Importer**, then click **Import Notes**.

## When to use this vs the contacts importer's Notes column

- **Use this importer** when you have a dedicated activities file exported from your prior CRM (Raiser's Edge Actions, Salesforce Task/Event, CiviCRM Activities, Bloomerang Activities). Each row represents one interaction with one contact.
- **Use the contacts importer's Notes column** when your CSV is a contacts export with a single free-text "notes" column that holds the contact's history. That path creates one note per row of the contacts file, attached to the imported contact.

## Contacts must be imported first

The Notes importer does **not** create contacts. Every row must resolve to an existing contact via the configured match column (Email, External ID, or Phone). Rows whose contact cannot be found land in the error table with a specific message — run the contacts importer first, then rerun the notes import.

## Structured columns

Each note supports these columns:

- **Type** — free text. Canonical values (`call`, `meeting`, `email`, `note`, `task`, `letter`, `sms`) render with icons in the Timeline; anything else is preserved verbatim.
- **Subject** — short title.
- **Status** — free text. Canonical: `completed`, `scheduled`, `cancelled`, `left_message`, `no_show`.
- **Body** — rich text. Plain text imports as-is; pre-formatted HTML is passed through unchanged.
- **Occurred At** — the date/time the interaction happened.
- **Follow-up At** — an optional "next action" date.
- **Outcome** — a short result summary, typical for calls / meetings.
- **Duration (minutes)** — integer. Accepts `30`, `"30 min"`, `"00:30:00"`, `"45:00"`. Free-text durations are stored as null.
- **External ID** — the source system's ID for the activity, used for dedupe on re-import.

## Unmapped columns

Columns you map to **Store in `meta`** are captured verbatim under `notes.meta` and surfaced on the Contact Timeline via the `Source fields` disclosure. No separate custom-field surface is created for notes — `meta` is schemaless by design.

## Duplicate handling

On re-import, rows whose `(import_source_id, External ID)` matches an existing note are resolved by the duplicate strategy you choose in the Map Columns step:

- **Skip** — leave the existing note unchanged.
- **Stage updates** — stage non-blank imported values as an update; applied on reviewer approval.
- **Create a new note anyway** — ignore the match and create a new note.
