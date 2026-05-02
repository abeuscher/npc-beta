## organizations

Organizations that contacts can be affiliated with (companies, foundations, etc.).

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| name | string | no | |
| type | string | yes | values: nonprofit, for_profit, government, other |
| website | string | yes | |
| phone | string | yes | |
| email | string | yes | |
| address_line_1 | string | yes | |
| address_line_2 | string | yes | |
| city | string | yes | |
| state | string | yes | |
| postal_code | string | yes | |
| country | string | yes | default: 'US' |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
| deleted_at | timestamp | yes | Soft delete |
