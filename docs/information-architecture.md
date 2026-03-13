# Information Architecture
*Last updated: March 2026 (Session 004). All three primary domains defined. Models, migrations, and Filament resources built.*

---

## Core Principle: Contact Is the Center

Every person in this system is a `Contact`. Membership, donations, event registrations, and notes all belong to a contact. Finance never owns a person — it borrows one from CRM. Content never owns a person — it reads membership status from CRM.

---

## The Three Domains

### CRM — Constituent Relationship Management

Tracks people and organizations: who they are, what they've done, what they owe, what they've given.

| Model | Status | Description |
|-------|--------|-------------|
| `Contact` | ✅ Built | A person. The central record. |
| `Organization` | ✅ Built | A company, foundation, government body, or other entity. Contacts belong to organizations. |
| `Membership` | ✅ Built | A contact's membership status, tier, and dates. One active membership per contact at a time. |
| `Tag` | ✅ Built | Flat label for segmenting contacts and organizations (polymorphic). |
| `Note` | ✅ Built | Timestamped log entry attached to a contact or organization (activity stream). |
| `Event` | ⬜ Deferred | Events attended by contacts. Session 005+. |
| `EventRegistration` | ⬜ Deferred | Contact-event junction with optional fee. Session 005+. |

### Content — CMS

Manages what the public website displays.

| Model | Status | Description |
|-------|--------|-------------|
| `Page` | ✅ Built | Static or semi-static web page with slug, content, and SEO fields. |
| `Post` | ✅ Built | News article or blog entry. Dated, authored, slugged. |
| `NavigationItem` | ✅ Built | Menu entry: label, link target (page/post/URL), parent, sort order. |
| Media library | ⬜ Deferred | Spatie Media Library UI. Session 005+. |
| Form builder | ⬜ Deferred | Public-facing forms. Later. |

### Finance — Accounting and Fundraising

Tracks money. Connects to QuickBooks. Feeds donation receipts.

| Model | Status | Description |
|-------|--------|-------------|
| `Donation` | ✅ Built (scaffold) | Single contribution. Belongs to a contact. Has amount, date, method, and fund. |
| `Campaign` | ✅ Built (scaffold) | Named fundraising drive. Donations roll up to it. |
| `Fund` | ✅ Built (scaffold) | Internal accounting bucket. Maps to a QuickBooks class. |
| `Transaction` | ✅ Built (scaffold) | Low-level money record. Every donation produces a transaction. |
| QuickBooks sync | ⬜ Deferred | Session 005+. |
| Stripe webhooks | ⬜ Deferred | Session 005+. |

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
│  Contacts          (heroicon-o-users)               sort: 1
│  Organizations     (heroicon-o-building-office)     sort: 2
│  Memberships       (heroicon-o-identification)      sort: 3
│  Tags              (heroicon-o-tag)                 sort: 4
│  Notes             (heroicon-o-chat-bubble-left-ellipsis)  sort: 5
└────────────────────────────────────┘

┌─ Content ──────────────────────────┐
│  Pages             (heroicon-o-document-text)       sort: 1
│  Posts             (heroicon-o-newspaper)           sort: 2
│  Navigation        (heroicon-o-bars-3)              sort: 3
└────────────────────────────────────┘

┌─ Finance ──────────────────────────┐
│  Donations         (heroicon-o-heart)               sort: 1
│  Campaigns         (heroicon-o-megaphone)           sort: 2
│  Funds             (heroicon-o-banknotes)           sort: 3
│  Transactions      (heroicon-o-receipt-percent)     sort: 4
└────────────────────────────────────┘

┌─ Settings ─────────────────────────┐
│  Users             (heroicon-o-user-circle)         sort: 1
└────────────────────────────────────┘
```

Group sort order: CRM → Content → Finance → Settings.

---

## Planned Future Additions

| Domain | Entity | Notes |
|--------|--------|-------|
| CRM | Event | Has a page. Attended by contacts. |
| CRM | EventRegistration | Contact ↔ Event junction. Optional fee. |
| CRM/Auth | Contact ↔ User link | Required for member portal gating. |
| Content | Media Library UI | Spatie Media Library management in admin. |
| Content | Post Categories & Tags | Taxonomy for blog. |
| Content | Form Builder | Public-facing forms (newsletter, contact, donations). |
| Finance | QuickBooks API | Outbound sync from Transaction mirror. |
| Finance | Stripe Webhooks | Keeps Transaction mirror current. |
| Finance | Grant module | Funder, Grant, GrantAllocation, GrantReport. |
| Finance | Tax Receipts | Annual giving statements. |
