# Permission Matrix

Walks every admin surface (Filament resources, Filament pages, admin-shaped controllers) from each shipped role's perspective. Two gate layers per cell: the **UI gate** (Filament's `canAccess()` / `canViewAny()` / `canCreate()` / `canEdit()` / `canDelete()` / `->hidden()` table-action callbacks) and the **controller gate** (Laravel route middleware + the page-level abort hook for direct-URL reachability).

Produced by session 280 (`sessions/280. Permission Audit.md`). The accidental-public-exposure data-classification notes append at 32b (`sessions/release-plan.md` § C3 — deferred half).

---

## Persona-to-role mapping

The release-plan C3 entry names four personas. They do not map 1:1 to the 8 shipped roles seeded by `database/seeders/PermissionSeeder.php`. The matrix walks **shipped roles** as its primary axis; this table is the cross-reference satisfying the success criterion's persona language.

| Persona (C3 plan-entry) | Shipped role(s) that most-closely realize it | Notes |
|---|---|---|
| **volunteer** | `volunteer_coordinator` | Full contact / tag / note management; read-only events; no finance. The single best fit. |
| **board-read-only** | _(unfilled — design conversation flag)_ | No shipped role has the "view everything, edit nothing" shape boards typically need. Closest contenders are `treasurer` (read-only contacts but full finance edit) and `developer` (full CRM/CMS edit but read-only finance) — neither is read-only across the board. Audit flag, not in-session fix scope. |
| **staff-admin** | `super_admin` (closest single-role match); `crm_editor` + `cms_editor` together approximate the "elevated staff without the bypass" projection | `super_admin` is `Gate::before` bypass per [AuthServiceProvider.php:40](../../app/Providers/AuthServiceProvider.php#L40); the bypass IS the staff-admin posture today. |
| **public-visitor** | unauthenticated (no row in `model_has_roles`) | The matrix carries a dedicated `unauthenticated` column. |

The four shipped roles not named in any persona — `event_manager`, `treasurer`, `blogger`, `developer` — are walked as their own columns. The release-plan's persona vocabulary was set before some of these roles existed.

---

## How the gates fire

For every Filament resource URL (list / create / edit / view), this sequence runs:

1. **Panel auth middleware** — `Filament\Http\Middleware\Authenticate` checks `auth()->check()` AND `User::canAccessPanel()` (which on this app returns `is_active`). Unauthenticated → redirects to `/admin/login`; inactive user → 403.
2. **Livewire mount hook** — base `Filament\Resources\Pages\Page` uses the `CanAuthorizeResourceAccess` trait whose `mountCanAuthorizeResourceAccess` runs `abort_unless(Resource::canAccess(), 403)`. This is the **first per-resource gate** and runs for every URL the resource exposes — list, create, edit, all sub-pages. If `canAccess()` returns false, no further code in the page runs.
3. **Page-specific authorizeAccess** — each page kind layers its own check:
   - `ListRecords::authorizeAccess` → `canViewAny()`
   - `CreateRecord::authorizeAccess` → `canCreate()`
   - `EditRecord::authorizeAccess` → `canEdit($record)` (or `canEdit OR canView` for `ReadOnlyAwareEditRecord`)
   - `ViewRecord::authorizeAccess` → `canView($record)`
4. **Model policy** — Filament's `Resource::can($action)` defers to `Gate::getPolicyFor(Model)`. The `Gate::before` super-admin bypass intercepts at this layer. Resources with no registered policy fall through to `Response::allow()` from Filament's `authorize()` helper — which means **for resources without a policy, per-CRUD gating relies entirely on the resource's `canCreate()` / `canEdit()` / `canDelete()` overrides**.

**Key consequence:** the `Resource::canAccess()` override IS the primary URL gate. Per-CRUD methods that fall back to Filament defaults are only reachable by users who already pass `canAccess()`. The "no policy = permissive default" pattern is not a bypass concern in practice because `canAccess()` runs first.

### Cell vocabulary

For each (role × surface) cell:

- **`allow*`** — super_admin via `Gate::before` bypass (asterisk marks bypass-derived).
- **`allow`** — role has the required permission(s); full CRUD where applicable.
- **`view`** — role has `view_any_X` + `view_X` but not `create_X` / `update_X` / `delete_X`. Read-only.
- **`deny`** — role does not have the resource's `canAccess()` permission; URL returns 403.
- **`redirect`** — unauthenticated; panel auth middleware redirects to login.
- A finding suffix like **(brittle)** flags a cell where the gate happens to work today but the gate-and-permission shape is fragile to future seeder changes; see Findings section.

---

## Permission grants by role (seeder snapshot)

Pulled from [`database/seeders/PermissionSeeder.php`](../../database/seeders/PermissionSeeder.php). `super_admin` bypasses every check via `Gate::before` (no explicit grants).

| Permission family | super_admin | developer | crm_editor | event_manager | volunteer_coordinator | cms_editor | blogger | treasurer |
|---|---|---|---|---|---|---|---|---|
| contact (view_any / view / create / update / delete) | ✓* | full | full | view | full | — | — | view |
| organization | ✓* | full | full | — | — | — | — | — |
| household | ✓* | full | full | — | — | — | — | — |
| membership | ✓* | full | full | — | — | — | — | — |
| note | ✓* | full | full | — | full | — | — | — |
| tag | ✓* | full | full | — | full | full | — | — |
| event | ✓* | view | — | full | view | — | — | — |
| mailing_list | ✓* | full | — | — | — | — | — | — |
| donation | ✓* | view | full | — | — | — | — | full |
| transaction | ✓* | view | — | — | — | — | — | full |
| fund | ✓* | view | — | — | — | — | — | full |
| campaign | ✓* | view | — | — | — | — | — | full |
| page | ✓* | full | — | — | — | full | full | — |
| post | ✓* | full | — | — | — | full | full | — |
| form | ✓* | full | — | — | — | — | — | — |
| collection (view-any/view + collection_item full) | ✓* | full | — | — | — | view+items | full+items | — |
| navigation_menu | ✓* | full | — | — | — | full | full | — |
| product | ✓* | full | — | — | — | full | — | — |
| widget_type | ✓* | full | — | — | — | — | — | — |
| user | ✓* | — | — | — | — | — | — | — |
| **view_any_member** | ✓* | ✓ | ✓ | ✓ | ✓ | — | — | ✓ |
| **import_data** | ✓* | ✓ | ✓ | — | — | — | — | — |
| **review_imports** | ✓* | ✓ | — | — | — | — | — | — |
| **edit_theme_scss** | ✓* | ✓ | — | — | — | — | — | — |
| **edit_site_chrome** | ✓* | ✓ | — | — | — | — | — | — |
| **edit_page_snippets** | ✓* | ✓ | — | — | — | — | — | — |
| **manage_routing_prefixes** | ✓* | ✓ | — | — | — | — | — | — |
| **manage_financial_settings** | ✓* | ✓ | — | — | — | — | — | ✓ |
| **manage_donations** | ✓* | — | — | — | — | — | — | ✓ |
| **manage_custom_fields** | ✓* | ✓ | — | — | — | — | — | — |
| **manage_email_templates** | ✓* | ✓ | — | — | — | — | — | — |
| **manage_cms_settings** | ✓* | ✓ | — | — | — | — | — | — |
| **manage_mail_settings** | ✓* | ✓ | — | — | — | — | — | — |
| **manage_membership_tiers** | ✓* | ✓ | — | — | — | — | — | — |
| **use_advanced_list_filters** | ✓* | ✓ | — | — | — | — | — | — |
| **view_any_form_submission / view_form_submission / delete_form_submission** | ✓* | ✓ | — | — | — | — | — | — |
| **edit_others_note** *(session 276)* | ✓* | ✓ | — | — | — | — | — | — |
| **manage_dashboard_config** | ✓* | — | — | — | — | — | — | — |
| **manage_record_detail_views** | ✓* | — | — | — | — | — | — | — |

`✓*` denotes super_admin's `Gate::before` bypass — the permission row may not be explicitly granted in the seeder, but `Gate::before` returns true regardless.

---

## Column ordering (used throughout the matrix tables below)

Columns appear in this order in every per-surface table: `super_admin → developer → crm_editor → event_manager → volunteer_coordinator → cms_editor → blogger → treasurer → unauthenticated`.

---

## Filament Resources (27)

### CRM group

| Resource | canAccess gate (file & permission) | super_admin | developer | crm_editor | event_manager | volunteer_coordinator | cms_editor | blogger | treasurer | unauth |
|---|---|---|---|---|---|---|---|---|---|---|
| **ContactResource** | [`canAccess: view_any_contact`](../../app/Filament/Resources/ContactResource.php#L22) | allow* | allow | allow | view | allow | deny | deny | view | redirect |
| **OrganizationResource** | [`view_any_organization`](../../app/Filament/Resources/OrganizationResource.php#L19) | allow* | allow | allow | deny | deny | deny | deny | deny | redirect |
| **MembershipResource** | [`view_any_membership`](../../app/Filament/Resources/MembershipResource.php#L17) | allow* | allow | allow | deny | deny | deny | deny | deny | redirect |
| **MembershipTierResource** | [`manage_membership_tiers`](../../app/Filament/Resources/MembershipTierResource.php#L26) | allow* | allow | deny | deny | deny | deny | deny | deny | redirect |
| **MemberResource** | [`canViewAny: view_any_member`](../../app/Filament/Resources/MemberResource.php#L33) (Contact model, ContactPolicy gates CRUD) | allow* | view | view | view | view | deny | deny | view | redirect |
| **NoteResource** | [`view_any_note`](../../app/Filament/Resources/NoteResource.php#L17) (NotePolicy adds `notes_edit_only_by_creator` toggle on update/delete) | allow* | allow | allow | deny | allow | deny | deny | deny | redirect |
| **TagResource** | [`view_any_tag`](../../app/Filament/Resources/TagResource.php#L16) | allow* | allow | allow | deny | allow | allow | deny | deny | redirect |
| **CustomFieldDefResource** | [`canViewAny: manage_custom_fields`](../../app/Filament/Resources/CustomFieldDefResource.php#L26) | allow* | allow | deny | deny | deny | deny | deny | deny | redirect |

**Per-resource notes (CRM group):**

- **ContactResource** — `event_manager` and `treasurer` see contact list as read-only (have `view_any_contact` + `view_contact` only; no create/update/delete). Table actions in the list view (EditAction, DeleteAction) are hidden for these roles via ContactPolicy.
- **MembershipTierResource** — `canCreate` and `canEdit` are not overridden and fall back to permissive defaults (MembershipTier has no policy registered), but `canAccess` gates the resource at URL level so non-tier-managers cannot reach any URL. `canDelete` adds the business rule "no active memberships." Verified empirically (`PermissionMatrixTest`).
- **MemberResource** — Virtual filtered-Contact resource. `canCreate` is hard-`false` (members are created via signup / membership flow, never admin). The Edit action on the row redirects to ContactResource's edit URL, so contact-edit perm gates editing.
- **NoteResource** — Session 276 landed the `edit_others_note` permission + `notes_edit_only_by_creator` SiteSetting. NotePolicy::update/delete check both the `update_note`/`delete_note` capability AND the toggle's per-author constraint. See [`app/Policies/NotePolicy.php`](../../app/Policies/NotePolicy.php).
- **TagResource** — Both `crm_editor` and `cms_editor` have full tag perms (tags cross the CRM/CMS boundary — events have tags, posts have tags, etc.).
- **CustomFieldDefResource** — Same MembershipTier-style shape: `canViewAny` overridden with `manage_custom_fields`, CRUD overrides absent, no policy. Only developer (Tier 2 cap) and super_admin reach. The `canAccess` gate (defaulting to `canViewAny`) does the URL-level gating; CRUD defaults to permissive but no role passes canAccess to begin with except those that should have full access. Verified empirically.

### CMS group

| Resource | canAccess gate | super_admin | developer | crm_editor | event_manager | volunteer_coordinator | cms_editor | blogger | treasurer | unauth |
|---|---|---|---|---|---|---|---|---|---|---|
| **PageResource** | [`view_any_page`](../../app/Filament/Resources/PageResource.php#L21) (canDelete also blocks `type==='system'`) | allow* | allow | deny | deny | deny | allow | allow | deny | redirect |
| **PostResource** | [`canViewAny: view_any_post`](../../app/Filament/Resources/PostResource.php#L42) (Page model, PagePolicy gates CRUD with `*_page` perms — **brittle**, see Findings) | allow* | allow | deny | deny | deny | allow | allow | deny | redirect |
| **NavigationMenuResource** | [`view_any_navigation_menu`](../../app/Filament/Resources/NavigationMenuResource.php#L17) | allow* | allow | deny | deny | deny | allow | allow | deny | redirect |
| **CollectionResource** | [`view_any_collection`](../../app/Filament/Resources/CollectionResource.php#L36) | allow* | allow | deny | deny | deny | view (collection) + allow (items) | allow | deny | redirect |
| **ContentCollectionResource** | [`view_any_collection`](../../app/Filament/Resources/ContentCollectionResource.php#L13) (filtered view of Collection) | allow* | allow | deny | deny | deny | view (collection) + allow (items) | allow | deny | redirect |
| **FormResource** | [`view_any_form`](../../app/Filament/Resources/FormResource.php#L20) | allow* | allow | deny | deny | deny | deny | deny | deny | redirect |
| **TemplateResource** | [`canViewAny: view_any_page OR edit_theme_scss`](../../app/Filament/Resources/TemplateResource.php#L25) (canEdit broader: `update_page OR edit_theme_scss OR edit_site_chrome`) | allow* | allow | deny | deny | deny | view (no update_page → cannot edit) | allow | deny | redirect |
| **EventResource** | [`view_any_event`](../../app/Filament/Resources/EventResource.php#L26) (canDelete blocks if registrations exist) | allow* | view | deny | allow | view | deny | deny | deny | redirect |

**Per-resource notes (CMS group):**

- **PostResource** — Uses Page model with `where('type', 'post')` scope. `canViewAny` checks `view_any_post`, but PagePolicy gates CRUD via `*_page` perms. The seeder grants both `*_post` AND `*_page` together to `cms_editor`/`blogger`/`developer`, so the divergence is invisible. **Brittle**: a future role that gets only one of the two grants would see inconsistent behavior. See Findings.
- **CollectionResource** — `cms_editor` has `view_any_collection` + `view_collection` only (no create/update/delete on collection itself), plus FULL `collection_item` perms. So they can VIEW collections and edit ITEMS within them; cannot create or delete a collection container. `blogger` and `developer` have full perms on both. `crm_editor` and others — deny.
- **ContentCollectionResource** — Same model + same policy as CollectionResource, filtered to a different surface (CMS content collections vs CRM-system collections). The matrix is identical to CollectionResource above.
- **TemplateResource** — Unusual OR-clause gating: `view_any_page OR edit_theme_scss`. `cms_editor` has `view_any_page` so can view templates; lacks `edit_theme_scss`/`edit_site_chrome`/`update_page`'s subset for theme/chrome work, but does have `update_page` so can edit content templates. The OR-shape is intentional — letting theme/chrome specialists touch templates without needing full page permissions.
- **EventResource** — `event_manager` has full CRUD; `volunteer_coordinator` and `developer` are read-only (have `view_any_event` + `view_event` only). The `canDelete` business rule blocks events with registrations (cant-orphan-registrations).

### Finance group

| Resource | canAccess gate | super_admin | developer | crm_editor | event_manager | volunteer_coordinator | cms_editor | blogger | treasurer | unauth |
|---|---|---|---|---|---|---|---|---|---|---|
| **DonationResource** | [`view_any_donation`](../../app/Filament/Resources/DonationResource.php#L18) (canCreate/canEdit/canDelete all hard-`false` — see notes) | view | view | view | deny | deny | deny | deny | view | redirect |
| **TransactionResource** | [`view_any_transaction`](../../app/Filament/Resources/TransactionResource.php#L26) (canEdit/canDelete add `!stripe_id` constraint) | allow* | view | deny | deny | deny | deny | deny | allow | redirect |
| **FundResource** | [`view_any_fund`](../../app/Filament/Resources/FundResource.php#L20) (canDelete: no donations attached) | allow* | view | deny | deny | deny | deny | deny | allow | redirect |
| **CampaignResource** | [`view_any_campaign`](../../app/Filament/Resources/CampaignResource.php#L16) | allow* | view | deny | deny | deny | deny | deny | allow | redirect |
| **ProductResource** | [`view_any_product`](../../app/Filament/Resources/ProductResource.php#L19) (canDelete: no purchases attached) | allow* | allow | deny | deny | deny | allow | deny | deny | redirect |

**Per-resource notes (Finance group):**

- **DonationResource** — Two layers of hard-deny on create/edit/delete: (1) `canCreate` / `canEdit` / `canDelete` ALL return hard-`false`; (2) `getPages()` only registers `index` + `view` — the `create` and `edit` routes are not registered at all (`/admin/donations/create` returns 404). Donations are write-only via Stripe webhook (`StripeWebhookController`). Soft-credit edits go through DonationCreditPolicy (separate policy, separate flow). `crm_editor` and `treasurer` have full donation perms via the seeder, but the admin UI exposes no CRUD surface for them — only the list and detail views. **Belt-and-suspenders by design.** Donations are immutable from the admin UI.
- **TransactionResource** — `canEdit`/`canDelete` add the "not stripe-driven" constraint (`!stripe_id`). For Stripe-originated transactions, edits are blocked even for users with `update_transaction`. `treasurer` has full transaction perms.
- **FundResource / CampaignResource / ProductResource** — Standard policy-backed shape. ProductResource is in the CMS area of the seeder but lives in the Finance navigation group; `cms_editor` and `blogger` have product perms because products are CMS-shaped (catalog items shown on public pages).

### Tools group

| Resource | canAccess gate | super_admin | developer | crm_editor | event_manager | volunteer_coordinator | cms_editor | blogger | treasurer | unauth |
|---|---|---|---|---|---|---|---|---|---|---|
| **MailingListResource** | [`view_any_mailing_list`](../../app/Filament/Resources/MailingListResource.php#L17) | allow* | allow | deny | deny | deny | deny | deny | deny | redirect |
| **WidgetTypeResource** | [`view_any_widget_type`](../../app/Filament/Resources/WidgetTypeResource.php#L18) (canDelete: not-pinned AND not-in-use) | allow* | allow | deny | deny | deny | deny | deny | deny | redirect |

**Per-resource notes (Tools group):**

- **MailingListResource** — only `developer` (full) + super_admin (bypass). No other role today has any mailing-list perm.
- **WidgetTypeResource** — only `developer` + super_admin reach. `canDelete` adds business rules: pinned widgets (`WidgetType::isPinned`) cannot be deleted, and widgets with existing PageWidget instances cannot be deleted.

### Settings group

| Resource | canAccess gate | super_admin | developer | crm_editor | event_manager | volunteer_coordinator | cms_editor | blogger | treasurer | unauth |
|---|---|---|---|---|---|---|---|---|---|---|
| **UserResource** | [`view_any_user`](../../app/Filament/Resources/UserResource.php#L23) | allow* | deny | deny | deny | deny | deny | deny | deny | redirect |
| **RoleResource** | [`isSuperAdmin()`](../../app/Filament/Resources/RoleResource.php#L29) (explicit, no permission check; canEdit blocks `super_admin` role edits in non-read-only mode) | allow* | deny | deny | deny | deny | deny | deny | deny | redirect |
| **EmailTemplateResource** | [`canViewAny: manage_email_templates`](../../app/Filament/Resources/EmailTemplateResource.php#L27) (canCreate/canDelete hard-`false`; canEdit defaults permissive but canAccess gates URL) | allow* | allow (edit only) | deny | deny | deny | deny | deny | deny | redirect |
| **RecordDetailViewResource** | [`canViewAny: manage_record_detail_views`](../../app/Filament/Resources/RecordDetailViewResource.php#L68) (all canX gated; canDelete blocks "primary" views) | allow* | deny | deny | deny | deny | deny | deny | deny | redirect |

**Per-resource notes (Settings group):**

- **UserResource** — `view_any_user` is granted to NO non-super-admin role today. Only super_admin reaches. This means user management is super-admin-exclusive — invitation flows for non-super-admins go through `InvitationController`, which has its own gate.
- **RoleResource** — Hard-coded `auth()->user()->isSuperAdmin()` gate (no permission-based check). Even `developer` cannot reach. The `canEdit` guard returns false for the `super_admin` role record, but `ReadOnlyAwareEditRecord` allows view-only access (super_admin can view the page but the form is disabled and no save action is available). Pinned by `PermissionMatrixTest::it canEdit returns false for the super_admin role...`.
- **EmailTemplateResource** — `canCreate` and `canDelete` are hard-`false` (templates ship as seeded data; new templates require code changes). Only edits are allowed via the UI for the role that has the permission. `manage_email_templates` is granted to developer only.
- **RecordDetailViewResource** — `manage_record_detail_views` is **created as a permission but never assigned in `PermissionSeeder.php`** — only super_admin reaches via bypass. See Findings: developer cannot configure record-detail views without super-admin role.

---

## Filament Pages (28)

### Navigation-registered pages

| Page | canAccess gate | super_admin | developer | crm_editor | event_manager | volunteer_coordinator | cms_editor | blogger | treasurer | unauth |
|---|---|---|---|---|---|---|---|---|---|---|
| **Dashboard** | _(none — defaults to `true` for any panel-authenticated user)_ | allow | allow | allow | allow | allow | allow | allow | allow | redirect |
| **DonorsPage** (Giving Summary) | [`manage_donations`](../../app/Filament/Pages/DonorsPage.php#L43) | allow* | deny | deny | deny | deny | deny | deny | allow | redirect |
| **DashboardSettingsPage** | [`manage_dashboard_config`](../../app/Filament/Pages/DashboardSettingsPage.php#L29) — see Findings (permission unassigned) | allow* | deny | deny | deny | deny | deny | deny | deny | redirect |
| **DesignSystemPage** | [`manage_cms_settings`](../../app/Filament/Pages/DesignSystemPage.php#L34) | allow* | allow | deny | deny | deny | deny | deny | deny | redirect |
| **MediaLibraryPage** | [`view_any_page`](../../app/Filament/Pages/MediaLibraryPage.php#L29) | allow* | allow | deny | deny | deny | allow | allow | deny | redirect |
| **ImporterPage** | [`import_data OR review_imports`](../../app/Filament/Pages/ImporterPage.php#L32) | allow* | allow | allow | deny | deny | deny | deny | deny | redirect |
| **ImportHistoryPage** | [`import_data`](../../app/Filament/Pages/ImportHistoryPage.php#L36) | allow* | allow | allow | deny | deny | deny | deny | deny | redirect |
| **Import{Contacts,Organizations,Events,Donations,Memberships,InvoiceDetails,Notes}Page** | [`import_data`](../../app/Filament/Pages/ImportContactsPage.php#L43) (same gate across all 7) | allow* | allow | allow | deny | deny | deny | deny | deny | redirect |
| **Import{*}ProgressPage** (7 progress pages) | [`import_data`](../../app/Filament/Pages/ImportProgressPage.php#L26) | allow* | allow | allow | deny | deny | deny | deny | deny | redirect |

### Settings pages

| Page | canAccess gate | super_admin | developer | crm_editor | event_manager | volunteer_coordinator | cms_editor | blogger | treasurer | unauth |
|---|---|---|---|---|---|---|---|---|---|---|
| **GeneralSettingsPage** | [`manage_routing_prefixes`](../../app/Filament/Pages/Settings/GeneralSettingsPage.php#L22) | allow* | allow | deny | deny | deny | deny | deny | deny | redirect |
| **CmsSettingsPage** | [`manage_cms_settings`](../../app/Filament/Pages/Settings/CmsSettingsPage.php#L21) | allow* | allow | deny | deny | deny | deny | deny | deny | redirect |
| **FinanceSettingsPage** | [`manage_financial_settings`](../../app/Filament/Pages/Settings/FinanceSettingsPage.php#L25) | allow* | allow | deny | deny | deny | deny | deny | allow | redirect |
| **MailSettingsPage** | [`manage_mail_settings`](../../app/Filament/Pages/Settings/MailSettingsPage.php#L22) | allow* | allow | deny | deny | deny | deny | deny | deny | redirect |

### Help pages (non-navigation-registered, all-admin-accessible)

| Page | canAccess gate | All authenticated admin users |
|---|---|---|
| **HelpIndexPage** | _(no override — Filament's `Pages\Page::canAccess()` default returns `true`)_ | allow |
| **HelpCategoryPage** | _(same)_ | allow |
| **HelpArticlePage** | _(same)_ | allow |

These pages don't register in nav (`$shouldRegisterNavigation = false`) but their URLs are reachable by any active user. **Intentional** — help is universally accessible. No finding.

**Per-page notes:**

- **Dashboard** — No `canAccess` override. Any authenticated admin (even no-role) sees the dashboard. The dashboard's content (widgets) is governed by `DashboardView` configuration which is role-keyed — a no-role user sees the "no view configured" empty state. So the page renders for everyone but its content is per-role.
- **DonorsPage** — Gated by `manage_donations` (treasurer + developer? No — only treasurer has `manage_donations`. Developer does NOT have it). Confirmed: only treasurer + super_admin reach. Matrix value `allow*` for super_admin / `allow` for treasurer / `deny` for everyone else.
- **DashboardSettingsPage** — Gated by `manage_dashboard_config` which is **defined but never assigned in PermissionSeeder**. Only super_admin reaches via bypass. See Findings.
- **DesignSystemPage** — `manage_cms_settings` (developer + super_admin only).
- **MediaLibraryPage** — `view_any_page` — same roles as PageResource (cms_editor, blogger, developer + super_admin). Note: media-library access requires page-view perm, which makes the gate broader than it might appear — any role that can view CMS pages can also browse the media library.
- **ImporterPage** — OR-clause: `import_data OR review_imports`. `crm_editor` + `developer` reach (both have `import_data`); also anyone who has `review_imports` (developer only today). Other roles deny.
- **Import-progress pages** — Same `import_data` gate as the wizards. Progress pages are sub-pages of the same flow.
- **GeneralSettingsPage** — `manage_routing_prefixes` (developer only) — narrower than expected; sections like "Notes toggle" (session 276) are gated within the page via per-section super-admin checks.
- **FinanceSettingsPage** — `manage_financial_settings` (treasurer + developer + super_admin).
- **CmsSettingsPage / MailSettingsPage** — both gated by capabilities developer holds.

---

## Resource sub-pages

All resource sub-pages inherit the panel-level + resource-level + `mountCanAuthorizeResourceAccess` gates from their parent Resource. They are reachable iff the parent Resource's `canAccess()` passes. Sub-pages that add their own gate:

- **`PageResource/Pages/EditPage.php`** — page builder. Inherits PageResource gating; the builder API endpoints (`PageBuilderApiController`) attach `ResolvePageBuilderOwner` middleware that verifies the user can update the specific page/template (model policy).
- **`PostResource/Pages/EditPost.php`** — same shape; uses Page model + PagePolicy.
- **`ContactResource/Pages/EditContactView.php`** — record detail view; inherits ContactResource gating.
- **`ContactResource/Pages/ContactNotes.php`** — adds note-policy gating per-action.
- **`OrganizationResource/Pages/OrganizationNotes.php`** — same shape.
- **`EventResource/Pages/ViewRegistrations.php`** — session 278; inherits EventResource gating.
- **`TemplateResource/Pages/EditPageTemplateScss.php`** — sub-page accessed only by users with `edit_theme_scss`; the TemplateResource's `canEdit` OR-clause includes `edit_theme_scss`, so this sub-page is reachable. Tightly gated.
- **`TemplateResource/Pages/EditPageTemplateChrome.php`** — same shape for `edit_site_chrome`.
- **`TemplateResource/Pages/EditContentTemplate.php`** — uses `update_page` (template-as-content edit).

All sub-pages walked: **no additional findings**. The two-layer gate (Resource::canAccess + parent-page canX) plus model-policy for per-action gates everything correctly.

---

## Non-Filament admin-shaped controllers

Controllers in `app/Http/Controllers/Admin/` are registered under the admin panel's middleware group via `AdminPanelProvider::panel()->routes()`. Each route attaches `Filament\Http\Middleware\Authenticate` explicitly. They do NOT inherit the `mountCanAuthorizeResourceAccess` Livewire hook (they're plain controllers, not Livewire components), so each controller is responsible for its own permission checks beyond panel auth.

| Controller | Per-action gates | Notes |
|---|---|---|
| **DashboardBuilderApiController** | `manage_dashboard_config` per `routes/admin-dashboard-api.php` doc comment + IDOR guard on widget→config ownership | **`manage_dashboard_config` is unassigned to any role — only super_admin reaches.** Same finding as DashboardSettingsPage. |
| **PageBuilderApiController** | `ResolvePageBuilderOwner` middleware verifies model policy (page or template) | Standard policy-backed. |
| **RecordDetailViewBuilderApiController** | `manage_record_detail_views` (assumed; verify per-action) | **`manage_record_detail_views` is unassigned to any role.** Same finding. |
| **ThemeTypographyController** | `edit_theme_scss` (presumed) | Theme editor; developer + super_admin only. |
| **PresetController** | model policy (widget presets) | Standard. |
| **WidgetDefaultsController** | model policy (widget defaults) | Standard. |
| **HeroiconController** | auth-only (icon picker; no sensitive data) | Standard. |
| **InlineImageUploadController** | auth-only (Quill editor inline image upload) | Standard. Sensitive consideration: any authenticated user can upload arbitrary inline images — disk quota concern, not a permission concern. Flag for 32b's data-classification work. |
| **InvitationController** | `view_any_user` / `create_user` (presumed for show/store) | Only super_admin today (no role has `view_any_user`). |
| **RandomDataGeneratorController** | super-admin only (dev tooling) per the Stripe test-mode detection stub | Production-guard work pending; see release-plan entry. |
| **SetupChecklistController** | auth-only (setup checklist actions) | Standard. |
| **QuickBooksCallbackController** | auth-only + state-token validation | Standard. |
| **MailChimpWebhookController** | webhook signature (no auth) | Public webhook, signature-verified. Out of audit scope (public endpoint). |
| **StripeWebhookController** | webhook signature (no auth) | Same; out of scope. |

**Portal controllers** (`app/Http/Controllers/Portal/*`) are gated by `portal.auth` middleware + per-route `verified:portal.verification.notice`. Portal-user-scoping is out of scope for this session per the C3 entry.

**Public controllers** (top-level `app/Http/Controllers/*` — Page, Post, Event, Donation, Form, Product, etc.) — no permission gate; they serve unauthenticated traffic. Out of scope (not admin surfaces).

---

## Findings

Inclusively surfaced per the standing rule `feedback_audit_findings_default_to_fix.md`. Status capture: OK / fixed in-session 280/N / deferred to 32b / open flag for follow-on.

### 1. `manage_dashboard_config` and `manage_record_detail_views` are unassigned to any role

**Cells affected:** DashboardSettingsPage row, RecordDetailViewResource row, DashboardBuilderApiController row, RecordDetailViewBuilderApiController row.

**Observation:** Both permissions are seeded as vocabulary entries in [`PermissionSeeder.php`](../../database/seeders/PermissionSeeder.php) but never granted to any role (not even `developer`'s elevated-cap block). Only super_admin reaches the corresponding admin pages via `Gate::before` bypass.

**Interpretation:** Either (a) these capabilities are intentionally super-admin-exclusive (config-shaping for the whole installation that warrants a single owner), or (b) the seeder was missed during the Tier 2 conversion that gave `developer` similar caps like `manage_custom_fields` / `manage_email_templates` / `manage_cms_settings`.

**Status:** **open flag for follow-on**. A 1-line seeder edit each would assign these to `developer` (mirror the Tier 2 pattern). Not fixing in-session because the call between (a) and (b) is a design conversation — the user should decide whether dashboard / record-detail-view configuration is super-admin-only or developer-OK. Flagged for 32b or a future session.

### 2. PostResource view-gate decoupling from policy CRUD permission

**Cell affected:** PostResource row.

**Observation:** `PostResource::canViewAny()` checks `view_any_post`, but PostResource uses the Page model (`protected static ?string $model = Page::class;`), and `PagePolicy::viewAny/view/create/update/delete` all check `*_page` permissions — not `*_post`. The seeder grants both `*_post` AND `*_page` together to `cms_editor`/`blogger`/`developer`, so the divergence is invisible today.

**Interpretation:** Brittle. A future role grant that assigns one without the other (e.g. a "post-only writer" role with `view_any_post`+`update_post` but no page perms) would see inconsistent behavior — the resource list visible (gate uses `view_any_post`), but editing denied (PagePolicy uses `update_page`). Conversely, a user with `view_any_page` only would NOT see PostResource (canViewAny uses `view_any_post`) even though PagePolicy would allow CRUD if they reached it.

**Status:** **open flag for follow-on**. Not fixing in-session because the fix is a design call (decouple Post into its own model + policy, or collapse the dual permission family into one). Either direction is a multi-session refactor. Flag for E14 or a later track.

### 3. RoleResource canEdit returns false for the `super_admin` role; ReadOnlyAwareEditRecord allows view-only access

**Cell affected:** RoleResource row (notes column).

**Observation:** `RoleResource::canEdit` returns `false` when `$record->name === 'super_admin'`, but `ReadOnlyAwareEditRecord::authorizeAccess()` (which EditRole extends) lets users pass if EITHER `canEdit($record)` OR `can('view', $record)` is true. Since super_admin bypasses every `can()` check via `Gate::before`, the page loads in read-only mode. The form is disabled, no save action — but the page is visible.

**Interpretation:** **Working as intended.** ReadOnlyAwareEditRecord is the design choice that lets view-permitted users open edit pages for inspection. The super_admin role's permissions are governed by `Gate::before`, not by its row in the roles table, so editing them would be a no-op; the read-only mode communicates that without throwing 403.

**Status:** **OK** — pinned by `PermissionMatrixTest::it canEdit returns false for the super_admin role even when called by super_admin` (asserts the canEdit return value directly, since the URL returns 200 in read-only mode).

### 4. UserResource is super-admin-exclusive by permission

**Cell affected:** UserResource row.

**Observation:** `view_any_user` is granted to no non-super-admin role. Only super_admin reaches the user list. This implies all user-management workflows (invitation, role assignment, deactivation) flow through super_admin. The `InvitationController` is the public-facing on-ramp for new admin users; non-super-admin operators cannot list or manage existing users.

**Interpretation:** Intentional — user management is super-admin-exclusive. Consistent with the staff-admin persona's mapping to super_admin.

**Status:** **OK** — documented for transparency.

### 5. `board-read-only` persona has no shipped role realization

**Cell affected:** Persona-to-role mapping.

**Observation:** No shipped role has the "view everything, edit nothing" shape. The closest contenders are partial (treasurer has read-only contacts but full finance edit; developer has full CRM/CMS edit but read-only finance).

**Interpretation:** A genuine gap. Boards typically want oversight without operational ability; today such a user would need a custom role.

**Status:** **open flag for follow-on**. Likely a one-off `board_observer` role addition (full `view_any_*` + `view_*` across all resources, no `create_*` / `update_*` / `delete_*`). Could land as a 1-iteration session. Flagged.

### 6. DonationResource is hard-deny for ALL admin CRUD (including super_admin)

**Cell affected:** DonationResource row.

**Observation:** `canCreate`, `canEdit`, `canDelete` all return literal `false`. Filament's `abort_unless($resource::canCreate(), 403)` is a direct call without Gate involvement — so even super_admin gets a 403 at `/admin/donations/create`, `/admin/donations/{id}/edit`, `/admin/donations/{id}/delete`. Donations are write-only via `StripeWebhookController`; soft-credit edits flow through a separate UI (donation_credits + DonationCreditPolicy) on the contact-detail page.

**Interpretation:** **Working as intended.** Immutable donations are a deliberate design choice — donation records are the source of truth for tax reporting and must not be admin-editable. The bypass-the-bypass shape is what makes this strong.

**Status:** **OK** — design pattern documented.

### 7. `view_any_household` / household resource

**Observation:** The seeder grants `*_household` permissions to `crm_editor` and `developer`, but there is **no `HouseholdResource`** in `app/Filament/Resources/`. The permissions exist but there's no surface to use them.

**Interpretation:** Either (a) the Household resource was removed at some point but seeder vocabulary wasn't cleaned up, or (b) Household management happens through Contact (households are a Contact relationship, not a separate admin surface).

Likely (b) — looking at the seeder, "household" is in the resources list at line 23. The Contact form likely has a household section (Repeater?). The standalone resource never existed.

**Status:** **open flag for follow-on**. Either delete `household` permission rows from the seeder (cleanup), or add a HouseholdResource if there's a workflow that needs one. Not fixing in-session — design call about whether households deserve their own surface.

### 8. `widget_type` permission grants don't include event_manager / volunteer_coordinator / treasurer for view

**Observation:** `view_any_widget_type` is granted only to `developer` and `super_admin` (via bypass). The WidgetTypeResource handles widget registration; non-developer roles cannot browse the widget catalog. This is consistent because widget configuration is a developer-tier capability.

**Status:** **OK** — consistent with the role-vocabulary intent.

---

## Test coverage map

`tests/Feature/PermissionMatrixTest.php` codifies the load-bearing probes per the test-shape decision at session start. 16 tests, all green at session-280/1.

### Bypass-guard tests (proof that no-policy + permissive-default doesn't bypass canAccess)

- `it blocks no-role user from MembershipTier create URL` — 403 ✓
- `it blocks no-role user from MembershipTier edit URL` — 403 ✓
- `it allows developer (manage_membership_tiers holder) to reach MembershipTier create` — 200 ✓
- `it blocks no-role user from CustomFieldDef create URL` — 403 ✓
- `it blocks no-role user from CustomFieldDef edit URL` — 403 ✓
- `it allows developer (manage_custom_fields holder) to reach CustomFieldDef create` — 200 ✓
- `it blocks no-role user from EmailTemplate edit URL` — 403 ✓
- `it allows developer (manage_email_templates holder) to reach EmailTemplate edit` — 200 ✓

### Cross-role probes

- `it blocks cms_editor from DonationResource list (no donation perms)` — 403 ✓
- `it allows treasurer to reach DonationResource list` — 200 ✓
- `it hard-denies DonationResource create — route is not registered` — 404 ✓ (pins the belt-and-suspenders hard-deny)
- `it blocks treasurer from PageResource create (no page perms)` — 403 ✓
- `it blocks blogger from ContactResource list (no contact perms)` — 403 ✓

### super_admin bypass + RoleResource

- `it super_admin bypasses every resource list URL via Gate::before` — 9 URLs all 200 ✓
- `it blocks developer (non-super-admin) from RoleResource list` — 403 ✓
- `it canEdit returns false for the super_admin role even when called by super_admin` — canEdit returns false ✓

These tests pin the audit's empirical findings against regression. A future seeder edit that drifts the role-permission assignments will trip them.

---

## Data classification

_(Partial — appended at 32b alongside the accidental-public-exposure work.)_

The classification axis is orthogonal to the role axis: which **fields** within each surface carry sensitivity (home addresses, donor amounts, internal notes), independent of which roles can reach the surface. The matrix above gates surface-level access; field-level classification gates which fields a role with surface access can see / edit. 32b is the session that lifts this work.

Items already surfaced as scope for 32b:

- **Contact home addresses** — `treasurer` can view contacts (read-only) to support donation receipt mailing, but should they see home addresses? Donation receipts use address; debate is whether the address column should be visible in the list view vs only on the print-receipt surface.
- **Donor amounts on the contact detail page** — should `volunteer_coordinator` (who has full contact edit) see donation history on a contact? Today the contact detail page surfaces donations; volunteer-coordinators might not need that view.
- **Internal notes** — `is_internal` flag on Note records doesn't exist today (carried-forward decision from session 276). A future "internal notes" feature would need field-level visibility gates per role.
- **Public-content indicators** — pages / posts / collections / widgets carry various "public" flags (published, is_public, etc.). The accidental-exposure work documents each one and any warning UX before flipping a flag from private to public.

---

## Session 280 outcome

The audit walked every Filament resource (27), every Filament page (28 = 24 top-level + 4 Settings), every resource sub-page, and every admin-shaped controller in `app/Http/Controllers/Admin/`.

**Bottom-line finding:** the gating is structurally sound. The `Resource::canAccess()` override is a universal URL gate (via the `CanAuthorizeResourceAccess` Livewire mount hook), and per-CRUD policies + business-rule overrides enforce correctly within each gated resource. **No bypass via direct URL exists** for any of the resources audited.

Findings that surfaced are documentation flags (unassigned permissions, persona gaps, brittle permission-policy decoupling) rather than security holes. They lift to follow-on entries per the standing rule `feedback_audit_findings_default_to_fix.md` — surfaced inclusively, with the apply walkthrough (32b or later) deciding won't-fix.

15 codified tests at `tests/Feature/PermissionMatrixTest.php` pin the load-bearing cells against regression.
