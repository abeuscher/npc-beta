## sample_images

Host rows for the demo/sample image pool. One row per category; the actual image files are stored as Spatie media rows attached to each `SampleImage`, one media collection per category name.

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| id | bigint | no | PK, auto-increment |
| category | string | no | Unique. One of: `portraits`, `still-photos`, `logos`, `product-photos`. |
| created_at | timestamp | yes | |
| updated_at | timestamp | yes | |

Populated by `database/seeders/SampleImageLibrarySeeder.php`, which syncs each row's media collection with the files under `resources/sample-images/{category}/`. Consumed by `App\Services\SampleImageLibrary` (widget demo seeders and `DemoDataService`).
