## events

Events with dates, registration, and venue information.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| title | string | no | |
| slug | string | no | unique |
| author_id | bigint unsigned | no | FK → users.id, restrictOnDelete |
| description | text | yes | |
| status | enum | no | default: 'draft'; values: draft, published, cancelled |
| published_at | timestamp | yes | Set by `EventObserver` on first transition to `status = 'published'` if currently null. Never overrides admin-set values. |
| address_line_1 | string | yes | |
| address_line_2 | string | yes | |
| city | string(100) | yes | |
| state | string(100) | yes | |
| zip | string(20) | yes | |
| map_url | string(2048) | yes | |
| map_label | string | yes | |
| meeting_url | string(2048) | yes | |
| meeting_label | string | yes | |
| meeting_details | text | yes | |
| starts_at | timestamp | no | |
| ends_at | timestamp | yes | |
| price | decimal(8,2) | no | default: 0 |
| capacity | unsignedInteger | yes | |
| registration_mode | string | no | default: 'open' |
| external_registration_url | string | yes | |
| auto_create_contacts | boolean | no | default: true |
| mailing_list_opt_in_enabled | boolean | no | default: false |
| landing_page_id | uuid | yes | FK→pages, nullOnDelete; system-managed by EventObserver |
| registrants_deleted_at | timestamp | yes | Set when staff runs Delete Registrant Contacts action |
| custom_fields | jsonb | yes | |
| import_session_id | uuid | yes | FK→import_sessions, nullOnDelete. Set for events created by the events importer so rollback can cascade correctly. |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
