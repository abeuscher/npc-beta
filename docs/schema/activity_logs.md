## activity_logs

Immutable audit stream recording CUD operations on key models, attributed to the actor who caused them.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | bigint | no | PK |
| subject_type | string | no | Fully-qualified model class name |
| subject_id | string | no | Model PK as string (UUID or bigint) |
| actor_type | string | no | Values: admin, portal, system |
| actor_id | unsignedBigInteger | yes | FK to users (admin) or portal_accounts (portal); null for system |
| event | string | no | Values: created, updated, deleted, restored |
| description | string | yes | Human-readable context (e.g. "Published", "Status changed to cancelled") |
| meta | json | yes | Arbitrary key/value payload attached by the caller (e.g. tax_year, resend flag) |
| created_at | timestamp | no | Set by DB default; immutable |

Index on `(subject_type, subject_id)`. Index on `(actor_type, actor_id)`. No `updated_at`.
