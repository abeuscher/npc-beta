## affiliations

Junction table binding Contacts to Organizations with role / date / primary metadata. A contact may be affiliated with multiple organizations and hold multiple roles at the same organization.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| contact_id | uuid | no | FK→contacts, cascadeOnDelete |
| organization_id | uuid | no | FK→organizations, cascadeOnDelete |
| role | text | yes | Free-form, operator-curated. No enum. |
| is_primary | boolean | no | default: false. At most one is_primary=true row per contact (enforced both at the model layer via a saving boot hook on `Affiliation` and at the DB layer via a partial unique index — see below). |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |

Indexes:

- `affiliations_contact_id_index` on `(contact_id)` — read-side scan from the Contact admin form repeater + `Contact->affiliations`/`primaryAffiliation` relations.
- `affiliations_organization_id_index` on `(organization_id)` — read-side scan from the Org show page's Affiliated Contacts panel + `Organization->contacts`/`affiliations` relations.
- `affiliations_one_primary_per_contact` UNIQUE on `(contact_id) WHERE is_primary = true` — partial index enforcing the single-primary-per-contact invariant. Created via raw SQL (`DB::statement(...)`) since Laravel's Blueprint has no fluent partial-index API. The `Affiliation::booted()` `saving` hook clears prior primaries before each save so the index never trips through normal application paths; raw INSERTs that bypass the model trigger a `QueryException`.

Foreign keys:

- `affiliations_contact_id_foreign` → `contacts.id` ON DELETE CASCADE.
- `affiliations_organization_id_foreign` → `organizations.id` ON DELETE CASCADE.

Force-deleting either parent removes the affiliation row. The application-layer deletion guard on `OrganizationResource` surfaces the affiliation count before allowing force-delete.

History: introduced at session 264 along with the data migration that copied `contacts.organization_id` rows into affiliations (with `is_primary = true`) and dropped the column. Soft-credit attribution (`donation_credits`) lands at session 265.
