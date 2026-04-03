---
title: Web Forms
description: How to create, configure, and embed web forms on public pages. Covers field types, validation, contact field mapping, honeypot spam protection, and viewing submissions.
version: "0.48"
updated: 2026-04-01
tags: [cms, forms, contact, submissions]
routes:
  - filament.admin.resources.forms.index
  - filament.admin.resources.forms.create
  - filament.admin.resources.forms.edit
category: cms
---

# Web Forms

Web forms allow you to collect information from visitors on your public website. Forms are defined here in the admin and embedded on any page using a simple Blade component. Submissions are stored and viewable in the admin.

## Creating a Form

Go to **CMS › Forms** and click **New Form**. Give your form a title and a handle. The handle is used to embed the form (e.g. `<x-public-form handle="contact-signup">`). It must be unique and contain only lowercase letters, numbers, hyphens, and underscores.

### Form Settings

| Setting | Description |
|---|---|
| **Form type** | `General` or `Contact sign-up`. Contact forms drive contact creation in the CRM (session 048 feature). |
| **Submit button label** | Text shown on the submit button. Defaults to "Submit". |
| **Success message** | Text shown to the visitor after a successful submission. |
| **Honeypot spam protection** | Enabled by default. Silently discards bot submissions that fill a hidden field. |

## Building Fields

Each form has a **Fields** repeater. Click **Add field** to add a new field. Fields are rendered in the order they appear.

### Field properties

| Property | Description |
|---|---|
| **Type** | See field types below. |
| **Label** | Visible label shown above the input. |
| **Handle** | The internal key used in the submission data. Auto-populated from the label; must be unique within the form. |
| **Placeholder** | Hint text inside the input (not applicable to checkbox, radio, select, state, country, or hidden). |
| **Default value** | For `hidden` fields only — the value written to the submission silently. |
| **Width** | Number of grid columns (1–12) this field occupies. 12 = full width, 6 = half. On mobile all fields expand to full width. |
| **Required** | Whether the field must be filled before submission. |
| **Validation** | See validation presets below. |
| **Maps to contact field** | Links this form field to a contact record column. Used when form type is Contact sign-up. |

### Field types

| Type | Use for |
|---|---|
| `Text` | Short free-text input |
| `Email` | Email address (browser validates format) |
| `Phone` | Telephone number |
| `Number` | Numeric input |
| `Textarea` | Multi-line text |
| `Select` | Dropdown — add options in the Options repeater |
| `Radio` | Radio button group — add options in the Options repeater |
| `Checkbox` | Single checkbox (yes/no, opt-in) |
| `US State` | Pre-populated dropdown of all 50 US states + DC |
| `Country` | Pre-populated dropdown — US, CA, GB, AU at the top, then full alphabetical list |
| `Hidden` | Not shown to the visitor — value from Default value is written silently to the submission |

### Validation presets

| Preset | What it checks |
|---|---|
| None | No additional validation beyond required/optional |
| Email address | Must be a valid email format |
| Phone number | 7–15 digits, spaces, hyphens, parentheses, + allowed |
| ZIP code | US 5-digit or ZIP+4 format |
| URL | Must be a valid URL |
| Numbers only | Digits only |
| Letters only | Letters and spaces only |
| Custom regex | Enter your own regex pattern and optional error message |

## Contact Field Mapping

When building a Contact sign-up form, map each field to the corresponding contact record column using the **Maps to contact field** dropdown. Available mappings include all standard contact columns (`first_name`, `last_name`, `email`, `phone`, `address_line_1`, `address_line_2`, `city`, `state`, `postal_code`, `country`, `mailing_list_opt_in`) plus any custom contact fields defined in **Custom Fields**.

A Contact form should always include fields mapped to `first_name`, `last_name`, and `email` at minimum.

## Embedding a Form on a Page

Add the Blade component anywhere in a page template or content block:

```html
<x-public-form handle="contact-signup" />
```

Replace `contact-signup` with your form's handle. If the form is inactive or the handle does not exist, the component renders nothing.

After a successful submission the form is replaced by the success message from the form settings. Validation errors are shown inline beneath each field.

## Downloading and Importing JSON

On the Forms list, click **Download JSON** next to any form to export the full field and settings definition as a `.json` file. Developers can modify field order, widths, and contact field mappings in the JSON and re-create the form using the admin UI.

The exported JSON structure:

```json
{
  "title": "Contact Sign-Up",
  "handle": "contact-signup",
  "fields": [ ... ],
  "settings": { ... }
}
```

## Viewing Submissions

Open any form in the admin and scroll to the **Submissions** tab. Each row shows the submission date and IP address. Click **View** to see all submitted field values in a modal. Rows can be deleted individually (for GDPR removal) by users with the `delete_form_submission` permission.

## Spam Protection

**Honeypot** — enabled by default. A hidden text field is rendered in the public form but hidden from real users via CSS. Bots that fill it in are silently discarded — the bot receives a success response so it does not retry.

**Rate limiting** — the submission endpoint is limited to 10 submissions per minute per IP address.

**PII screening** — all submitted values are scanned for credit card numbers, Social Security Numbers, and ABA routing numbers before being saved. If detected, the submission is rejected with a generic error message.

## Deleted Records and Trash

When you delete a form, the record is soft-deleted — it is hidden from normal views but kept in the database so it can be restored if needed. Deleted forms return a 404 if someone tries to submit them. Submissions on a deleted form are preserved and restored along with the form.

### Viewing trashed records

Use the **Trashed** filter above the table to control which records appear:

- **Without trashed** (default) — only active records are shown.
- **With trashed** — active and deleted records are shown together. Deleted records can be identified by the Restore action in their row.
- **Only trashed** — only deleted records are shown.

The same Trashed filter is available on the Submissions tab within each form.

### Restoring a deleted record

Find the record using the Trashed filter set to **With trashed** or **Only trashed**, then click **Restore** in the row actions. The record is immediately returned to active status.

### Permanently deleting (purge)

Force-delete permanently removes a record from the database. This action is restricted to super-admin users and cannot be undone. Force-delete appears as an action on trashed records only when you are logged in as a super-admin. Force-deleting a form will also permanently destroy all of its submissions.
