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
| registered_at | timestamp | no | default: current |
| stripe_payment_intent_id | string | yes | |
| stripe_session_id | string | yes | Stripe Checkout session ID for paid registrations |
| mailing_list_opt_in | boolean | no | default: false |
| notes | text | yes | |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
