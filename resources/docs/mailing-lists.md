---
title: Mailing Lists
description: How to create and manage dynamic audience lists using filter rules against the contacts database.
version: "0.36"
updated: 2026-03-18
tags: [mailing-lists, crm, export, advanced-filters]
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

## CAN-SPAM / anti-spam compliance

Mailing lists produce contact sets only — they do not send messages. Any bulk communication sent via these lists (e.g. through MailChimp) must comply with applicable laws including CAN-SPAM, CASL, and GDPR as relevant to your organisation's jurisdiction and audience. Compliance obligations — including unsubscribe handling, physical address disclosure, and consent management — are the responsibility of the downstream sending integration and the organisation using it.
