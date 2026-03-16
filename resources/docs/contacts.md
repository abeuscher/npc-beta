---
title: Contacts
description: How to add, view, edit, and manage individual contacts in the CRM, including custom fields and contact tagging.
version: "0.24"
updated: 2026-03-16
tags: [contacts, crm, custom-fields, tags]
routes:
  - filament.admin.resources.contacts.index
  - filament.admin.resources.contacts.create
  - filament.admin.resources.contacts.edit
---

# Contacts

Contacts are the core record in the CRM. Each contact represents an individual person — a donor, volunteer, member, or anyone your organization has a relationship with.

## Contact List

The Contacts index shows all contacts in the system. You can search by name or email, filter by tag, and sort by any column. Use the **New Contact** button in the top right to add a contact manually.

## Adding a Contact

When creating a contact, the following fields are available:

- **Name** (required) — first and last name.
- **Email** — primary email address. Must be unique if provided.
- **Phone** — primary phone number.
- **Address** — mailing address fields.
- **Household / Organization** — optionally link this contact to a household or organization record.
- **Tags** — apply one or more CRM tags to categorize the contact.
- **Custom fields** — any custom fields defined under Tools → Custom Fields will appear here.

## Editing a Contact

Click any contact row to open the detail view, then click **Edit** to modify the record. All fields are editable. Changes are saved immediately on submit.

## Bulk Actions

From the contact list you can select multiple contacts to apply bulk actions such as adding tags or exporting selected records to CSV.

## Importing Contacts

To bring in contacts from a spreadsheet, use **Tools → Import Contacts**. See the Import Contacts help article for details.
