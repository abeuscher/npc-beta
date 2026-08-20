## membership_tiers

**Owned by the Memberships plugin (`nonprofitcrm/memberships`, extracted to `crm-plugin--memberships` session 393)** — created by the plugin's `database/migrations/`, not core's schema dump.

Admin-configurable membership tier definitions. One default "Standard" tier is seeded on install when the memberships surface is present (the seeder call is route-presence-gated, session 393).

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| name | string | no | |
| slug | string | no | unique; auto-generated via Spatie sluggable |
| billing_interval | enum | no | values: monthly, annual, one_time, lifetime |
| default_price | decimal(8,2) | yes | null = complimentary / price not set |
| renewal_notice_days | integer | no | default: 30; reserved for future renewal flow |
| description | text | yes | |
| is_active | boolean | no | default: true |
| sort_order | integer | no | default: 0 |
| is_archived | boolean | no | default: false |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
