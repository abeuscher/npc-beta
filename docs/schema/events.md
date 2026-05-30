## events

Events with dates, registration, and venue information. Per-event price and
capacity live on [ticket_tiers](ticket_tiers.md) — an event with zero tiers is
free and uncapped.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| title | string | no | |
| slug | string | no | unique |
| author_id | bigint unsigned | no | FK → users.id, restrictOnDelete |
| sponsor_organization_id | uuid | yes | FK→organizations, nullOnDelete; corporate sponsor of the event. |
| description | text | yes | |
| status | enum | no | default: 'draft'; values: draft, published, cancelled |
| source | string | no | default: human; values: human, import, scrub_data (per `Event::ACCEPTED_SOURCES`). Origin discriminator — orthogonal to `status`. |
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
| registration_mode | string | no | default: 'open' |
| sold_out | boolean | no | default: false. Operator-controllable flag (toggle on the event form). Surfaces the "Sold Out" badge in the events listing (cheap column read, no per-event capacity counting) and closes the landing-page registration form. Manual-only as of session 327 — no automatic flip when capacity is reached (deferred; see housekeeping inbox). |
| external_registration_url | string | yes | |
| auto_create_contacts | boolean | no | default: true |
| mailing_list_opt_in_enabled | boolean | no | default: false |
| landing_page_id | uuid | yes | FK→pages, nullOnDelete; system-managed by EventObserver |
| registrants_deleted_at | timestamp | yes | Set when staff runs Delete Registrant Contacts action |
| custom_fields | jsonb | yes | |
| import_session_id | uuid | yes | FK→import_sessions, nullOnDelete. Set for events created by the events importer so rollback can cascade correctly. |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
