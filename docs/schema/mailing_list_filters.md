## mailing_list_filters

Individual filter rules that define which contacts belong to a mailing list.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| mailing_list_id | uuid | no | FK→mailing_lists, cascade |
| field | string | no | Contact column name |
| operator | string | no | e.g. equals, contains, is_true |
| value | string | yes | |
| sort_order | integer | no | default: 0 |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
