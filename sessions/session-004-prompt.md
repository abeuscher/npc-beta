# Session 004 Prompt — Admin Information Architecture

## Context

Session 003 built the Page model, Filament PageResource, public routing, Blade layout (Alpine only, optional Pico, `@stack('styles')`), and DatabaseSeeder. The admin at `/admin` now has two navigation groups: **CRM** (Contact) and **Content** (Page).

Session 004 establishes the complete information architecture for the admin. That means:
- Defining all three primary domains and their nav groups
- Identifying the overlapping data that crosses domain lines
- Creating model + migration stubs for all major entities
- Creating scaffold Filament resources for each
- Writing the IA as a living document
- Making the component strategy decision for the admin side

---

## The Three Domains

This application covers three functional domains. Each maps to a Filament navigation group.

### CRM — Constituent Relationship Management

Tracks people and organizations: who they are, what they've done, what they owe, what they've given. The center of everything.

**Entities:**
| Model | Description |
|-------|-------------|
| `Contact` | A person. Already exists. The central record. |
| `Organization` | A company, nonprofit, foundation, or other entity. Contacts belong to organizations. |
| `Membership` | A contact's membership status, tier, and dates. One active membership per contact at a time. |
| `Tag` | Flat label for segmenting contacts and organizations (e.g. "board member", "major donor", "volunteer") |
| `Note` | A timestamped log entry attached to a contact or organization (activity stream) |

**Future CRM (not session 004):** Events and EventRegistrations live conceptually here once built — an event is attended by contacts. Defer until session 005 or later.

### Content — CMS

Manages what the public website displays. Every content object either renders at a URL or feeds data into a page.

**Entities:**
| Model | Description |
|-------|-------------|
| `Page` | A static or semi-static web page. Already exists. |
| `Post` | A news article or blog entry. Dated, authored, categorized. |
| `NavigationItem` | A menu entry: label, URL or slug, parent, sort order. Drives the public site header. |

**Future Content (not session 004):** Media library UI, form builder, gated content tiers. Defer.

### Finance — Accounting and Fundraising

Tracks money moving in and out. Connects to QuickBooks. Feeds donation receipts.

**Entities:**
| Model | Description |
|-------|-------------|
| `Donation` | A single contribution. Belongs to a contact. Has an amount, date, method, and fund. |
| `Campaign` | A named fundraising drive (e.g. "Annual Fund 2026", "Capital Campaign"). Donations roll up to it. |
| `Fund` | An internal accounting bucket (e.g. "General Operating", "Scholarship Fund"). Maps to a QuickBooks class. |
| `Transaction` | The low-level money record: amount, direction (in/out), status, Stripe ID if applicable, QuickBooks sync status. Every donation produces a transaction. |

**Future Finance (not session 004):** QuickBooks API integration, Stripe webhook handler, grant tracking. Defer.

---

## Where the Domains Overlap

These seams are the most architecturally important decisions in the application.

### The Donor: CRM ∩ Finance

A `Donation` belongs to a `Contact`. The contact record is the authoritative identity. Finance never owns a person — it borrows one from CRM. This means:
- `donations` table has a `contact_id` foreign key
- `ContactResource` should eventually show a donations tab (Filament `RelationManager`)
- A contact may be tagged `donor` automatically when their first donation is recorded

### The Member: CRM ∩ Content

A `Membership` belongs to a `Contact`. Content gating (for a member portal) reads membership status from the CRM. This means:
- `memberships` table has a `contact_id` foreign key
- The `User` (auth) model will need to link to a `Contact` for the member portal to work (future session)
- Content knows nothing about membership business rules — it only asks "is this user's contact record showing an active membership?"

### The Event Attendee: CRM ∩ Content ∩ Finance

Not fully in scope yet, but the seam to plan for:
- An **Event** is a content object (it has a page, a description, a URL)
- An **EventRegistration** is a CRM object (a contact registered)
- If there is a registration fee, it produces a **Transaction** (Finance)
- All three domains contribute. The Event model will sit in Content; the registration and any payment live in CRM/Finance.

---

## Navigation Group Structure

Filament sidebar groups with ordering. Implement via `$navigationGroup` and `$navigationSort` on each resource, and register group ordering in `AdminPanelProvider`.

```
┌─ CRM ──────────────────────────────┐
│  Contacts          (heroicon-o-users)
│  Organizations     (heroicon-o-building-office)
│  Memberships       (heroicon-o-identification)
│  Tags              (heroicon-o-tag)
│  Notes             (heroicon-o-chat-bubble-left-ellipsis)
└────────────────────────────────────┘

┌─ Content ──────────────────────────┐
│  Pages             (heroicon-o-document-text)
│  Posts             (heroicon-o-newspaper)
│  Navigation        (heroicon-o-bars-3)
└────────────────────────────────────┘

┌─ Finance ──────────────────────────┐
│  Donations         (heroicon-o-heart)
│  Campaigns         (heroicon-o-megaphone)
│  Funds             (heroicon-o-banknotes)
│  Transactions      (heroicon-o-receipt-percent)
└────────────────────────────────────┘

┌─ Settings ─────────────────────────┐
│  Users             (heroicon-o-user-circle)
│  Roles             (heroicon-o-shield-check)   ← Filament Shield
└────────────────────────────────────┘
```

Group sort order: CRM → Content → Finance → Settings

---

## Admin Component Strategy — Decision

The question from session 003 was: do we need a component library, and does the same answer apply to both the admin and the public frontend?

**Answer: No additional component library for the admin. Filament is the component library.**

Filament ships with a complete, production-quality set of UI primitives:
- **Form fields**: text, textarea, rich editor, select, checkbox, toggle, date picker, file upload, repeater, builder, etc.
- **Table columns**: text, badge, boolean, image, date, action columns
- **Actions**: modal actions, confirmations, bulk actions
- **Infolists**: read-only detail views with the same layout DSL as forms
- **Notifications**, **modals**, **slide-overs**: all built in
- All of this is already compiled and versioned inside `vendor/filament`

Filament's CSS is its own compiled Tailwind build, separate from any app CSS pipeline. We do not add Tailwind to the app to style the admin — the admin styles itself.

The only time to add something beyond Filament's native set is a custom Filament plugin (e.g. a TipTap rich editor plugin, a chart widget). Those are added per-need, not up front.

**Public frontend:** Unchanged from session 003 decision. Alpine only. Optional Pico. `@stack('styles')`. No component library selected. That decision is deferred until there is actual UI to style.

Write this as `docs/decisions/010-admin-component-strategy.md`.

---

## Tasks

### 1. Register Navigation Groups in AdminPanelProvider

In `app/Providers/Filament/AdminPanelProvider.php`, register the four navigation groups with explicit sort order so the sidebar order is deterministic, not alphabetical:

```php
->navigationGroups([
    NavigationGroup::make('CRM')->icon('heroicon-o-users'),
    NavigationGroup::make('Content')->icon('heroicon-o-document-text'),
    NavigationGroup::make('Finance')->icon('heroicon-o-banknotes'),
    NavigationGroup::make('Settings')->icon('heroicon-o-cog-6-tooth'),
])
```

Also update `ContactResource` and `PageResource` to add `$navigationSort` values matching the table above.

### 2. Organization Model and Resource

**Migration** `create_organizations_table`:
- `id` — UUID
- `name` — string, required
- `type` — string, nullable (e.g. "foundation", "corporate", "government")
- `website` — string, nullable
- `phone` — string, nullable
- `address_line_1`, `address_line_2`, `city`, `state`, `postal_code`, `country` — strings, nullable
- `notes` — text, nullable
- Timestamps, soft deletes

**Update `contacts` table**: add `organization_id` (nullable UUID FK). Update `Contact` model with `belongsTo(Organization::class)`.

**Model** `app/Models/Organization.php`: `HasUuids`, `SoftDeletes`, `HasFactory`, `hasMany(Contact::class)`.

**Resource** `OrganizationResource` in CRM group:
- Form: name (required), type (select: foundation/corporate/government/other), website, phone, address fields (collapsible), notes
- Table: name, type (badge), city, contacts count, updated_at
- Relation manager: `ContactsRelationManager` — inline list of contacts belonging to this org

### 3. Membership Model and Resource

**Migration** `create_memberships_table`:
- `id` — UUID
- `contact_id` — UUID FK, required
- `tier` — string (e.g. "individual", "family", "sustaining", "lifetime")
- `status` — string (active, expired, cancelled, pending) — default "pending"
- `starts_on` — date, nullable
- `expires_on` — date, nullable
- `amount_paid` — decimal(10,2), nullable
- `notes` — text, nullable
- Timestamps, soft deletes

**Model** `app/Models/Membership.php`: `HasUuids`, `SoftDeletes`, `HasFactory`, `belongsTo(Contact::class)`.

Update `Contact` with `hasMany(Membership::class)` and a `activeMembership()` helper that returns the single active record.

**Resource** `MembershipResource` in CRM group:
- Form: contact (searchable select), tier, status, starts_on, expires_on, amount_paid, notes
- Table: contact name (linked), tier, status (badge with color), expires_on, updated_at
- Filter: status

### 4. Tag Model and Resource (Polymorphic)

Tags apply to both contacts and organizations.

**Migration** `create_tags_table`: `id` (UUID), `name` (string, unique), `color` (string, nullable — hex code for badge display), timestamps.

**Migration** `create_taggables_table`: `tag_id`, `taggable_id`, `taggable_type` — polymorphic pivot.

**Model** `app/Models/Tag.php`: `HasUuids`, `morphedByMany` for contacts and organizations.

Update `Contact` and `Organization` with `morphToMany(Tag::class, 'taggable')`.

**Resource** `TagResource` in CRM group (simple):
- Form: name, color (color picker)
- Table: name (with color dot), contacts count, organizations count

### 5. Note Model and Resource (Polymorphic)

**Migration** `create_notes_table`:
- `id` — UUID
- `notable_type`, `notable_id` — polymorphic (points to Contact or Organization)
- `author_id` — UUID FK to users
- `body` — text, required
- `occurred_at` — timestamp, default now
- Timestamps, soft deletes

**Model** `app/Models/Note.php`: `HasUuids`, `SoftDeletes`, `morphTo(notable)`, `belongsTo(User::class, 'author_id')`.

Update `Contact` and `Organization` with `morphMany(Note::class, 'notable')`.

**Resource** `NoteResource` in CRM group:
- This resource is used mainly via relation managers on Contact and Organization, not as a standalone list. The standalone list is still useful for global activity feed.
- Form: body (textarea), occurred_at, notable (polymorphic select — use two fields: type dropdown + id search)
- Table: body (truncated), notable name (linked), author, occurred_at

Add `NotesRelationManager` to both `ContactResource` and `OrganizationResource`.

### 6. Post Model and Resource

**Migration** `create_posts_table`:
- `id` — UUID
- `title` — string, required
- `slug` — string, unique
- `excerpt` — text, nullable
- `content` — longtext
- `author_id` — UUID FK to users
- `is_published` — boolean, default false
- `published_at` — timestamp, nullable
- Meta title, meta description (nullable strings)
- Timestamps, soft deletes

**Model** `app/Models/Post.php`: `HasUuids`, `SoftDeletes`, `HasFactory`, `HasSlug` (Spatie sluggable), `belongsTo(User::class, 'author_id')`.

**Resource** `PostResource` in Content group — same form pattern as PageResource plus excerpt, author select.

### 7. NavigationItem Model and Resource

**Migration** `create_navigation_items_table`:
- `id` — UUID
- `label` — string, required
- `url` — string, nullable (for external links)
- `page_id` — UUID FK to pages, nullable (for internal links)
- `post_id` — UUID FK to posts, nullable (future)
- `parent_id` — UUID FK self-referential, nullable (for nested menus)
- `sort_order` — integer, default 0
- `target` — string, default `_self`
- `is_visible` — boolean, default true
- Timestamps

**Model** `app/Models/NavigationItem.php`: `HasUuids`, `HasFactory`, `belongsTo(Page::class)`, `belongsTo(NavigationItem::class, 'parent_id')`, `hasMany(NavigationItem::class, 'parent_id')`.

**Resource** `NavigationItemResource` in Content group:
- Form: label, type toggle (page link vs. external URL), page select or url input, parent (select existing items), sort order, target, is_visible
- Table: label, type, target page/url (linked), parent, sort_order, is_visible (badge)
- Table should be orderable by sort_order (drag reorder via Filament's `ReorderAction` or sort column)

### 8. Donation, Campaign, Fund, Transaction Stubs

For Finance — build the migrations and models with full columns, but **scaffold-only Filament resources**. Lists and forms should render without error; they do not need to be complete. Full Finance work is session 005 or later.

**`campaigns` table**: `id` (UUID), `name`, `description` (text, nullable), `goal_amount` (decimal, nullable), `starts_on` (date, nullable), `ends_on` (date, nullable), `is_active` (bool, default true), timestamps, soft deletes.

**`funds` table**: `id` (UUID), `name`, `code` (string, unique — QuickBooks class code), `description` (text, nullable), `is_active` (bool, default true), timestamps.

**`donations` table**: `id` (UUID), `contact_id` (UUID FK), `campaign_id` (UUID FK, nullable), `fund_id` (UUID FK, nullable), `amount` (decimal 10,2), `donated_on` (date), `method` (string: cash/check/card/ach/other), `reference` (string, nullable — check number etc.), `is_anonymous` (bool, default false), `notes` (text, nullable), timestamps, soft deletes.

**`transactions` table**: `id` (UUID), `donation_id` (UUID FK, nullable), `type` (string: donation/refund/fee/adjustment), `amount` (decimal 10,2), `direction` (string: in/out), `status` (string: pending/cleared/failed/refunded), `stripe_id` (string, nullable), `quickbooks_id` (string, nullable), `occurred_at` (timestamp), timestamps.

Models: `Campaign`, `Fund`, `Donation`, `Transaction` — all with `HasUuids`, appropriate FKs.

Resources: scaffold only — list + create/edit form with all fields present but no relation managers yet.

### 9. UserResource in Settings Group

Create `app/Filament/Resources/UserResource.php` in the Settings group:
- List: name, email, roles (badge list), is_active (toggle), created_at
- Form: name, email, password (nullable on edit), roles (multi-select via Spatie Permission), is_active toggle
- No delete — deactivate instead (soft approach; hard deletes risk FK integrity)

### 10. Update DatabaseSeeder

Add seeder methods that create representative demo records:
- 3 organizations (one foundation, one corporate, one government)
- 10 contacts (distributed across orgs, a mix with and without memberships)
- 2 tags: "major-donor", "newsletter"
- 1 campaign: "Annual Fund 2026"
- 2 funds: "General Operating", "Scholarship Fund"
- 3 donations (various contacts, campaigns, funds)
- 1 published post with slug `news` (so `/news` works)
- 1 nav item pointing to the home page, 1 pointing to the news post

Wrap everything in `if (app()->environment('local'))` so it won't run in production.

### 11. Documentation

**`docs/information-architecture.md`** — Living document. Should include:
- The three domain table (from this prompt)
- The overlap diagram (Donor, Member, Event Attendee seams)
- Navigation group structure with icons
- The "Contact is the center" principle
- Planned future additions not yet built

**`docs/decisions/010-admin-component-strategy.md`** — Documents the admin component decision: Filament is self-contained, no additional component library, custom plugins added per-need.

**`sessions/session-004-log.md`** — Written at the end of session 004.

---

## What This Session Does Not Cover

- Events and EventRegistrations — session 005
- Stripe webhook handler — later
- QuickBooks API integration — later
- Member portal (public login) — later
- Gated content by membership tier — later
- Form builder / public-facing forms — later
- Media library UI — later
- Post categories and tags — later
- Public routes for Post and NavigationItem (public nav rendering) — session 005

---

## Acceptance Criteria

At the end of session 004:
- [ ] All four navigation groups visible in Filament sidebar, in the correct order
- [ ] All resources listed in the nav group table above render without error
- [ ] Organizations list and form work; contacts can be assigned to an organization
- [ ] Memberships can be created for a contact
- [ ] Tags can be created and assigned to contacts and organizations
- [ ] Notes appear on both contact and organization detail pages
- [ ] Posts resource renders; a post can be created
- [ ] Navigation items can be created and ordered
- [ ] Finance resources (Donation, Campaign, Fund, Transaction) render without error
- [ ] UserResource in Settings group renders and can create/edit users
- [ ] `php artisan db:seed` produces a populated demo dataset
- [ ] `php artisan test` passes
- [ ] `docs/information-architecture.md` exists and is accurate
- [ ] `docs/decisions/010-admin-component-strategy.md` exists
