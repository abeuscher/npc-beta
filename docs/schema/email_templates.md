## email_templates

System email template records, seeded by handle. Edited by admins via the Email Templates resource.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | bigInteger | no | PK |
| handle | string | no | unique; system-managed identifier (e.g. registration_confirmation) |
| subject | string | no | |
| body | text | no | |
| header_color | string | yes | |
| header_image_path | string | yes | |
| header_text | string | yes | |
| footer_sender_name | string | yes | |
| footer_reply_to | string | yes | |
| footer_address | text | yes | |
| footer_reason | string | yes | |
| custom_template_path | string | yes | Path to a custom HTML wrapper stored in public disk |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
