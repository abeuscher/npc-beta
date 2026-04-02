## media

Spatie media library — stores file metadata and conversion state for models implementing HasMedia.

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| id | bigint | no | PK, auto-increment |
| model_type | string | no | Morph type (e.g. App\Models\EmailTemplate) |
| model_id | varchar(36) | no | Morph ID — supports both integer and UUID models |
| uuid | uuid | yes | |
| collection_name | string | no | Media collection name |
| name | string | no | |
| file_name | string | no | |
| mime_type | string | yes | |
| disk | string | no | |
| conversions_disk | string | yes | |
| size | bigint | no | File size in bytes |
| manipulations | json | no | |
| custom_properties | json | no | |
| generated_conversions | json | no | Tracks which conversions have been generated |
| responsive_images | json | no | |
| order_column | integer | yes | |
| created_at | timestamp | yes | |
| updated_at | timestamp | yes | |
