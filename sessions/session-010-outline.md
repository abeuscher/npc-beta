# Session 010 Outline — CRM Taxonomy & Contact Model Clarity

> **Session Preparation**: This is a planning outline, not a complete implementation prompt.
> At the start of this session, read this outline alongside the current Contact, Membership,
> Organization, and Tag models. Review what's been built and what Events will need (session 012+),
> then expand into a full implementation prompt. The goal is to get the CRM taxonomy right
> before it becomes load-bearing for Events and the member portal.

---

## Goal

Bring clarity and completeness to the contact taxonomy before building Events (which adds registrants) and before any member portal work. Right now the CRM has Contacts and Memberships but the distinction between a "member," a "donor," a "registrant," and a general constituent is implicit. Make it explicit and extensible.

---

## Key Decisions to Make at Session Start

- **Contact types**: The existing `type` field on Contact has `individual` / `organization`. Is this sufficient, or do we need a richer taxonomy? (e.g. `individual`, `organization`, `household`?)
- **Member vs Contact**: Is "member" a contact attribute (derived from active Membership), a contact type, or a separate flag? Currently it's derived — is that correct?
- **Constituent segments**: Should contacts be segmentable beyond Tags? (e.g. a `segment` or `contact_type` system that isn't just a freeform tag)
- **Household / family grouping**: Is there a need to group related individual contacts under a household record? Relevant for memberships and communications.
- **Anonymous contacts**: Donations can be anonymous (`is_anonymous = true`). Does an anonymous donation create any contact record at all?
- **Contact ↔ User link**: When does a Contact get a corresponding auth User? Is this the right session to establish that link, or defer to the member portal session?

---

## Scope

**In:**
- Audit existing Contact, Membership, Organization, Tag models against what Events and the member portal will need
- Define and document the formal taxonomy: what types of contacts exist, how they're distinguished, how they relate to memberships
- Any model/migration changes that come out of the audit
- Update the information architecture doc to reflect the agreed taxonomy
- Ensure the CRM is ready to accept event registrants as contacts (session 012 dependency)

**Out:**
- Member portal / public auth (separate session)
- Full household grouping model (may be deferred further)
- Contact ↔ User link (defer unless the audit reveals it's blocking)

---

## Rough Build List

- Audit: list every place `contact.type` is used and what values exist
- Decision: finalise the contact type taxonomy
- Any migrations needed (add fields, rename, add indexes for lookup performance)
- Model updates: scopes, casts, helpers that reflect the agreed taxonomy
- Update `ContactResource` Filament UI to reflect any new fields or filters
- Update `docs/information-architecture.md`
- Write a short ADR documenting the taxonomy decisions

---

## Open Questions at Planning Time

- Is "household" in scope for this product at all, or is it always individual/organization?
- Are there other CRM entities that emerged from Events planning that need to be addressed here first?

---

## What This Unlocks

- Events session can attach registrants to contacts with a clear taxonomy
- Import/Export session knows what fields a Contact canonical record has
- Member portal session has a clean Contact ↔ User link to build on
