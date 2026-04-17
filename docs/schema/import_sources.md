## import_sources

Named external systems that imports originate from (e.g. "Old CRM", "Wild Apricot"). Doubles as a mapping preset: a source carries the last-saved column map, custom-field map, and match key so re-imports start pre-configured. Presets are scoped per content type — contacts and events have independent map columns on the same row.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| name | string | no | |
| notes | text | yes | |
| field_map | jsonb | no | default: `{}`; keys = lowercased/trimmed source column headers, values = Contact field keys (e.g. `"first name" => "first_name"`). Populated on "Save mapping" after a successful contact commit. |
| custom_field_map | jsonb | no | default: `{}`; keys = lowercased/trimmed source column headers, values = `{handle, label, field_type}` for columns the user mapped as contact custom fields. |
| match_key | string | yes | Field key used to match existing contacts on re-import (e.g. `email`, `external_id`, a custom field handle). Null until the user saves a contact mapping. |
| match_key_column | string | yes | Original CSV column header mapped to `match_key`. Kept for display; the runtime derives the field from `match_key`. |
| events_field_map | jsonb | no | default: `{}`; events-scoped equivalent of `field_map`. Keys = lowercased/trimmed source column headers, values = Event/Registration/Transaction field keys (prefixed, e.g. `"event title" => "event:title"`). |
| events_custom_field_map | jsonb | no | default: `{}`; events-scoped equivalent of `custom_field_map`. Keys = lowercased/trimmed source column headers, values = `{handle, label, field_type, target}` (target is `event` or `registration`). |
| events_match_key | string | yes | Event match key (typically `event:external_id`). Null until the user saves an events mapping. |
| events_match_key_column | string | yes | Original CSV column header for `events_match_key`. |
| events_contact_match_key | string | yes | Contact match key for the events importer's contact-lookup step (e.g. `email`, `external_id`, a custom field handle). |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |

Seeded built-in sources: `Generic CSV`, `Wild Apricot`, `Bloomerang`. Admins can clone or edit these.
