# Information Architecture
*Last updated: March 2026 (Session 011 post-session). Contact taxonomy formalised — see Contact Taxonomy section and ADR 014.*

---

## Core Principle: Contact Is the Center

Every person in this system is a `Contact`. Membership, donations, event registrations, and notes all belong to a contact. Finance never owns a person — it borrows one from CRM. Content never owns a person — it reads membership status from CRM.

---

## The Three Domains

### CRM — Constituent Relationship Management

Tracks people and organizations: who they are, what they've done, what they owe, what they've given.

| Model | Status | Description |
|-------|--------|-------------|
| `Contact` | ✅ Built | A person. Always an individual. The central CRM record. |
| `Organization` | ✅ Built | A company, foundation, government body, or other entity. Contacts affiliate with organizations. Organizations can be donors. |
| `Household` | ✅ Built | A named mailing group of contacts sharing a canonical address. Admin-managed for beta; self-service deferred to member portal session. |
| `Membership` | ✅ Built | A contact's membership status, tier, and dates. One active membership per contact at a time. |
| `Tag` | ✅ Built | Flat label for segmenting contacts and organizations (polymorphic). CRM-scoped only — CMS content uses `CmsTag`. |
| `Note` | ✅ Built | Timestamped log entry attached to a contact or organization (activity stream). |
| `Event` | ⬜ Deferred | Events attended by contacts. |
| `EventRegistration` | ⬜ Deferred | Contact-event junction with optional fee. |

### Content — CMS

Manages what the public website displays.

| Model | Status | Description |
|-------|--------|-------------|
| `Page` | ✅ Built | Public web page. `content` field will be removed in Session 009 — page body becomes an ordered block stack. |
| `Post` | ✅ Built | News article or blog entry. Dated, authored, slugged. Body remains a single rich text field for now. |
| `NavigationItem` | ✅ Built | Menu entry: label, link target (page/post/URL), parent, sort order. |
| `Collection` | ✅ Built | User-defined typed data bucket (Board Members, Sponsors, FAQs, etc.). Schema stored as JSONB. |
| `CollectionItem` | ✅ Built | A single item belonging to a Collection. Data stored as JSONB. Has a system-level `CmsTag` relationship for query filtering. |
| `CmsTag` | ⬜ Session 008 | Flat label for tagging collection items. Separate from CRM `Tag`. Supports include/exclude query filtering on widget data. |
| `WidgetType` | ⬜ Session 008 | Developer-defined content component. Stored in DB with handle, label, Blade template (server mode) or JS variable + code (client mode), plus CSS and JS snippets. Declares which collections it consumes. |
| `PageWidget` | ✅ Built (foundation) | An instance of a WidgetType placed on a Page. Holds per-placement query config (limit, order, tag filters) and sort_order. Will be extended in Session 008. |
| Media library | ⬜ Deferred | Spatie Media Library UI. |
| Form builder | ⬜ Deferred | Public-facing forms. |

### Finance — Accounting and Fundraising

Tracks money. Connects to QuickBooks. Feeds donation receipts.

| Model | Status | Description |
|-------|--------|-------------|
| `Donation` | ✅ Built (scaffold) | Single contribution. Belongs to a contact. Has amount, date, method, and fund. |
| `Campaign` | ✅ Built (scaffold) | Named fundraising drive. Donations roll up to it. |
| `Fund` | ✅ Built (scaffold) | Internal accounting bucket. Maps to a QuickBooks class. |
| `Transaction` | ✅ Built (scaffold) | Low-level money record. Every donation produces a transaction. |
| QuickBooks sync | ⬜ Deferred | |
| Stripe webhooks | ⬜ Deferred | |

---

## Contact Taxonomy
*Decided: Session 011 (extended). See ADR 014.*

### Contact is always a person

`Contact` has no `type` field. Every contact record is an individual person. `first_name`, `last_name`, `prefix`, and `preferred_name` are always the name fields.

- **Organization** is a separate first-class model for companies, foundations, and other entities. Contacts affiliate with organizations via `organization_id`. Organizations can be donors.
- **Household** is a separate grouping model for mailing purposes (see below).

### Roles — derived, never stored

A contact can hold multiple roles simultaneously. Roles are computed from related records, never stored as columns or flags:

| Role | Derived from | Query scope |
|------|-------------|-------------|
| **Member** | Has an active `Membership` record (`status = 'active'`) | `Contact::isMember()` / `scopeIsMember()` |
| **Donor** | Has at least one `Donation` record | `Contact::isDonor()` / `scopeIsDonor()` |
| **Public Donor** | Has at least one non-anonymous `Donation` | `scopeIsPublicDonor()` |
| **Registrant** | Has at least one `EventRegistration` *(session 012)* | *(stub reserved)* |

### Households

A `Household` groups contacts who share a mailing address. It has a **name** (e.g., "Smith Household") used on correspondence, and a full set of address fields that is the canonical mailing address for all members.

- Contacts belong to a household via `household_id` (nullable FK)
- When a contact is added to a household, their personal address fields are overwritten with the household address
- Admin can sync the household address to all members at any time via the Households admin panel
- **Beta scope:** household membership is admin-managed only. Self-service household creation, invite flow, and address resolution in the member portal are deferred to the portal session

### Organizations as donors

An `Organization` can be the source of a donation (`donations.organization_id`). This covers foundation grants, corporate gifts, and other institutional giving. `donations.contact_id` and `donations.organization_id` are both nullable — exactly one must be set per donation (enforced at the application layer).

### Anonymous donations

An anonymous donation (`donations.is_anonymous = true`) still has a contact (or organization) record. The anonymity flag lives at the donation level. Public-facing views respect it.

### Contact ↔ User link

Deferred to the member portal session. No `user_id` on contacts yet.

---

## Widget System Architecture

### Two rendering modes

**Server mode** — Developer writes a Blade template. Collection data is injected as PHP variables named by handle (`$blog_posts`, `$board_members`, etc.). CSS and JS are inlined on the page when the widget is active. Standard PHP/Blade; SEO-friendly; JS is enhancement only.

**Client mode** — Developer names a JS variable (e.g. `boardMembers`). Server writes `window.boardMembers = [...]` into the page. A single code field (any valid JS/HTML/CSS) runs against it. No restrictions. This is a power-user escape hatch and carries the corresponding risk.

### Two admin interfaces

**Widget Type Manager** (Session 008) — Developer-facing CRUD for registering widget types. Sets the template/code, declares which collections the widget consumes, and defines per-collection query defaults.

**Page Builder** (Session 009) — Content editor UI for composing page content as an ordered block stack. Each block is either a text block (inline WYSIWYG) or a widget block. Supports drag-to-reorder and per-block query configuration (limit, order, tag include/exclude).

### Query filtering

Each widget instance can configure per-collection: limit, order field + direction, include-tags (any match), exclude-tags (none match). Tags use the `CmsTag` system, which is separate from CRM tags.

---

## Where the Domains Overlap

### CRM ∩ Finance — The Donor

A `Donation` belongs to a `Contact`. The contact record is the authoritative identity. Finance never owns a person.

- `donations.contact_id` → `contacts.id`
- `ContactResource` will eventually show a Donations relation manager tab
- Contacts can be auto-tagged `major-donor` when a threshold is crossed (future)

### CRM ∩ Content — The Member

A `Membership` belongs to a `Contact`. Content gating reads membership status from the CRM.

- `memberships.contact_id` → `contacts.id`
- The `User` (auth) model will link to a `Contact` for the member portal (future session)
- Content checks: "is this user's contact record showing an active membership?"
- Content has no knowledge of membership business rules

### CRM ∩ Content ∩ Finance — The Event Attendee *(planned)*

Not yet built. Architecture is planned:

- `Event` is a **Content** object (it has a page, a URL, a description)
- `EventRegistration` is a **CRM** object (a contact registered for an event)
- If there is a registration fee, it produces a **Transaction** (Finance)
- Event model lives in Content; registration and payment live in CRM/Finance

---

## Navigation Group Structure

Filament sidebar groups, ordered:

```
┌─ CRM ──────────────────────────────┐
│  Contacts          (heroicon-o-users)                         sort: 1
│  Organizations     (heroicon-o-building-office)               sort: 2
│  Memberships       (heroicon-o-identification)                sort: 3
│  Households        (heroicon-o-home)                          sort: 4
│  CRM Tags          (heroicon-o-tag)                           sort: 5
│  Events            (heroicon-o-calendar)                      sort: 6  ⬜ future
└────────────────────────────────────┘

┌─ Content ──────────────────────────┐
│  Pages             (heroicon-o-document-text)                 sort: 1
│  Blog Posts        (heroicon-o-newspaper)                     sort: 2
│  Collections       (heroicon-o-square-3-stack-3d)             sort: 3  (CollectionItems)
│  Navigation        (heroicon-o-bars-3)                        sort: 4
│  CMS Tags          (heroicon-o-tag)                           sort: 5
└────────────────────────────────────┘

┌─ Finance ──────────────────────────┐
│  Donations         (heroicon-o-heart)                         sort: 1
│  Campaigns         (heroicon-o-megaphone)                     sort: 2
│  Funds & Grants    (heroicon-o-banknotes)                     sort: 3
│  Transactions      (heroicon-o-receipt-percent)               sort: 4
└────────────────────────────────────┘

┌─ Tools ────────────────────────────┐
│  Widget Manager    (heroicon-o-puzzle-piece)                  sort: 1  (WidgetTypeResource)
│  Collection Manager(heroicon-o-circle-stack)                  sort: 2  (CollectionResource)
│  Import            —                                          sort: 3  ⬜ future
│  Export            —                                          sort: 4  ⬜ future
└────────────────────────────────────┘

┌─ Settings ─────────────────────────┐
│  CRM               (heroicon-o-user-circle)                   sort: 1  (UserResource)
│  CMS               —                                          sort: 2  ⬜ future (SiteSettingResource)
│  Finance           —                                          sort: 3  ⬜ future
└────────────────────────────────────┘

┌─ Integrations ─────────────────────┐  ⬜ future group
│  MailChimp         —
│  QuickBooks        —
│  Other             —
└────────────────────────────────────┘
```

Group sort order (AdminPanelProvider): CRM → Content → Finance → Tools → Settings.

Notes hidden from navigation (`$shouldRegisterNavigation = false`) — accessible via Contact/Organization relation managers only.

---

## Planned Future Additions

| Domain | Entity | Notes |
|--------|--------|-------|
| CRM | Event | Has a page. Attended by contacts. |
| CRM | EventRegistration | Contact ↔ Event junction. Optional fee. |
| CRM/Auth | Contact ↔ User link | Required for member portal gating. |
| Content | Post block editor | Posts gain block stack like pages. Deferred past Session 009. |
| Content | Media Library UI | Spatie Media Library management in admin. |
| Content | Form Builder | Public-facing forms (newsletter, contact, donations). |
| Finance | QuickBooks API | Outbound sync from Transaction mirror. |
| Finance | Stripe Webhooks | Keeps Transaction mirror current. |
| Finance | Grant module | Funder, Grant, GrantAllocation, GrantReport. |
| Finance | Tax Receipts | Annual giving statements. |
