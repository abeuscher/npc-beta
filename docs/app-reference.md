# App Reference

Fast-orientation index for Claude. Read this at the start of any session before searching for files. It is not a replacement for `docs/schema/` — it is a map to where things live.

---

## Environments

| Name | Host | App path | Artisan |
|------|------|----------|---------|
| **Local (WSL2)** | `localhost` | `~/nonprofitcrm` (WSL2 Linux filesystem) | `docker compose exec app php artisan` |
| **Deploy server** | `root@167.172.141.225` (DigitalOcean, Ubuntu 22.04) | `/opt/nonprofitcrm` | `docker exec nonprofitcrm_app php artisan` |

### Deploy server — Docker containers

| Container name | Role |
|----------------|------|
| `nonprofitcrm_app` | PHP-FPM (Laravel app) |
| `nonprofitcrm_nginx` | Nginx reverse proxy |
| `nonprofitcrm_worker` | Queue worker |
| `nonprofitcrm_postgres` | PostgreSQL 16 |
| `nonprofitcrm_redis` | Redis (cache + queue) |

Local container names are identical. Local compose file: `docker-compose.yml` in the project root. Deploy server compose file: `/opt/nonprofitcrm/docker-compose.prod.yml`.

Deploy server domain: `beuscher.net`. `.env` lives at `/opt/nonprofitcrm/.env` (not in source control).

---

## Admin UI — views and their files

The admin panel is built with Filament 3 and lives at `/admin`. Each resource has List, Create, and Edit pages under `app/Filament/Resources/{Name}Resource/Pages/`.

### CRM group

| View title (as seen in UI) | Resource / Page file |
|---------------------------|----------------------|
| Contacts (list) | `ContactResource.php` |
| Edit Contact | `ContactResource/Pages/EditContact.php` |
| Members (list) | `MemberResource.php` |
| Membership Tiers (list) | `MembershipTierResource.php` |
| Organizations (list) | `OrganizationResource.php` |
| Custom Fields (list) | `CustomFieldDefResource.php` |

### CMS group

| View title (as seen in UI) | Resource / Page file |
|---------------------------|----------------------|
| Pages (list) | `PageResource.php` |
| Edit Page (includes page builder) | `PageResource/Pages/EditPage.php` |
| Blog Posts (list) | `PostResource.php` |
| Edit Post (includes page builder) | `PostResource/Pages/EditPost.php` |
| Events (list) | `EventResource.php` |
| Edit Event | `EventResource/Pages/EditEvent.php` |
| Navigation (list) | `NavigationMenuResource.php` |
| Collections (list — custom collections) | `CollectionResource.php` |
| Collection Manager (list — content collections) | `ContentCollectionResource.php` |
| Forms (list) | `FormResource.php` |
| Templates (list) | `TemplateResource.php` |
| Edit Content Template | `TemplateResource/Pages/EditContentTemplate.php` |
| Edit Page Template | `TemplateResource/Pages/EditPageTemplate.php` |
| CMS Settings | `Filament/Pages/Settings/CmsSettingsPage.php` |

### Finance group

| View title (as seen in UI) | Resource / Page file |
|---------------------------|----------------------|
| Donations (list) | `DonationResource.php` |
| Giving Summary | `Filament/Pages/DonorsPage.php` |
| Products (list) | `ProductResource.php` |
| Funds & Grants (list) | `FundResource.php` |
| Generate Tax Receipts | `Filament/Pages/GenerateTaxReceiptsPage.php` |
| Finance Settings | `Filament/Pages/Settings/FinanceSettingsPage.php` |

### Tools group

| View title (as seen in UI) | Resource / Page file |
|---------------------------|----------------------|
| Importer | `Filament/Pages/ImporterPage.php` |
| Import Contacts | `Filament/Pages/ImportContactsPage.php` |
| Import History | `Filament/Pages/ImportHistoryPage.php` |
| Mailing Lists (list) | `MailingListResource.php` |
| Tag Manager (list) | `TagResource.php` |
| Widget Manager (list) | `WidgetTypeResource.php` |

### Settings group

| View title (as seen in UI) | Resource / Page file |
|---------------------------|----------------------|
| General Settings | `Filament/Pages/Settings/GeneralSettingsPage.php` |
| Mail Settings | `Filament/Pages/Settings/MailSettingsPage.php` |
| Users (list) | `UserResource.php` |
| Roles (list) | `RoleResource.php` |
| System Emails (list) | `EmailTemplateResource.php` |

---

## Public-facing views and their files

| View / URL pattern | Controller / file |
|-------------------|-------------------|
| Home page (`/`) | `PageController::home` |
| Any published page (`/{slug}`) | `PageController::show` |
| Blog index (`/{blog_prefix}`) | `PostController::index` |
| Single blog post (`/{blog_prefix}/{slug}`) | `PostController::show` |
| Member portal login | `LoginController` |
| Member portal signup | `SignupController` |
| Member portal account dashboard | `AccountController` |
| Event registration (POST) | `EventController::register` |
| Product checkout (POST) | `ProductCheckoutController::store` |
| Donation checkout (POST) | `DonationCheckoutController::store` |
| Web form submission (POST) | `FormSubmissionController::store` |

All public controllers live in `app/Http/Controllers/`. Portal routes are prefixed by the `portal_prefix` site setting (default: `members`). Blog prefix is the `blog_prefix` site setting (default: `news`).

---

## Page builder — key components

| Your name | Class / file |
|-----------|-------------|
| Page builder (admin UI) | `App\Livewire\PageBuilder` — `app/Livewire/PageBuilder.php` |
| Individual block in the builder | `App\Livewire\PageBuilderBlock` — `app/Livewire/PageBuilderBlock.php` |
| Page builder blade | `resources/views/livewire/page-builder.blade.php` |
| Block blade (includes widget picker modal) | `resources/views/livewire/page-builder-block.blade.php` |
| Widget templates | `resources/views/widgets/*.blade.php` |
| Page context service (data for widget templates) | `App\Services\PageContext` — `app/Services/PageContext.php` |
| Widget data resolver | `App\Services\WidgetDataResolver` — `app/Services/WidgetDataResolver.php` |

---

## Major dependencies

| Package | Used for |
|---------|----------|
| `filament/filament` v3 | Entire admin panel — resources, pages, forms, tables |
| `livewire/livewire` (via Filament) | Page builder UI, reactive admin components |
| Alpine.js (via Filament) | Front-end interactivity in admin and public pages |
| `laravel/cashier` | Stripe integration — subscriptions, charges |
| `spatie/laravel-permission` | Role and permission system |
| `spatie/laravel-activitylog` | Activity log on CRM records |
| `spatie/laravel-medialibrary` | File/image uploads |
| `spatie/laravel-sluggable` | Auto-slug generation on pages, events, etc. |
| `spatie/laravel-schemaless-attributes` | Flexible JSONB fields on models |
| `resend/resend-php` | Transactional email sending |
| `mailchimp/marketing` | MailChimp sync |
| `scssphp/scssphp` | SCSS compilation for the site theme editor |
| `laravel/horizon` | Queue monitoring dashboard |
| `predis/predis` | Redis client (cache + queues) |
| `swiper` | Carousel/slider — Swiper.js (MIT license, copyright Vladimir Kharlampidi) |
| Pest v2 | Test runner |
