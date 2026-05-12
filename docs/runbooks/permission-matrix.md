# Permission Matrix

Walks every admin surface (Filament resources, Filament pages, admin-shaped controllers) from each shipped role's perspective. Two gate layers per cell: the **UI gate** (Filament's `canAccess()` / `canViewAny()` / `canCreate()` / `canEdit()` / `canDelete()` / `->hidden()` table-action callbacks) and the **controller gate** (Laravel route middleware + policy enforcement for direct-URL reachability bypassing the UI).

Produced by session 280 (`sessions/280. Permission Audit.md`). The accidental-public-exposure data-classification notes append at 32b (`sessions/release-plan.md` § C3 — deferred half).

---

## Persona-to-role mapping

The release-plan C3 entry names four personas. They do not map 1:1 to the 8 shipped roles seeded by `database/seeders/PermissionSeeder.php`. The matrix below walks the **shipped roles** as its primary axis; this table is the cross-reference that satisfies the success criterion's persona language.

| Persona (C3 plan-entry) | Shipped role(s) that most-closely realize it | Notes |
|---|---|---|
| **volunteer** | `volunteer_coordinator` | Full contact / tag / note management; read-only events; no finance. The single best fit. |
| **board-read-only** | _(unfilled — design conversation flag)_ | No shipped role has the "view everything, edit nothing" shape boards typically need. Closest contenders are `treasurer` (read-only contacts but full finance edit) and `developer` (full CRM/CMS edit but read-only finance) — neither is read-only across the board. Audit flag, not in-session fix scope. |
| **staff-admin** | `super_admin` (closest single-role match); `crm_editor` + `cms_editor` together approximate the "elevated staff without the bypass" projection | `super_admin` is `Gate::before` bypass per [AuthServiceProvider.php:40](../../app/Providers/AuthServiceProvider.php#L40); the bypass IS the staff-admin posture today. |
| **public-visitor** | unauthenticated (no row in `model_has_roles`) | The matrix carries a dedicated `unauthenticated` column. |

The four shipped roles not named in any persona — `event_manager`, `treasurer`, `blogger`, `developer` — are walked as their own columns. The release-plan's persona vocabulary was set before some of these roles existed.

---

## How to read this matrix

Each surface (resource, page, controller-group) has a section with:

- **Path** — the canonical file under `app/Filament/Resources/`, `app/Filament/Pages/`, or `app/Http/Controllers/`.
- **Model + policy** — for resources, the Eloquent model and its `App\Policies` policy class (per `AuthServiceProvider::$policies`).
- **Gate references** — which `canAccess()` / `canViewAny()` / `canCreate()` / `canEdit()` / `canDelete()` callbacks the resource defines, and which it leaves at Filament's default (which delegates to the policy).
- **A per-role status table** — one row per role, columns: UI gate, controller gate, finding/status.

### Cell vocabulary

For each (role × surface × layer) cell:

- **`allow`** — the role is intentionally permitted; the gate (if any) allows them.
- **`deny`** — the role is intentionally denied; the gate enforces the denial.
- **`open`** — the gate would allow this role even though the spec says deny → **finding**.
- **`missing`** — no gate defined at this layer; Filament default is permissive → **finding** for any role expected to be denied; harmless for any role expected to be allowed.
- **`bypass possible`** — controller gate has a known direct-URL path that skips the UI gate → **finding**.

### Status vocabulary

- **`OK`** — both gates enforce the expected behavior; no action.
- **`fixed in-session 280/N`** — finding closed during this session by an inline fix on iteration N.
- **`deferred to 32b`** — finding lifts to the C3-deferred sibling session (concurrent editing + accidental public exposure).
- **`open flag for follow-on`** — finding lifts as a standalone follow-on entry in `sessions/release-plan.md`.

### Panel-level gates (apply to every Filament surface)

Two panel-level gates apply to **every** Filament resource and page; per-resource sections omit them for brevity:

1. **`Filament\Http\Middleware\Authenticate`** is attached to every route registered by the admin panel (`AdminPanelProvider::panel()` — `->authMiddleware([Authenticate::class])` and explicit `->middleware()` calls on every custom-route group). Unauthenticated requests redirect to `/admin/login`. The `unauthenticated` column in every per-resource table inherits this — its cells say `redirect` rather than `allow` / `deny`.
2. **`Gate::before` super-admin bypass** at [AuthServiceProvider.php:40](../../app/Providers/AuthServiceProvider.php#L40). Any `$user->can(...)` call returns `true` for users with the `super_admin` role, regardless of explicit permission grants. The `super_admin` column in every per-resource table inherits this — its cells say `allow (bypass)` for every action that flows through a policy.

---

## Role column ordering

Columns appear in this order in every per-surface table (functional grouping, not alphabetical):

1. **super_admin** — `Gate::before` bypass; allow everything (notation: `allow (bypass)`).
2. **developer** — broad CRM + CMS + read-only finance + elevated standalone capabilities.
3. **crm_editor** — full CRM edit; no finance settings, no user mgmt, no import review.
4. **event_manager** — full event edit; read-only contacts.
5. **volunteer_coordinator** — full contact / tag / note edit; read-only events; no finance.
6. **cms_editor** — full CMS content edit; no CRM / finance / admin.
7. **blogger** — full CMS content edit (overlap with cms_editor); no CRM / finance / admin.
8. **treasurer** — full finance edit; read-only contacts; no CRM editing.
9. **unauthenticated** — public visitor; no model_has_roles row.

---

## Filament Resources (27)

### CRM group

#### ContactResource — [`app/Filament/Resources/ContactResource.php`](../../app/Filament/Resources/ContactResource.php)

- **Model:** `App\Models\Contact` — **Policy:** `App\Policies\ContactPolicy`
- **Gate references:** _(not yet walked)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### OrganizationResource — [`app/Filament/Resources/OrganizationResource.php`](../../app/Filament/Resources/OrganizationResource.php)

- **Model:** `App\Models\Organization` — **Policy:** `App\Policies\OrganizationPolicy`
- **Gate references:** _(not yet walked)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### MembershipResource — [`app/Filament/Resources/MembershipResource.php`](../../app/Filament/Resources/MembershipResource.php)

- **Model:** `App\Models\Membership` — **Policy:** `App\Policies\MembershipPolicy`
- **Gate references:** _(not yet walked)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### MembershipTierResource — [`app/Filament/Resources/MembershipTierResource.php`](../../app/Filament/Resources/MembershipTierResource.php)

- **Model:** `App\Models\MembershipTier` — **Policy:** _(not in `AuthServiceProvider::$policies`)_
- **Gate references:** _(not yet walked)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### MemberResource — [`app/Filament/Resources/MemberResource.php`](../../app/Filament/Resources/MemberResource.php)

- **Model:** `App\Models\Member` — **Policy:** _(virtual resource; uses `view_any_member` standalone permission)_
- **Gate references:** _(not yet walked)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### NoteResource — [`app/Filament/Resources/NoteResource.php`](../../app/Filament/Resources/NoteResource.php)

- **Model:** `App\Models\Note` — **Policy:** `App\Policies\NotePolicy`
- **Gate references:** _(not yet walked — session 276 landed `edit_others_note` + `notes_edit_only_by_creator` toggle)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### TagResource — [`app/Filament/Resources/TagResource.php`](../../app/Filament/Resources/TagResource.php)

- **Model:** `App\Models\Tag` — **Policy:** `App\Policies\TagPolicy`
- **Gate references:** _(not yet walked)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### CustomFieldDefResource — [`app/Filament/Resources/CustomFieldDefResource.php`](../../app/Filament/Resources/CustomFieldDefResource.php)

- **Model:** `App\Models\CustomFieldDef` — **Policy:** _(not in `AuthServiceProvider::$policies`)_
- **Gate references:** _(not yet walked — uses `manage_custom_fields` standalone capability)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

### CMS group

#### PageResource — [`app/Filament/Resources/PageResource.php`](../../app/Filament/Resources/PageResource.php)

- **Model:** `App\Models\Page` — **Policy:** `App\Policies\PagePolicy`
- **Gate references:** _(not yet walked)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### PostResource — [`app/Filament/Resources/PostResource.php`](../../app/Filament/Resources/PostResource.php)

- **Model:** `App\Models\Post` — **Policy:** _(not in `AuthServiceProvider::$policies` — uses generic permission abilities)_
- **Gate references:** _(not yet walked)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### NavigationMenuResource — [`app/Filament/Resources/NavigationMenuResource.php`](../../app/Filament/Resources/NavigationMenuResource.php)

- **Model:** `App\Models\NavigationMenu` — **Policy:** `App\Policies\NavigationMenuPolicy`
- **Gate references:** _(not yet walked)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### CollectionResource — [`app/Filament/Resources/CollectionResource.php`](../../app/Filament/Resources/CollectionResource.php)

- **Model:** `App\Models\Collection` — **Policy:** `App\Policies\CollectionPolicy`
- **Gate references:** _(not yet walked)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### ContentCollectionResource — [`app/Filament/Resources/ContentCollectionResource.php`](../../app/Filament/Resources/ContentCollectionResource.php)

- **Model:** `App\Models\Collection` (filtered view) — **Policy:** `App\Policies\CollectionPolicy` + `CollectionItemPolicy`
- **Gate references:** _(not yet walked)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### FormResource — [`app/Filament/Resources/FormResource.php`](../../app/Filament/Resources/FormResource.php)

- **Model:** `App\Models\Form` — **Policy:** `App\Policies\FormPolicy`
- **Gate references:** _(not yet walked)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### TemplateResource — [`app/Filament/Resources/TemplateResource.php`](../../app/Filament/Resources/TemplateResource.php)

- **Model:** `App\Models\Template` — **Policy:** _(not in `AuthServiceProvider::$policies`)_
- **Gate references:** _(not yet walked — uses `edit_theme_scss` / `edit_site_chrome` standalone capabilities for sub-pages)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### EventResource — [`app/Filament/Resources/EventResource.php`](../../app/Filament/Resources/EventResource.php)

- **Model:** `App\Models\Event` — **Policy:** `App\Policies\EventPolicy`
- **Gate references:** _(not yet walked)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

### Finance group

#### DonationResource — [`app/Filament/Resources/DonationResource.php`](../../app/Filament/Resources/DonationResource.php)

- **Model:** `App\Models\Donation` — **Policy:** `App\Policies\DonationPolicy`
- **Gate references:** _(not yet walked)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### TransactionResource — [`app/Filament/Resources/TransactionResource.php`](../../app/Filament/Resources/TransactionResource.php)

- **Model:** `App\Models\Transaction` — **Policy:** `App\Policies\TransactionPolicy`
- **Gate references:** _(not yet walked)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### FundResource — [`app/Filament/Resources/FundResource.php`](../../app/Filament/Resources/FundResource.php)

- **Model:** `App\Models\Fund` — **Policy:** `App\Policies\FundPolicy`
- **Gate references:** _(not yet walked)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### CampaignResource — [`app/Filament/Resources/CampaignResource.php`](../../app/Filament/Resources/CampaignResource.php)

- **Model:** `App\Models\Campaign` — **Policy:** `App\Policies\CampaignPolicy`
- **Gate references:** _(not yet walked)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### ProductResource — [`app/Filament/Resources/ProductResource.php`](../../app/Filament/Resources/ProductResource.php)

- **Model:** `App\Models\Product` — **Policy:** `App\Policies\ProductPolicy`
- **Gate references:** _(not yet walked)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

### Tools group

#### MailingListResource — [`app/Filament/Resources/MailingListResource.php`](../../app/Filament/Resources/MailingListResource.php)

- **Model:** `App\Models\MailingList` — **Policy:** `App\Policies\MailingListPolicy`
- **Gate references:** _(not yet walked)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### WidgetTypeResource — [`app/Filament/Resources/WidgetTypeResource.php`](../../app/Filament/Resources/WidgetTypeResource.php)

- **Model:** `App\Models\WidgetType` — **Policy:** `App\Policies\WidgetTypePolicy`
- **Gate references:** _(not yet walked)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

### Settings group

#### UserResource — [`app/Filament/Resources/UserResource.php`](../../app/Filament/Resources/UserResource.php)

- **Model:** `App\Models\User` — **Policy:** `App\Policies\UserPolicy`
- **Gate references:** _(not yet walked)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### RoleResource — [`app/Filament/Resources/RoleResource.php`](../../app/Filament/Resources/RoleResource.php)

- **Model:** `App\Models\Role` — **Policy:** _(not in `AuthServiceProvider::$policies`)_
- **Gate references:** _(not yet walked — sensitive: roles assign permissions)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### EmailTemplateResource — [`app/Filament/Resources/EmailTemplateResource.php`](../../app/Filament/Resources/EmailTemplateResource.php)

- **Model:** `App\Models\EmailTemplate` — **Policy:** _(not in `AuthServiceProvider::$policies`)_
- **Gate references:** _(not yet walked — uses `manage_email_templates` standalone capability)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### RecordDetailViewResource — [`app/Filament/Resources/RecordDetailViewResource.php`](../../app/Filament/Resources/RecordDetailViewResource.php)

- **Model:** `App\Models\RecordDetailView` — **Policy:** _(not in `AuthServiceProvider::$policies`)_
- **Gate references:** _(not yet walked — uses `manage_record_detail_views` standalone capability)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

---

## Filament Pages (28)

### Top-level pages

#### Dashboard — [`app/Filament/Pages/Dashboard.php`](../../app/Filament/Pages/Dashboard.php)

- **Gate references:** _(not yet walked — typically open to any authenticated admin user)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### DashboardSettingsPage — [`app/Filament/Pages/DashboardSettingsPage.php`](../../app/Filament/Pages/DashboardSettingsPage.php)

- **Gate references:** _(not yet walked — uses `manage_dashboard_config`)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### DesignSystemPage — [`app/Filament/Pages/DesignSystemPage.php`](../../app/Filament/Pages/DesignSystemPage.php)

- **Gate references:** _(not yet walked — design system editor; super-admin / theme-scss gated?)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### DonorsPage (Giving Summary) — [`app/Filament/Pages/DonorsPage.php`](../../app/Filament/Pages/DonorsPage.php)

- **Gate references:** `canAccess()` returns `can('manage_donations')` ([line 43](../../app/Filament/Pages/DonorsPage.php#L43))

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### HelpIndexPage — [`app/Filament/Pages/HelpIndexPage.php`](../../app/Filament/Pages/HelpIndexPage.php)

- **Gate references:** _(not yet walked — help docs, typically open to all admin users)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### HelpCategoryPage — [`app/Filament/Pages/HelpCategoryPage.php`](../../app/Filament/Pages/HelpCategoryPage.php)

- **Gate references:** _(not yet walked)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### HelpArticlePage — [`app/Filament/Pages/HelpArticlePage.php`](../../app/Filament/Pages/HelpArticlePage.php)

- **Gate references:** _(not yet walked)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### ImporterPage — [`app/Filament/Pages/ImporterPage.php`](../../app/Filament/Pages/ImporterPage.php)

- **Gate references:** _(not yet walked — uses `import_data`)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### Import* pages (16 importers — one section, walked together)

The 8 Import{Type}Page + 8 Import{Type}ProgressPage pages share the same gating shape (each is `import_data`-gated by convention). Walking and per-page status capture together to keep the matrix compact; if a page's gate diverges from the pattern, it gets its own section appended.

Pages in scope:
- ImportContactsPage / ImportProgressPage
- ImportEventsPage / ImportEventsProgressPage
- ImportDonationsPage / ImportDonationsProgressPage
- ImportMembershipsPage / ImportMembershipsProgressPage
- ImportInvoiceDetailsPage / ImportInvoiceDetailsProgressPage
- ImportNotesPage / ImportNotesProgressPage
- ImportOrganizationsPage / ImportOrganizationsProgressPage
- ImportHistoryPage (review surface; uses `review_imports`)

- **Gate references:** _(not yet walked)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### MediaLibraryPage — [`app/Filament/Pages/MediaLibraryPage.php`](../../app/Filament/Pages/MediaLibraryPage.php)

- **Gate references:** _(not yet walked)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

### Settings pages

#### GeneralSettingsPage — [`app/Filament/Pages/Settings/GeneralSettingsPage.php`](../../app/Filament/Pages/Settings/GeneralSettingsPage.php)

- **Gate references:** _(not yet walked — typically super-admin gated)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### CmsSettingsPage — [`app/Filament/Pages/Settings/CmsSettingsPage.php`](../../app/Filament/Pages/Settings/CmsSettingsPage.php)

- **Gate references:** _(not yet walked — uses `manage_cms_settings`)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### FinanceSettingsPage — [`app/Filament/Pages/Settings/FinanceSettingsPage.php`](../../app/Filament/Pages/Settings/FinanceSettingsPage.php)

- **Gate references:** _(not yet walked — uses `manage_financial_settings`)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

#### MailSettingsPage — [`app/Filament/Pages/Settings/MailSettingsPage.php`](../../app/Filament/Pages/Settings/MailSettingsPage.php)

- **Gate references:** _(not yet walked — uses `manage_mail_settings`)_

| Role | UI gate | Controller gate | Finding / Status |
|---|---|---|---|
| super_admin | | | not yet walked |
| developer | | | not yet walked |
| crm_editor | | | not yet walked |
| event_manager | | | not yet walked |
| volunteer_coordinator | | | not yet walked |
| cms_editor | | | not yet walked |
| blogger | | | not yet walked |
| treasurer | | | not yet walked |
| unauthenticated | redirect | redirect | OK (panel auth) |

---

## Resource sub-pages

Resource sub-pages under `app/Filament/Resources/{Resource}/Pages/` typically inherit gating from their parent Resource's `canViewAny()` / model policy. Walked as a group below; per-sub-page sections only land for pages that override the default (e.g. `EditPage` with the page builder, `ViewRegistrations` with the registrants table, `EditContentTemplate` with theme-scss edit).

**Sub-pages that may carry their own gate (deserve explicit walk):**

- `ContactResource/Pages/ContactNotes.php` — uses the note policy
- `ContactResource/Pages/EditContactView.php` — record detail view edit
- `OrganizationResource/Pages/OrganizationNotes.php` — uses the note policy
- `EventResource/Pages/ViewRegistrations.php` — event registrants table (session 278)
- `PageResource/Pages/EditPage.php` — page builder
- `PageResource/Pages/EditPageDetails.php` — page metadata
- `PostResource/Pages/EditPost.php` — page builder
- `PostResource/Pages/EditPostDetails.php` — post metadata
- `TemplateResource/Pages/EditContentTemplate.php` — `edit_page_snippets`?
- `TemplateResource/Pages/EditPageTemplate.php` — `edit_site_chrome`?
- `TemplateResource/Pages/EditPageTemplateChrome.php` — `edit_site_chrome`?
- `TemplateResource/Pages/EditPageTemplateScss.php` — `edit_theme_scss`?
- `ContentCollectionResource/Pages/ManageContentCollectionItems.php` — collection item edit

_(walked together at Phase 3; not stubbed individually here)_

---

## Non-Filament admin-shaped controllers

Controllers in `app/Http/Controllers/Admin/` are routed under the admin panel's middleware group (`Authenticate` + policy enforcement at action level) per `AdminPanelProvider::panel()`. Walked together at Phase 3; each gets a note on which capability gates its actions and whether direct-URL reach is enforced consistently with the matching Filament surface.

**Controllers in scope:**

- DashboardBuilderApiController — gated by `manage_dashboard_config` (per `routes/admin-dashboard-api.php` doc comment)
- HeroiconController — heroicon picker; auth-only?
- InlineImageUploadController — Quill editor image upload
- InvitationController — user invitation flow; `view_any_user` / `create_user`?
- PageBuilderApiController — page builder JSON API; policy-gated per widget owner
- PresetController — widget presets
- RandomDataGeneratorController — dev tooling; super-admin gated per Stripe Test-Mode Detection stub
- RecordDetailViewBuilderApiController — record detail view edits
- SetupChecklistController — setup checklist
- ThemeTypographyController — typography editor; `edit_theme_scss`?
- WidgetDefaultsController — widget default values

**Portal controllers** (`app/Http/Controllers/Portal/*`) are gated by `portal.auth` middleware and the per-route `verified:portal.verification.notice` middleware; portal-user-scoping audit is out of scope for this session per the C3 entry.

**Public controllers** (top-level `app/Http/Controllers/*`) — no permission gate; they serve unauthenticated traffic. Out of scope for this audit (they're not admin surfaces).

---

## Findings summary

_(Filled at session close. Tallies cells by status: OK / fixed in-session / deferred to 32b / open flag for follow-on.)_

---

## Data classification

_(Partial — appended at 32b alongside the accidental-public-exposure work.)_

The classification axis is orthogonal to the role axis: which **fields** within each surface carry sensitivity (home addresses, donor amounts, internal notes), independent of which roles can reach the surface. The matrix above gates surface-level access; field-level classification gates which fields a role with surface access can see / edit. 32b is the session that lifts this work.
