## forms

Web form definitions. Fields and settings stored as JSON.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | bigint | no | PK |
| title | string | no | Admin-facing label |
| handle | string | no | unique; used in `<x-public-form handle="…">` |
| description | text | yes | Admin notes, not shown publicly |
| fields | json | no | Array of field definition objects (handle, type, label, required, width, validation, contact_field, …) |
| settings | json | no | submit_label, success_message, honeypot, form_type (general\|contact) |
| is_active | boolean | no | default: true; inactive forms return 404 on submission |
| is_archived | boolean | no | default: false |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
| deleted_at | timestamp | yes | Soft delete |
