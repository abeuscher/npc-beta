## transactions

Financial transaction ledger entries. Subject is polymorphic — one table covers donations and future payment types. `contact_id` is denormalized for efficient filtering; populated from the subject's contact where known, null for manual (off-system) entries.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| subject_type | string | yes | Polymorphic model class, e.g. App\Models\Donation |
| subject_id | string | yes | Polymorphic FK (UUID string) |
| contact_id | uuid | yes | FK → contacts.id; denormalized for filtering; null on manual entries |
| organization_id | uuid | yes | FK → organizations.id, nullOnDelete; the bill-to / payer Org for invoice-shaped Transactions. Coexists non-exclusively with `contact_id`. |
| type | string | no | default: 'payment' |
| amount | decimal(10,2) | no | |
| direction | string | no | default: 'in'; values: in, out |
| status | string | no | default: 'pending'; values: pending, completed, failed |
| source | string | no | default: human; values: human, import, stripe_webhook, scrub_data (per `Transaction::ACCEPTED_SOURCES`). Origin discriminator — orthogonal to `status`. |
| stripe_id | string | yes | |
| quickbooks_id | string | yes | |
| qb_sync_error | text | yes | Last sync error message; cleared on success |
| qb_synced_at | timestamp | yes | Set when QB sync succeeds |
| occurred_at | timestamp | no | default: current |
| import_source_id | uuid | yes | FK→import_sources, nullOnDelete. Identifies the source that owns this external_id for dedupe. |
| import_session_id | uuid | yes | FK→import_sessions, nullOnDelete. Set for transactions created by the importer so rollback can cascade correctly. |
| external_id | string | yes | The source-system payment reference (invoice #, confirmation code, Stripe PaymentIntent). Universal payment external key; deduped via `(import_source_id, external_id)`. |
| payment_method | string | yes | Imported payment method (Card, Check, Cash, etc). Free-text snapshot from source. |
| payment_channel | string | yes | Imported payment channel (e.g. 'online', 'offline'). |
| invoice_number | string | yes | Human-readable invoice/receipt number. Distinct from `external_id` (technical source-system ID). |
| line_items | jsonb | yes | Array of `{item, quantity, price, amount}` objects. Populated by the Invoice Details importer when multiple CSV rows share the same invoice. |
| custom_fields | jsonb | yes | User-defined custom field values. Populated by the Invoice Details importer when columns are mapped as `__custom_invoice__`. |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |

Indexes:
- `(import_source_id, external_id)` — `transactions_import_external_idx`, for fast upsert lookup.
- `(contact_id)` — `transactions_contact_id_index`.
- `(organization_id)` — `transactions_organization_id_index`.
- `(subject_type, subject_id)` — `transactions_subject_type_subject_id_index`.
- `(source)` — `transactions_source_index`.
