## import_sources

Named external systems that imports originate from (e.g. "Old CRM", "Wild Apricot"). Doubles as a mapping preset: a source carries the last-saved column map, custom-field map, and match key so re-imports start pre-configured. Presets are scoped per content type — contacts and events have independent map columns on the same row.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| name | string | no | |
| notes | text | yes | |
| contacts_field_map | jsonb | no | default: `{}`; keys = lowercased/trimmed source column headers, values = Contact field keys (e.g. `"first name" => "first_name"`). Populated on "Save mapping" after a successful contact commit. |
| contacts_custom_field_map | jsonb | no | default: `{}`; keys = lowercased/trimmed source column headers, values = `{handle, label, field_type}` for columns the user mapped as contact custom fields. |
| contacts_match_key | string | yes | Field key used to match existing contacts on re-import (e.g. `email`, `external_id`, a custom field handle). Null until the user saves a contact mapping. |
| contacts_match_key_column | string | yes | Original CSV column header mapped to `contacts_match_key`. Kept for display; the runtime derives the field from `contacts_match_key`. |
| events_field_map | jsonb | no | default: `{}`; events-scoped equivalent of `field_map`. Keys = lowercased/trimmed source column headers, values = Event/Registration/Transaction field keys (prefixed, e.g. `"event title" => "event:title"`). |
| events_custom_field_map | jsonb | no | default: `{}`; events-scoped equivalent of `custom_field_map`. Keys = lowercased/trimmed source column headers, values = `{handle, label, field_type, target}` (target is `event` or `registration`). |
| events_match_key | string | yes | Event match key (typically `event:external_id`). Null until the user saves an events mapping. |
| events_match_key_column | string | yes | Original CSV column header for `events_match_key`. |
| events_contact_match_key | string | yes | Contact match key for the events importer's contact-lookup step (e.g. `email`, `external_id`, a custom field handle). |
| donations_field_map | jsonb | no | default: `{}`; donations-scoped equivalent of `field_map`. |
| donations_custom_field_map | jsonb | no | default: `{}`; donations-scoped equivalent of `custom_field_map`. |
| donations_contact_match_key | string | yes | Contact match key for the donations importer. |
| memberships_field_map | jsonb | no | default: `{}`; memberships-scoped equivalent of `field_map`. |
| memberships_custom_field_map | jsonb | no | default: `{}`; memberships-scoped equivalent of `custom_field_map`. |
| memberships_contact_match_key | string | yes | Contact match key for the memberships importer. |
| invoices_field_map | jsonb | no | default: `{}`; invoices-scoped equivalent of `field_map`. |
| invoices_custom_field_map | jsonb | no | default: `{}`; invoices-scoped equivalent of `custom_field_map`. |
| invoices_contact_match_key | string | yes | Contact match key for the invoice details importer. |
| notes_field_map | jsonb | no | default: `{}`; notes-scoped equivalent of `field_map`. |
| notes_custom_field_map | jsonb | no | default: `{}`; notes-scoped equivalent of `custom_field_map`. Values are `{handle, label, field_type}` but there is no CustomFieldDef surface — these columns are written into `notes.meta` at import time. |
| notes_contact_match_key | string | yes | Contact match key for the notes importer. |
| organizations_field_map | jsonb | yes | organizations-scoped equivalent of `field_map`. Keys = lowercased/trimmed source column headers, values = Organization field keys (prefixed `organization:*`). |
| organizations_custom_field_map | jsonb | yes | organizations-scoped equivalent of `custom_field_map`. Values are `{handle, label, field_type}` for columns mapped via the `__custom_organization__` sentinel. |
| organizations_match_key | string | yes | Match key used to dedupe orgs on re-import (one of `organization:name`, `organization:email`, `organization:external_id`). Default at runtime is `organization:name`. |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |

Seeded built-in sources: `Generic CSV`, `Wild Apricot`, `Bloomerang`. Admins can clone or edit these.
