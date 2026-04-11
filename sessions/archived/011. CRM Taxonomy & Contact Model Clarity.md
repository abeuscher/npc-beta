# Session 011 Prompt — CRM Taxonomy & Contact Model Clarity

## Context

This session audits and solidifies the contact taxonomy before building Events (session 012) and the member portal. The goal is to make implicit distinctions explicit and give the CRM clear, extensible answers to "what kind of contact is this?" and "what roles does this contact have?"

### Current State (as of session 010)

**Contact model** has:
- `type` enum: `individual` | `organization` — mutual-exclusive structural type
- `is_deceased`, `do_not_contact` boolean flags
- `source` enum: `manual` | `import` | `form` | `api`
- Relationships: `memberships()`, `donations()`, `tags()`, `notes()`
- `activeMembership()` helper — returns latest active Membership record

**Membership model** has `status` (pending/active), `tier`, `starts_on`, `expires_on`, `amount_paid`

**No User ↔ Contact link yet** — deferred to member portal session

---

## Decisions Made for This Session

After reviewing the existing models against what Events and the member portal will need, the following decisions have been made:

### 1. Contact `type` — Keep `individual` | `organization`, no `household`

**Rationale:** Household grouping is a significant data model investment (a new top-level entity with its own relationships, billing address, gift credit rules). This product has no immediate need for it. Contacts can represent families via shared addresses or an organization-type contact named "The Smith Family" if truly needed. Defer household formally.

### 2. "Member" is derived — not a type and not a flag

**Rationale:** A contact is a member if and only if they have an active Membership record. There is no `is_member` column. The source of truth is the Membership table. We will add model scopes and helper methods to make querying/checking membership easy, but no redundant data.

### 3. Roles are cross-cutting — add a `roles` derived concept to the model

Contacts can simultaneously be a member, a donor, a registrant, and a volunteer. These are **roles**, not types. They are fully derived from other tables (Membership, Donation, EventRegistration in the future). We will add:
- Model scopes: `whereIsMember()`, `whereIsDonor()`
- Accessor helpers: `isMember()`, `isDonor()`, `hasDonated()`
- Filament filters for these roles

We do **not** add a `roles` column or pivot table — roles are computed, not stored.

### 4. Anonymous donations — no contact-level flag needed

The existing `is_anonymous` field on the Donation model is the right level. An anonymous donation still has a contact record (for audit and deduplication), but the donation itself is marked anonymous and the public-facing layer respects that. No changes needed.

### 5. Contact ↔ User link — defer to member portal session

No `user_id` on Contact this session. That link will be established when building the public auth flow.

### 6. Event readiness — no schema changes needed

A future `EventRegistration` model will have `contact_id` (BelongsTo Contact) and `event_id`. The Contact model needs a `registrations()` HasMany stub added now so the relationship is declared even before the EventRegistration table exists. We'll guard it with a `if (class_exists(EventRegistration::class))` check or simply add it as a commented stub with a TODO comment, matching the project's pattern for planned relationships.

---

## Build List

### 1. Audit `contact.type` usage

Search all PHP, Blade, and migration files for references to `contact.type`, `->type`, `'individual'`, `'organization'` in the contact context. Document all found usages. No changes — audit only.

### 2. Add model scopes and helpers to Contact

Add to `app/Models/Contact.php`:

**Scopes (query builders):**
```php
// Returns contacts with at least one active membership
public function scopeIsMember(Builder $query): Builder
{
    return $query->whereHas('memberships', fn ($q) => $q->where('status', 'active'));
}

// Returns contacts with at least one donation
public function scopeIsDonor(Builder $query): Builder
{
    return $query->whereHas('donations');
}

// Returns contacts with at least one non-anonymous donation
// (useful for "public donor" lists)
public function scopeIsPublicDonor(Builder $query): Builder
{
    return $query->whereHas('donations', fn ($q) => $q->where('is_anonymous', false));
}
```

**Instance helpers:**
```php
public function isMember(): bool
{
    return $this->activeMembership() !== null;
}

public function isDonor(): bool
{
    return $this->donations()->exists();
}
```

**Relationship stub for Events (coming in session 012):**
```php
// EventRegistration relationship — model and migration added in session 012
// public function registrations(): HasMany
// {
//     return $this->hasMany(\App\Models\EventRegistration::class);
// }
```

### 3. Update ContactResource Filament UI

In `app/Filament/Resources/ContactResource.php`:

**Add filters** to the table's `filters()` method:
- "Is Member" filter — uses the `isMember` scope
- "Is Donor" filter — uses the `isDonor` scope

**Add a "Roles" indicator column** to the table (after the `type` badge):
- Small badge/icon group showing which roles apply: Member, Donor
- These should be visual indicators derived from relationships, not stored data
- Use a `IconColumn` or custom `TextColumn` with HTML that shows colored badges

### 4. No migrations needed

This session makes no schema changes. All additions are at the model/application layer.

### 5. Update `docs/information-architecture.md`

Add a new section **"Contact Taxonomy"** that documents:
- The two structural types (individual, organization) and when to use each
- The role system: member, donor, registrant (future) — all derived
- Explicit decision: no household type in this product
- Explicit decision: member is derived from Membership, not a flag
- Explicit decision: anonymous donations keep a contact record
- Explicit decision: Contact ↔ User link is deferred to member portal

### 6. Write ADR 014 — Contact Taxonomy

Create `docs/decisions/014-contact-taxonomy.md` documenting:
- The decision to keep type as `individual` | `organization` only
- The decision to derive roles (member, donor) rather than store them
- The rationale for deferring household
- The rationale for deferring Contact ↔ User link
- What this decision enables (Events, Import/Export, member portal all have a clean foundation)

---

## Acceptance Criteria

- [ ] Audit complete — all `contact.type` usages documented in the session log
- [ ] `Contact` model has `scopeIsMember`, `scopeIsDonor`, `scopeIsPublicDonor` scopes
- [ ] `Contact` model has `isMember()` and `isDonor()` instance helpers
- [ ] ContactResource table has "Is Member" and "Is Donor" filters
- [ ] ContactResource table shows a roles indicator column
- [ ] `docs/information-architecture.md` has a Contact Taxonomy section
- [ ] `docs/decisions/014-contact-taxonomy.md` exists and is complete
- [ ] All existing tests pass (`php artisan test`)
- [ ] No new migrations (confirm no schema changes)

---

## Notes

- Scope methods should be named to avoid collision: `scopeIsMember` not `scopeMembers` (Laravel convention is `scope` + PascalCase = call as camelCase)
- The roles indicator in the table should gracefully handle the N+1 problem — use `with(['memberships', 'donations'])` or subquery-based scopes, not per-row queries. Check if the existing ContactResource already eager-loads; if not, add `->with(['memberships' => fn ($q) => $q->where('status', 'active'), 'donations'])` to the table query.
- Follow the existing code style in ContactResource (section comments, Filament v3 API)
