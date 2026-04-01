# Data Retention Policy

Developer-facing reference for deletion behaviour, soft-delete retention, and purge rules.

Last updated: 2026-04-01 (session 110)

---

## 1. Soft-delete behaviour

The following models use Laravel `SoftDeletes`. Deleting them via the admin UI sets `deleted_at` rather than removing the row.

| Model | Table | Retention period | Notes |
|-------|-------|-----------------|-------|
| Campaign | campaigns | Indefinite | Rarely deleted; kept for historical reporting |
| Collection | collections | 90 days | Custom collections only; system collections cannot be deleted |
| CollectionItem | collection_items | 90 days | Cascades with parent collection |
| Contact | contacts | 180 days | PII implications — see section 5 |
| Form | forms | 90 days | Soft-deleted forms return 404 on submission |
| FormSubmission | form_submissions | 180 days | May contain PII from submitters |
| Membership | memberships | 365 days | Financial history (amount_paid, Stripe references) |
| Note | notes | 90 days | Polymorphic; attached to contacts, orgs, etc. |
| Organization | organizations | 180 days | Contacts referencing org get org_id nulled on org hard-delete |
| Page | pages | 90 days | Includes posts (type=post), event landing pages, member pages |

**Retention period** = minimum time a soft-deleted record must remain before purge is allowed. These are policy minimums, not automated deadlines.

---

## 2. Cascade rules summary

### When a Contact is deleted (soft)

Soft-delete does **not** trigger FK cascades. All child records remain intact.

| Child table | FK column | onDelete rule | Effect of soft-delete | Effect of force-delete |
|-------------|-----------|--------------|----------------------|----------------------|
| donation_receipts | contact_id | RESTRICT | None | **Blocked** — must delete receipts first |
| donations | contact_id | SET NULL | None | contact_id set to null |
| event_registrations | contact_id | SET NULL | None | contact_id set to null |
| form_submissions | contact_id | SET NULL | None | contact_id set to null |
| memberships | contact_id | RESTRICT | None | **Blocked** — must delete memberships first |
| portal_accounts | contact_id | SET NULL | None | contact_id set to null |
| transactions | contact_id | SET NULL | None | contact_id set to null |
| purchases | contact_id | SET NULL | None | contact_id set to null |
| allocations | contact_id | SET NULL | None | contact_id set to null |
| waitlist_entries | contact_id | SET NULL | None | contact_id set to null |
| contact_duplicate_dismissals | contact_id_a/b | CASCADE | None | Dismissal records removed |
| import_staged_updates | contact_id | CASCADE | None | Staged updates removed |
| contacts (household_id) | household_id | SET NULL | None | household_id set to null |

Force-delete of a contact is restricted to `super_admin` role (enforced in session 109).

### When a User (admin) is deleted

| Child table | FK column | onDelete rule | Effect |
|-------------|-----------|--------------|--------|
| pages | author_id | RESTRICT | **Blocked** — must reassign pages first |
| events | author_id | RESTRICT | **Blocked** — must reassign events first |
| notes | author_id | SET NULL | author_id set to null |
| templates | created_by | SET NULL | created_by set to null |
| import_sessions | imported_by | SET NULL | imported_by set to null |
| import_logs | user_id | SET NULL | user_id set to null |
| import_sessions (approved_by) | approved_by | SET NULL | approved_by set to null |
| invitation_tokens | user_id | CASCADE | Tokens removed (expected) |
| contact_duplicate_dismissals | dismissed_by | SET NULL | dismissed_by set to null |
| posts (legacy) | author_id | SET NULL | author_id set to null |

### When an Event is deleted

| Child table | FK column | onDelete rule | Effect |
|-------------|-----------|--------------|--------|
| event_registrations | event_id | CASCADE | All registrations destroyed |

Events cannot be deleted via UI when registrations exist (session 109 guard).

### When a Page is deleted (soft)

Soft-delete does not trigger FK cascades. On force-delete:

| Child table | FK column | onDelete rule | Effect |
|-------------|-----------|--------------|--------|
| page_widgets | page_id | CASCADE | All widgets destroyed |
| navigation_items | page_id | SET NULL | page_id set to null |
| events (landing_page_id) | landing_page_id | SET NULL | landing_page_id set to null |
| templates (header/footer_page_id) | header/footer_page_id | SET NULL | Reference set to null |
| pages (template_id) | template_id | SET NULL | template_id set to null |

### When a Collection is deleted (soft → force)

| Child table | FK column | onDelete rule | Effect of force-delete |
|-------------|-----------|--------------|----------------------|
| collection_items | collection_id | CASCADE | All items destroyed |

### When a Form is deleted (soft → force)

| Child table | FK column | onDelete rule | Effect of force-delete |
|-------------|-----------|--------------|----------------------|
| form_submissions | form_id | CASCADE | All submissions destroyed |

### When a Product is deleted

| Child table | FK column | onDelete rule | Effect |
|-------------|-----------|--------------|--------|
| product_prices | product_id | CASCADE | Prices destroyed |
| purchases | product_id | RESTRICT | **Blocked** — cannot delete product with purchases |
| allocations | product_id | RESTRICT | **Blocked** (legacy table) |
| waitlist_entries | product_id | CASCADE | Waitlist entries removed |

### When a Fund is deleted

| Child table | FK column | onDelete rule | Effect |
|-------------|-----------|--------------|--------|
| donations | fund_id | SET NULL | fund_id set to null |

Fund delete is blocked in UI when donations reference it (session 109 guard).

### When a Membership Tier is deleted

| Child table | FK column | onDelete rule | Effect |
|-------------|-----------|--------------|--------|
| memberships | tier_id | SET NULL | tier_id set to null |

Tier delete is blocked in UI when active memberships exist (session 109 guard).

### When a Widget Type is deleted

| Child table | FK column | onDelete rule | Effect |
|-------------|-----------|--------------|--------|
| page_widgets | widget_type_id | RESTRICT | **Blocked** — cannot delete widget type in use |

### When a Navigation Menu is deleted

| Child table | FK column | onDelete rule | Effect |
|-------------|-----------|--------------|--------|
| navigation_items | navigation_menu_id | CASCADE | All menu items destroyed |

### When a Template is deleted

| Child table | FK column | onDelete rule | Effect |
|-------------|-----------|--------------|--------|
| pages (template_id) | template_id | SET NULL | template_id set to null |

### When an Import Session is deleted

| Child table | FK column | onDelete rule | Effect |
|-------------|-----------|--------------|--------|
| contacts (import_session_id) | import_session_id | SET NULL | import_session_id set to null |
| import_staged_updates | import_session_id | CASCADE | Staged updates destroyed |

### When a Tag is deleted

| Child table | FK column | onDelete rule | Effect |
|-------------|-----------|--------------|--------|
| taggables | tag_id | CASCADE | Pivot rows removed |

### When a Mailing List is deleted

| Child table | FK column | onDelete rule | Effect |
|-------------|-----------|--------------|--------|
| mailing_list_filters | mailing_list_id | CASCADE | Filter rules removed |

---

## 3. Purge policy

Purging permanently removes soft-deleted records from the database after the retention period has elapsed.

### Rules

- **Who can purge:** `super_admin` role only.
- **How:** Manual artisan command or admin action (not yet implemented).
- **Minimum retention:** see retention periods in section 1. No record may be purged before its retention period expires.
- **Prerequisites before purge:**

| Model | Prerequisites |
|-------|--------------|
| Contact | All memberships for the contact must be soft-deleted or reassigned. All donation_receipts for the contact must be deleted. Portal account must be detached (contact_id nulled). |
| Form | All form_submissions must be purged first (or accepted as cascade loss). |
| Collection | All collection_items must be purged first (or accepted as cascade loss). |
| Page | Confirm no navigation items, event landing pages, or templates reference the page. Widgets will cascade. |
| Membership | No prerequisites (contact FK is RESTRICT, so membership must be purged before contact). |
| FormSubmission | No prerequisites. |
| Note | No prerequisites. |
| Organization | Contacts referencing the org will have organization_id set to null. |
| Campaign | No prerequisites. |
| CollectionItem | No prerequisites. |

### Not yet implemented

Automated purging is deferred. Future implementation should be:
- An `artisan purge:soft-deleted` command with `--model=` and `--before=` flags
- Restricted behind `super_admin` gate check
- Dry-run mode by default, `--force` to execute
- Logs all purged record IDs to activity_logs

---

## 4. Financial record immutability

The following tables contain financial records that must **never** be deleted:

| Table | Protection |
|-------|-----------|
| donations | No soft-delete, no delete action in UI (`canDelete=false`) |
| transactions | No soft-delete, no delete action in UI |
| donation_receipts | No soft-delete; RESTRICT FK prevents contact force-delete if receipts exist |
| purchases | No soft-delete; RESTRICT FK prevents product/price deletion if purchases exist |

Financial records may be **cancelled** or **refunded** via status changes but are never removed from the database. The `transactions` table is an append-only ledger — rows are created by Stripe webhooks and QuickBooks sync, never updated after initial creation (except for QB sync metadata).

---

## 5. Contact PII handling on deletion

When a contact is soft-deleted:
- The record remains in the database with all PII intact.
- It is excluded from standard queries via Laravel's `SoftDeletes` scope.
- It can be restored by any admin with the appropriate permission.

When a contact is force-deleted (super_admin only):
- The `contacts` row is permanently removed.
- All child records with SET NULL FKs lose their contact association but are preserved (donations, registrations, transactions, etc.).
- Force-delete is **blocked** if donation_receipts or memberships still reference the contact (RESTRICT FKs). These must be resolved first.
- Activity log entries referencing the contact (as subject) remain but the subject becomes unresolvable.

**PII minimisation principle:** the system does not store more PII than necessary. Contact records contain names, email, phone, and address. On force-delete, the only surviving PII is in denormalized fields on event_registrations (name, email, phone, address — captured at registration time for the event's own records).
