## ticket_tiers

Per-event ticket tiers — the canonical price and capacity for event registration.

Tiers are event-scoped (no shared tier pool). An event with zero tiers is free
and uncapped. An event with one or more tiers gets its price + capacity from
those rows; the registration flow picks one tier per registration via the
`event_registrations.ticket_tier_id` FK.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| event_id | uuid | no | FK→events, cascade |
| name | string | no | tier label, e.g. "General", "VIP", "Member" |
| price | decimal(8,2) | no | default: 0; per-tier price in the site's currency |
| capacity | unsignedInteger | yes | null = unlimited capacity for this tier |
| sort_order | unsignedInteger | no | default: 0; lower values render first in the public picker |
| is_complimentary | boolean | no | default: false; display-only "Complimentary" label in the admin repeater + public picker. Routing stays price-driven — a tier is free because `price = 0`, never because this flag is set. |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |

Indexes:
- `(event_id, sort_order)` — `ticket_tiers_event_id_sort_order_index`.

Backfill: session 278's migration creates one `{name: 'General', price: events.price, capacity: events.capacity, sort_order: 0}` row for every event that had a non-zero `price` OR a non-null `capacity` at migrate time. Events that were truly free + uncapped (price 0, capacity null) get no tier.

Retroactive importer linkage: the same migration walks every `event_registrations` row with a non-null `ticket_type` and resolves it to a tier. Matching name → reuse; new name → create a tier with `{name: ticket_type, price: ticket_fee, capacity: null}` on the registration's event.
