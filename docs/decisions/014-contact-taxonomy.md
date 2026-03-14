# 014 — Contact Taxonomy

**Date:** March 2026 (Session 011)
**Status:** Decided

---

## Context

Before building Events (session 012) and the member portal, the CRM needed a clear and documented answer to "what kind of contact is this?" and "who can own a donation, a membership, or a mailing?" The original schema had a `contact.type` field (`individual` | `organization`) and a separate `Organization` model — two overlapping concepts with no clear boundary. As the product adds Households and prepares for a member portal, these ambiguities needed to be resolved.

---

## Decisions

### 1. Contact is always a person

The `type` field is removed from contacts. `Contact` is a person record, full stop. It always has `first_name`, `last_name`, `prefix`, `preferred_name`. The conditional organization-contact concept (type='organization' with `organization_name`) is eliminated.

**Rationale:** A contact of type organization was semantically a different thing from a person, but shared the same table, the same form, and the same FK relationships. This created confusion about what "contact" means everywhere else in the system. Making Contact mean "person" unconditionally removes that ambiguity.

### 2. Organization is a separate, first-class financial and affiliation entity

`Organization` is its own model. It is not a constituent in the full CRM sense — it cannot log in, cannot receive email, cannot register for events. It exists for two purposes:
1. **Affiliation** — a contact works for or is associated with an organization (`contacts.organization_id`)
2. **Financial** — an organization can be the source of a donation (`donations.organization_id`)

Organizations do not participate in memberships, event registrations, or portal auth in this product.

**Rationale:** Organizations do not need to be in the same constituent space as people for this product. Forcing them into a common ancestry (e.g., a shared `constituents` table) would add complexity without proportionate benefit. The only place organizations and contacts need to appear in the same list is the donation entry form — which is solved with a single custom search component, not a schema change.

### 3. Household is a named mailing group

`Household` is a thin model with a **name** and address fields. Contacts optionally belong to a household via `household_id`. The household's address is the canonical mailing address for all its members. When a contact is added to a household, their address fields are overwritten with the household address.

Household is not a constituent. It does not log in, receive email individually, register for events, or have a membership (household memberships are deferred). It is a mailing and grouping construct.

**Why a name field is required:** Household name is what appears on envelopes and mailing lists ("The Smith Household"). It is a required field, not optional, because a nameless household is not useful for correspondence.

**Beta scope:** Household membership is admin-managed only. An admin creates a household, sets the address, and adds contacts through the Filament admin panel. Self-service household management (creation, invite flow, portal UI) is deferred to the member portal session.

### 4. Roles are derived, not stored

Member, donor, and registrant are roles — not types. They are derived from related records (Membership, Donation, EventRegistration). No `is_member`, `is_donor`, or `roles` column exists on any model.

### 5. Donation ownership — two nullable FKs, not polymorphic

`donations.contact_id` and `donations.organization_id` are both nullable FKs. Exactly one must be set per donation (application-layer enforcement). This is intentionally explicit rather than polymorphic — it enables simple JOIN-based reporting, is easy to query, and makes the constraint visible in the schema.

### 6. Contact ↔ User link deferred

No `user_id` on contacts. The link between a portal user and their contact record is established in the member portal session.

---

## Consequences

- **Events (session 012):** `EventRegistration` will have `contact_id → contacts.id`. No other changes needed.
- **Import/Export:** Contact's canonical fields are fully defined — first_name, last_name, prefix, preferred_name, email, phone, address, org affiliation, household, flags.
- **Member portal:** The auth session creates the User ↔ Contact link. Household self-management is also deferred to that session.
- **Mailing:** Household name + address are the mailing target for household members. Non-household contacts use their own address. Deduplication happens at the household level.
- **Reporting:** "All donors" requires UNIONing contacts and organizations on the donations table. Use `Donation::with(['contact', 'organization'])` and handle both in display logic.

---

## Future Work (documented for the portal session)

- Household self-service: create, invite, accept/decline flow via the member portal
- `household_members` pivot table (with role, status, invited_by) to support invite workflow
- Address resolution: live read from household rather than one-time overwrite
- Household membership tier (family membership belonging to a household, not a contact)
