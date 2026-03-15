# Code Audit — Redundant Code & Outdated Documentation

*Generated: Session 015 pre-work. Project is at the close of session 014; session 015 has not yet been executed.*

---

## Section 1 — Redundant or Orphaned Code

### 1. `resources/views/welcome.blade.php` — Orphaned view

Laravel's default welcome page. The root route (`/`) is registered in `routes/web.php` as `PageController@home`, which handles the homepage correctly. Nothing in the application references `welcome.blade.php`. It is unreachable dead code.

---

### 2. `resources/css/app.css`, `resources/js/app.js`, `resources/js/bootstrap.js` — Near-empty Vite assets

All three files are effectively empty: `app.css` is whitespace/comments only, `app.js` is 22 bytes, `bootstrap.js` is 127 bytes. The project delivers its frontend through Filament's compiled assets (Alpine.js bundled), CDN-loaded Alpine for the public site, and custom CSS uploaded via the SiteSettings admin. These files are wired into the Vite pipeline but produce nothing. They imply a build step that has no output.

---

### 3. `new-nav.txt` — Loose planning file at project root

A proposed admin navigation structure — a draft that supersedes the nav structure currently documented in `docs/information-architecture.md`. It does not belong at the project root. It has not been incorporated into any formal doc. It should either be moved to `/sessions/` or `/docs/`, or its proposed changes should be promoted into the IA doc and the file deleted.

---

### 4. `routes/web.php:18-21` — Event routes use wrong URL shape

The event `show` and `register` routes still include `/{dateId}`:

```
Route::get("/{$eventsPrefix}/{slug}/{dateId}", ...)
Route::post("/{$eventsPrefix}/{slug}/{dateId}/register", ...)
```

Per the session 015 plan, both routes should use `/{slug}` only — the `{dateId}` segment was a design error identified in the session 014 post-review. The current routes will resolve incorrectly until session 015 is executed. This is not a clean "future work" stub — it is actively wrong code that affects working event pages and tests.

---

### 5. `app/Livewire/PageBuilder.php` — Unresolved TODO

A comment near the top of the file reads:

```php
// TODO: Remove these once CSP is resolved and @alpinejs/sort is confirmed working.
```

The underlying CSP (Content Security Policy) issue it references is noted in `sessions/future-sessions.md` as a deferred DevOps task but has no scheduled session. The TODO is open-ended with no owner or target session, making it easy to forget.

---

### 6. `docs/schema/README.md` — Documented convention, never followed

The file declares: *"Every time a migration is written, the table it creates or modifies must be added or updated here. This is a non-negotiable project convention."* The table index still reads: `_(none yet — populated as migrations are written)_`. There are 39 migrations and roughly 25 tables. The convention has been ignored for the entire project. The file is either a placeholder that should be deleted or a commitment that should be honoured.

---

## Section 2 — Outdated Planning Documentation

### 1. `docs/information-architecture.md` — Multiple stale statuses (last updated session 011)

This is the most out-of-date document in the project. Several statuses have not been updated since session 008.

**CRM Domain table:**
- `Event` is marked `⬜ Deferred` — built in session 014
- `EventRegistration` is marked `⬜ Deferred` — built in session 014

**Content Domain table:**
- `CmsTag` is marked `⬜ Session 008` — built in session 008
- `WidgetType` is marked `⬜ Session 008` — built in session 008
- `PageWidget` says "Will be extended in Session 008" — session 008 is done

**Navigation Group Structure section:**
- `Events` under CRM is marked `⬜ future` — built in session 014
- The `Roles` resource (added session 013) does not appear anywhere in the nav map
- The `Households` resource (added session 011) does not appear in the nav map

**Planned Future Additions table:**
- Lists `Event` and `EventRegistration` as planned work — both are built

---

### 2. `docs/ARCHITECTURE.md` — Header and Core Entities section out of date

The header states: *"Last updated: March 2026. SiteSetting infrastructure added."* This reflects approximately session 007. Sessions 008 through 014 are not reflected.

**Core Entities — People & Organizations:**
- `Household` is not marked as built; it sits in an undifferentiated list with `Family`, `Relationship`, `Volunteer` (none of which exist yet)

**Core Entities — Events:**
- Lists the entire events domain (`Event`, `EventRegistration`, `Event Ticket/Tier`, `Waitlist Entry`, `Waiver`) as a single undifferentiated block with no built/pending distinction — `Event` and `EventRegistration` are now built

**Core Entities — Content:**
- No content entity is updated past session 007; `CmsTag`, `WidgetType`, and the full widget system are not reflected as built

---

### 3. `docs/adr/014-event-data-model.md` — File location creates naming collision

This ADR is stored in `/docs/adr/014-event-data-model.md`. The decisions folder (`/docs/decisions/`) also has files numbered 001–014. Having `014` appear in two separate filing systems under `/docs/` is confusing — a future reader could conflate `docs/adr/014-event-data-model.md` with `docs/decisions/014-contact-taxonomy.md`. The ADR either belongs in `/docs/decisions/` with a non-colliding number, or the two filing systems should be consolidated.

---

### 4. `docs/decisions/004-twill-plus-filament.md` — Superseded decision with stale internal references

The decision is correctly marked `Superseded` at the top. However, the body text still refers to a `sessions/002.md` file that does not exist (logs use `session-002-log.md`). This is a minor inconsistency — the file is historical, but if someone follows the internal reference they will hit a dead link.

---

*End of audit.*
