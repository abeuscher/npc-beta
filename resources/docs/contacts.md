---
title: Contacts
description: How to add, view, edit, and manage individual contacts in the CRM, including custom fields and contact tagging.
version: "0.25"
updated: 2026-03-21
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

## Tags

Tags let you categorize and segment contacts for filtering, mailing lists, and reporting. The Tags field appears in the contact edit form.

- **Selecting existing tags** — click the Tags field and type to search, or scroll the dropdown to browse available contact tags. Click a tag to apply it. Applied tags appear as pills.
- **Removing a tag** — click the × on any pill to remove it.
- **Creating a new tag** — type the label in the **Create tag** field below the selector and click the **+** button. The tag is created immediately and added to this contact.

## Bulk Actions

From the contact list you can select multiple contacts to apply bulk actions such as adding tags or exporting selected records to CSV.

## Source Field

Every contact has a **Source** field that records how the record entered the system:

- **Manual Entry** — created directly through the admin interface
- **Import** — brought in via the Importer tool
- **Web Form** — submitted through a public registration or sign-up form
- **API** — created programmatically via the API

The Source field is set automatically and **cannot be changed by any user through the UI**. It is displayed as read-only information on the contact edit form.

## Importing Contacts

To bring in contacts from a spreadsheet, use **Tools → Importer**. See the Import Contacts help article for details.

---

## Date of Birth and Minor Privacy Laws

The **Date of Birth** field is admin-entered and optional. If a contact's date of birth would make them younger than 13 years old, the field will not accept the date. This is a safeguard related to two laws that impose heightened privacy obligations on personal data collected from children under 13:

**COPPA — Children's Online Privacy Protection Act (US federal)**
Applies when an operator has actual knowledge it holds personal information from a child under 13. Key obligations include: obtaining verifiable parental consent before collecting data, providing parents the right to review and delete their child's record, and not conditioning participation on collecting more information than is reasonably necessary. See the FTC's official COPPA page: https://www.ftc.gov/legal-library/browse/rules/childrens-online-privacy-protection-rule-coppa

**CCPA / CPRA — California Consumer Privacy Act (California)**
Requires opt-in parental consent before selling or sharing personal information of consumers under 13, and opt-in consent from the consumer themselves for ages 13–16. See the California Attorney General's CCPA page: https://oag.ca.gov/privacy/ccpa

If you believe a contact in your system is under 13 and you have collected their personal information, consult legal counsel before taking further action. At minimum, the record should be treated with heightened care: avoid adding it to mailing lists, exporting it to third parties, or sharing it in any form without verifying you have appropriate consent or legal basis to do so.
