# Session 011 Log — CRM Taxonomy & Contact Model Clarity

**Date:** 2026-03-14
**Branch:** ft-session-011
**Status:** Complete

---

## Summary

This session was scoped to audit and formalise the contact taxonomy before building Events. It started as a straightforward audit-and-document task but evolved into a significant architectural discussion that produced real schema changes. The session ended with a cleaner, less ambiguous data model and a new Household entity.

---

## What Was Built

### Decisions Made (documented in ADR 014)

- **Contact is always a person.** The `type` field (`individual` | `organization`) and `organization_name` column are removed. A contact record is unconditionally a human being with `first_name`, `last_name`, `prefix`, `preferred_name`.
- **Organization is a financial and affiliation entity.** It is not a CRM constituent. It can be the source of a donation and can have affiliated contacts, but it does not log in, receive email, or register for events.
- **Household is a named mailing group.** A thin model with a required `name` and address fields. Contacts optionally belong to a household. The household address is the canonical mailing address for its members.
- **Roles (member, donor) are derived, never stored.** No `is_member` flag. Computed from Membership and Donation records via named scopes and instance helpers.
- **Household membership is admin-managed for beta.** Self-service household creation, invite flow, and portal UI are deferred to the member portal session.

### Schema Changes (3 migrations)

| Migration | What it does |
|-----------|-------------|
| `2026_03_14_000003_create_households_table` | New `households` table: `id`, `name`, address fields, soft deletes |
| `2026_03_14_000004_update_contacts_remove_type_add_household` | Drops `type` and `organization_name` from contacts; adds nullable `household_id` FK |
| `2026_03_14_000005_add_organization_id_to_donations` | Adds nullable `organization_id` FK to donations; makes `contact_id` nullable |

### New Files

- `app/Models/Household.php` — model with `members()` HasMany, soft deletes
- `app/Filament/Resources/HouseholdResource.php` — list, create, edit; CRM group sort 4
- `app/Filament/Resources/HouseholdResource/Pages/` — List, Create, Edit pages
- `app/Filament/Resources/HouseholdResource/RelationManagers/MembersRelationManager.php` — add member with optional address sync; sync-all action; remove action
- `docs/decisions/014-contact-taxonomy.md` — full ADR

### Updated Files

- `app/Models/Contact.php` — removed `type`, `organization_name`; added `household()` BelongsTo; added `scopeIsMember`, `scopeIsDonor`, `scopeIsPublicDonor`; added `isMember()`, `isDonor()` helpers
- `app/Models/Organization.php` — added `donations()` HasMany
- `app/Models/Donation.php` — added `organization_id` to fillable; added `organization()` BelongsTo
- `app/Filament/Resources/ContactResource.php` — removed type field, badge, filter; added Household column and "In a household" filter; added role filters (member, donor); added eager loading
- `app/Filament/Resources/TagResource.php` — nav sort bumped from 4 → 5
- `database/factories/ContactFactory.php` — removed `type`, `organization_name`, `organization()` state
- `tests/Feature/ContactTest.php` — updated to remove type/org assertions; added display_name edge case test
- `docs/information-architecture.md` — updated Contact Taxonomy section; updated nav sort; updated model table

---

## Architectural Notes

The session surfaced a pre-existing ambiguity: the original schema had `Contact(type=organization)` and a separate `Organization` model doing overlapping jobs. The discussion evaluated three options (polymorphic constituents, shared anchor table, explicit separate models) before settling on the simplest solution specific to this product's use cases:

- Contact = person only
- Organization = financial/affiliation entity (separate, no constituent status)
- Household = mailing group (new, thin, admin-managed)

The "list together" problem for the donation entry form (selecting a contact or organization as donor) is deferred to when that UI is built — it's one custom search component, not a schema problem.

### Household UX (admin)

1. Create a household with a name and address in the Households panel
2. Add contacts via the Members relation manager tab
3. Toggle "Apply household address" on add to overwrite the contact's personal address
4. "Sync Address to All Members" bulk action available when the household address changes later
5. Remove individual members with confirmation (contact keeps their address, loses household link)

### Deferred to Member Portal Session

- Self-service household creation and invite/approve flow
- `household_members` pivot table (role, status, invited_by, accepted_at)
- Live address resolution (read from household vs. one-time overwrite)
- Household membership tier (family membership belonging to a household)

---

## Test Results

```
Tests: 54 passed (116 assertions)
```

All existing tests pass. ContactTest updated to reflect the removed type/organization_name fields.

---

## Infrastructure Note

The PostgreSQL Docker container became unresponsive partway through the session, causing `docker compose exec app php artisan migrate` to fail silently with a Docker API 500 error. The container was restarted and migrations ran successfully. Memory saved: recognise the `500 Internal Server Error .../exec/...` pattern as a hung DB, stop and ask for a container restart rather than retrying.
