## donation_credits

Polymorphic junction table attributing soft-credit on a Donation to one or more recipients (Contact or Organization). The donation's authoritative payer remains `donations.contact_id` / `donations.organization_id`; soft-credit rows are an additive attribution layer for matching-gift, "in honour of", "triggered by", and similar use cases.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| donation_id | uuid | no | FK→donations, cascadeOnDelete |
| attributable_type | string | no | Polymorphic class — currently `App\Models\Contact` or `App\Models\Organization`. Persisted as full class name (no morph alias map registered codebase-wide as of session 265). |
| attributable_id | uuid | no | Polymorphic FK; the soft-credit recipient row's primary key. |
| credit_pct | decimal(5,2) | no | Soft-credit percentage. Allows values exceeding 100 for matching-gift configurations (a $1000 gift that triggers a 1:1 match can carry 200% to the original donor). No application-layer sum-ceiling — operator owns the invariant. |
| credit_role | text | yes | Free-form, operator-curated. Examples: "Honour of", "In memory of", "Triggered by", "Match recipient". Mirrors the free-text shape of `affiliations.role`. |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |

Indexes:

- `donation_credits_donation_id_index` on `(donation_id)` — read-side scan from the Donation admin's Soft-Credits relation manager + `Donation->softCredits` relation.
- `donation_credits_attributable_type_attributable_id_index` on `(attributable_type, attributable_id)` — read-side scan from `Contact->softCreditsReceived` / `Organization->softCreditsReceived` morph relations.

Foreign keys:

- `donation_credits_donation_id_foreign` → `donations.id` ON DELETE CASCADE.

Force-deleting a donation removes its soft-credit rows. There is **no FK constraint on `attributable_id`** — polymorphic targets can't be enforced at the DB layer; deleting a Contact / Organization that has received soft-credit will leave orphan rows pointing at the deleted target. Reading via the morph relation returns `null` for orphans; cleanup is a future-session concern (will likely follow the deletion-policy posture established for affiliations: cascade where possible, surface counts in the deletion guard otherwise).

History: introduced at session 265 as the soft-credit half of release-plan § B1b (the structural half — `affiliations` — shipped at session 264). Surfaced through the `SoftCreditsRelationManager` on `DonationResource` (registered alongside `TransactionsRelationManager`); not exposed on the Donations importer in v0.
