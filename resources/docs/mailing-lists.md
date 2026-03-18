---
title: Mailing Lists
description: How to create and manage dynamic audience lists using filter rules against the contacts database.
version: "0.37"
updated: 2026-03-18
tags: [mailing-lists, crm, export, advanced-filters, mailchimp]
routes:
  - filament.admin.resources.mailing-lists.index
  - filament.admin.resources.mailing-lists.create
  - filament.admin.resources.mailing-lists.edit
---

# Mailing Lists

Mailing lists are **dynamic audience definitions** — not static rosters. Each list stores filter rules, and the matching contacts are evaluated fresh every time the list is used. A contact added today will appear on a matching list immediately; a contact that no longer meets the criteria will drop off automatically.

Lists do not send email. They produce filtered sets of contacts for use by downstream integrations such as MailChimp.

---

## Creating a List

Go to **CRM → Mailing Lists** and click **New Mailing List**. Give the list a name, choose a conjunction (see below), and add one or more filter rules in the Simple Filters section.

---

## Simple Filters

The Simple Filters section lets you build a list using a repeater UI. Each row specifies a **field**, an **operator**, and an optional **value**.

### Field reference

| Field | Description |
|-------|-------------|
| `first_name` | Contact's first name |
| `last_name` | Contact's last name |
| `email` | Primary email address |
| `phone` | Primary phone number |
| `city` | City |
| `state` | State / province |
| `postal_code` | Postal / ZIP code |
| `mailing_list_opt_in` | Opted in to mailing list (boolean — use `equals` with value `1` for yes, `0` for no) |
| `tags` | CRM tags attached to the contact — use `includes tag` / `excludes tag` operators |

### Operator reference

| Operator | Behaviour |
|----------|-----------|
| `equals` | Exact match |
| `not equals` | Does not equal |
| `contains` | Case-insensitive substring match |
| `does not contain` | Case-insensitive substring does not match |
| `includes tag` | Contact has a tag with this name |
| `excludes tag` | Contact does not have a tag with this name |
| `is empty` | Field is null or blank |
| `is not empty` | Field has any value |

### AND / OR conjunction

The **Match** selector on the list controls how multiple filter rules are combined:

- **ALL rules must match (AND)** — a contact must satisfy every rule to be included.
- **ANY rule must match (OR)** — a contact is included if it satisfies at least one rule.

The conjunction applies to the entire set of simple filters. For more complex logic, use Advanced mode.

---

## Advanced Filter

> **This feature requires the `use_advanced_list_filters` permission.** It is off by default for all roles and must be explicitly granted by a super admin via Settings → Roles.

The Advanced Filter section exposes a raw PostgreSQL `WHERE` clause textarea. When a WHERE clause is present, the Simple Filters section is ignored — the two modes are mutually exclusive.

Queries run against a dedicated read-only database connection and are subject to a **5-second statement timeout**. If the query exceeds this limit, an error is shown and no results are returned.

### Contacts table schema

The WHERE clause is applied directly to the `contacts` table. Available columns:

| Column | Type | Notes |
|--------|------|-------|
| `id` | uuid | Primary key |
| `first_name` | varchar | |
| `last_name` | varchar | |
| `preferred_name` | varchar | |
| `prefix` | varchar | |
| `email` | varchar | |
| `email_secondary` | varchar | |
| `phone` | varchar | |
| `phone_secondary` | varchar | |
| `address_line_1` | varchar | |
| `address_line_2` | varchar | |
| `city` | varchar | |
| `state` | varchar | |
| `postal_code` | varchar | |
| `country` | varchar | |
| `notes` | text | |
| `mailing_list_opt_in` | boolean | |
| `is_deceased` | boolean | |
| `do_not_contact` | boolean | |
| `source` | varchar | `manual`, `import`, `form`, `api` |
| `custom_fields` | jsonb | See below |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |
| `deleted_at` | timestamp | Soft-deleted records — non-null rows are excluded automatically |

### Custom fields in the WHERE clause

Custom fields are stored as a JSON object in the `custom_fields` column. To filter on a custom field, use the PostgreSQL `->>` operator with the field's handle (visible in Tools → Custom Fields):

```sql
custom_fields->>'field_handle' = 'some value'
```

For numeric comparison, cast the value:

```sql
(custom_fields->>'age')::integer >= 18
```

### Example WHERE clauses

**Contacts in Boston who have opted in:**
```sql
city ILIKE 'Boston' AND mailing_list_opt_in = true
```

**Contacts with a custom field "membership_tier" set to "gold" or "platinum":**
```sql
custom_fields->>'membership_tier' IN ('gold', 'platinum')
```

**Contacts added in the past 90 days who are not deceased:**
```sql
created_at >= NOW() - INTERVAL '90 days' AND is_deceased = false
```

**Contacts whose email domain is a specific organisation:**
```sql
email ILIKE '%@example.org'
```

### Prohibited keywords

To prevent accidental data modification, the following keywords are rejected and will produce an error if found in the WHERE clause (case-insensitive):

`DROP`, `DELETE`, `UPDATE`, `INSERT`, `TRUNCATE`, `ALTER`, `CREATE`, `GRANT`, `REVOKE`, `EXECUTE`, `CALL`, `--`, `/*`

---

## Contactability filter

Every list query — whether used for the contact count shown in the form, CSV export, or MailChimp sync — automatically excludes contacts who are not contactable. Specifically, any contact where **Do Not Contact** is checked or **Mailing List Opt-In** is unchecked will never appear in a list result, regardless of what the filter rules say.

This is applied universally and cannot be overridden per list. It ensures that unsubscribed or suppressed contacts are never accidentally pushed to MailChimp or included in an export.

---

## Exporting a List

Open a mailing list and click **Export CSV** in the top-right. The export includes: first name, last name, email, phone, city, state, postal code, and tags (comma-joined). The filename includes the list name and today's date.

---

## Setup note — read-only database user

When advanced filters are used, queries run through a dedicated `pgsql_readonly` connection. This connection is intentionally scoped to the `contacts` table only — it cannot read CMS pages, financial records, or any other table.

If `DB_READONLY_USERNAME` and `DB_READONLY_PASSWORD` are not set in your `.env`, the main database credentials are used as a fallback. Advanced filters will still work, but without the extra isolation layer. **For production deployments, creating the read-only user is strongly recommended.**

To create the read-only user, run the following SQL as a PostgreSQL superuser:

```sql
CREATE USER nonprofitcrm_readonly WITH PASSWORD 'choose-a-strong-password';
GRANT CONNECT ON DATABASE nonprofitcrm TO nonprofitcrm_readonly;
GRANT USAGE ON SCHEMA public TO nonprofitcrm_readonly;
GRANT SELECT ON contacts TO nonprofitcrm_readonly;
```

Then add to your `.env`:

```
DB_READONLY_USERNAME=nonprofitcrm_readonly
DB_READONLY_PASSWORD=choose-a-strong-password
```

---

## MailChimp Integration

The app can push any mailing list to a MailChimp audience. Each contact is upserted as an audience member (FNAME and LNAME merge fields), and the list's name is applied as a tag. Sync is manual and one-directional — from the CRM to MailChimp — except for the unsubscribe webhook described below.

### Setup

Add the following to your `.env`:

```
MAILCHIMP_API_KEY=        # Your MailChimp API key
MAILCHIMP_SERVER_PREFIX=  # Data centre prefix from the API key, e.g. us14
MAILCHIMP_AUDIENCE_ID=    # The audience (list) ID from MailChimp dashboard
MAILCHIMP_WEBHOOK_SECRET= # A random string you choose; used to verify incoming webhooks
MAILCHIMP_WEBHOOK_PATH=   # The URL path segment after /webhooks/ (default: mailchimp)
                          # Set this to a random hash for security, e.g. a8f3c1d9e2b7
```

- **API key**: Found in MailChimp under **Account → Extras → API Keys**.
- **Server prefix**: The prefix shown at the end of your API key after the dash (e.g. `us14`), or visible in your MailChimp dashboard URL.
- **Audience ID**: Found in MailChimp under **Audience → All contacts → Settings → Audience name and defaults**.

### Syncing a list

Open a mailing list and click **Sync to MailChimp** in the top-right. Confirm the modal. The sync is submitted as a MailChimp background batch job — contacts will appear in MailChimp within a minute or two for most list sizes. The button is only shown when MailChimp credentials are configured in `.env`.

Only contactable members are pushed (see [Contactability filter](#contactability-filter) above). Contacts without an email address are silently skipped.

### Tag strategy

Rather than using separate MailChimp audiences per list, each list maps to a **tag** on members within a single shared audience. The tag name equals the list name. When you sync a list, all its members receive that tag. If a contact belongs to multiple lists, they will accumulate multiple tags. Tags can be used in MailChimp to target segments for campaigns.

### Unsubscribe webhook

When a contact unsubscribes via MailChimp, the webhook writes `mailing_list_opt_in = false` back to their contact record in the CRM. This ensures the contactability filter will exclude them from all future syncs and exports.

**Registering the webhook URL:**

1. In MailChimp, go to **Audience → Manage Audience → Settings → Webhooks**.
2. Click **Create New Webhook**.
3. Set the callback URL to:
   ```
   https://yourdomain.com/webhooks/{MAILCHIMP_WEBHOOK_PATH}?secret={MAILCHIMP_WEBHOOK_SECRET}
   ```
   Replace `{MAILCHIMP_WEBHOOK_PATH}` and `{MAILCHIMP_WEBHOOK_SECRET}` with the values from your `.env`.
4. Under **Events to send**, enable **Unsubscribes** only.
5. Save. MailChimp will send a verification GET request to confirm the URL is reachable.

> The `?secret=` query parameter is how the CRM verifies that incoming webhook requests genuinely come from MailChimp. Keep this value private.

---

## CAN-SPAM / anti-spam compliance

Mailing lists produce contact sets only — they do not send messages. Any bulk communication sent via these lists (e.g. through MailChimp) must comply with applicable laws including CAN-SPAM, CASL, and GDPR as relevant to your organisation's jurisdiction and audience. Compliance obligations — including unsubscribe handling, physical address disclosure, and consent management — are the responsibility of the downstream sending integration and the organisation using it.
