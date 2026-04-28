## event_registrations

Registrations submitted for an event.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| event_id | uuid | no | FK→events, cascade |
| contact_id | uuid | yes | FK→contacts, nullOnDelete |
| name | string | no | |
| email | string | no | |
| phone | string(50) | yes | |
| company | string | yes | |
| address_line_1 | string | yes | |
| address_line_2 | string | yes | |
| city | string(100) | yes | |
| state | string(100) | yes | |
| zip | string(20) | yes | |
| status | enum | no | default: 'registered'; values: pending, registered, waitlisted, cancelled, attended |
| source | string | no | default: human; values: human, import, stripe_webhook (per `EventRegistration::ACCEPTED_SOURCES`). Origin discriminator — orthogonal to `status`. |
| registered_at | timestamp | no | default: current |
| stripe_payment_intent_id | string | yes | |
| stripe_session_id | string | yes | Stripe Checkout session ID for paid registrations |
| mailing_list_opt_in | boolean | no | default: false |
| notes | text | yes | |
| ticket_type | string | yes | Denormalized snapshot of the ticket tier (free/paid/member/etc). Populated by the events importer. |
| ticket_fee | decimal(10,2) | yes | Denormalized fee amount from the source. Authoritative amount lives on the linked Transaction when present. |
| payment_state | string | yes | Denormalized payment-state snapshot from the source (e.g. "Paid", "Free"). Authoritative state lives on the Transaction when present. |
| transaction_id | uuid | yes | FK→transactions, nullOnDelete. Set when the events importer created or matched a Transaction for this registration. |
| import_session_id | uuid | yes | FK→import_sessions, nullOnDelete. Set for registrations created by the events importer so rollback can cascade correctly. |
| custom_fields | jsonb | no | default: `{}`. Registration-scoped custom-field values (populated by the events importer's `__custom_registration__` sentinel). |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |

Indexes:
- `(source)` — `event_registrations_source_index`.
