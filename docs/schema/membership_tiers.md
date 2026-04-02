## membership_tiers

Admin-configurable membership tier definitions. One default "Standard" tier is seeded on install.

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
