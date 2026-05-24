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
| content_hash | string | yes | SHA-256 hex of the stored original; populated at upload (MediaHasBeenAddedEvent) and backfilled via `media:backfill-hashes`. Indexed. Drives upload-time dedup, the Media Finder duplicate scan, and content-addressed storage (the file's on-disk directory + refcounted deletion are keyed on this — see below). |
| manipulations | json | no | |
| custom_properties | json | no | |
| generated_conversions | json | no | Tracks which conversions have been generated |
| responsive_images | json | no | |
| order_column | integer | yes | |
| created_at | timestamp | yes | |
| updated_at | timestamp | yes | |

### Content-addressed storage (session 320)

Files are stored under a directory derived from `content_hash`, not `media.id`:
`cas/{hash[0:2]}/{hash}/{file_name}` (with conversions and responsive images in
the usual `conversions/` and `responsive-images/` subdirectories alongside).
Two media rows with identical bytes **and** the same `file_name` therefore
resolve to one physical file — disk is stored once and shared. (`App\Services\Media\ContentAddressedPathGenerator`, registered as the `path_generator`
in `config/media-library.php`.)

The public URL follows the physical path (`/storage/cas/{shard}/{hash}/{file_name}`); files are served statically through the `public/storage` symlink, so there is no separate URL-layer indirection.

Deletion is **refcounted** (`App\Services\Media\ContentAddressedFileRemover`):
deleting a `media` row removes the physical files only when no other row shares
its `content_hash`. No counter column is stored — the refcount is computed from
the `content_hash` index at delete time.

There is **no schema change** for this; the layout is enforced by the path
generator + file remover and the one-time `media:relocate-cas` relocation
(invoked by the `2026_05_22_130000` migration). Same-content/different-`file_name`
pairs share the hash directory but keep their separate named originals (not
collapsed in v1).
