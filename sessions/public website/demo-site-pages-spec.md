# Demo Site Pages — Design Spec

**Session:** A006 (cloud design session). **Status:** spec, not built. **Companion:** `sessions/demo-site-pages-build.PROMPT-DRAFT.md` (the local build session that authors these pages).

This is the build-ready design for the public website the **demo install** ships with — a small pretend-nonprofit site that shows a prospect what the product does, sitting on top of the CRM data the daily `demo:reset` already generates. It is a **separate, isolated instance** from the real `nonprofitcrm.com` marketing site (demo visitors can edit pages, so the demo site must never point at the live marketing pages — FM handoff decision). Every page composes **existing** widgets only; no new widget is in scope.

Read this end-to-end before building. It is written so the build-session operator needs no further context.

---

## 0. Resolved open questions (decisions, with rationale)

1. **Does the supplanting touch fresh-install defaults, or only the demo node? → Demo node only.** The demo-site pages are a demo-server asset. Changing what *every* customer's fresh install seeds (the "Default Content State" stub in `session-outlines.md`) is a separate, larger product decision and is **not** made here. The fresh-install Default Content State behavior is left exactly as-is; these pages are recorded as the better *showcase vehicle*, scoped to the demo node. (`session-outlines.md` is updated with that note this session.)
2. **Contact-page map: real address or placeholder? → Generic placeholder.** The org is fictional, so `map_embed` is configured to a recognizable mid-size US downtown (spec uses **downtown Madison, WI**) — it renders a real-looking map without implying a real org address. No street address is published.
3. **Fictional org identity → chosen and fixed below.** A community land/parks trust ("Greenline Community Trust"), deliberately distinct from the real `nonprofitcrm.com` brand and broadly relatable. It maps cleanly onto every entity the generator produces (events, donations, memberships, news posts, products).

---

## 1. Org identity

**Name:** Greenline Community Trust
**One-line mission:** *We protect and care for the parks, trails, and wild places that make our neighborhoods worth living in.*
**Cause:** community greenspace / urban-nature stewardship + volunteer-driven education. Chosen because it makes every generated entity believable: **events** = volunteer trail days and fundraisers; **donations** = gifts to land/stewardship; **memberships** = "Friends of Greenline"; **news posts** = stewardship updates; **products** = a small store (tote bags, native-plant kits) the generator already populates.
**Voice/tone:** warm, plain-spoken, community-rooted, signed-by-a-person — the same anti-SaaS register the product's own marketing site uses, pointed at a nonprofit's supporters rather than a software buyer. First person plural ("we", "our neighbors"), short sentences, concrete ("a Saturday with a shovel"), no jargon, no exclamation-mark fundraising hype.
**Signed-by voice:** **Ellen Park, Executive Director** (the "from a person" signature on Home and About).
**Visual feel / sample-image categories:** outdoors, nature, community/volunteers, parks/trails. All images draw from the in-product **sample image library** as placeholders (same convention as the marketing-site build — `audit-summary.md`); each placeholder is labelled for a later real-photography swap. The generator already attaches sample-library images to its events, posts, and products, so the data-driven pages render with imagery automatically.

---

## 2. System conventions (reused, not re-derived)

These are inherited verbatim from the Public Marketing Website track (`sessions/tracks/public-marketing-website.md`, `brief.md`) — do not re-decide them:

- **Page JSON format:** version **1.1.0**, `payload.pages` + `payload.templates`. Two block types: `type:"widget"` (`handle`, `label`, `config`, `query_config`, `appearance_config`, `sort_order`, `is_active`, `media`) and `type:"layout"` (`label`, `display` grid|flex, `columns`, `layout_config`, `slots` — array-of-arrays, one inner array per column; child widgets carry `column_index` + `full_width:false`). `sessions/public website/home.json` and `existing-home-page-export.json` are canonical examples.
- **Section bands:** full-bleed background-color slabs; background lives on the `type:"layout"` block, not inner widgets (inner-widget backgrounds only for intentional within-row contrast).
- **Padding:** 150/150 top/bottom on major bands; 25/50 on widgets inside column layouts.
- **Background tones:** `#ffffff`, `#d4d4f2` (tinted), `#373c44` (dark). No new tones without a gap flag.
- **Color source of truth:** `appearance_config.text.color`. Inline Quill `style="color:…"` is inline emphasis only.
- **Type + buttons fixed:** primary / secondary / text-only. A needed-but-missing variant is a **gap**, never invented.
- **No animations** beyond the bar-chart load + carousel autoplay.
- **No invented widgets:** a missing capability degrades + logs a gap.
- **Chrome is global, not per-page.** The marketing pages carry `template_name: null`; header/footer/nav come from the global public layout (`public.blade.php`), not the page JSON. The demo site follows the same model — see §5. *(Sequencing note: the E16 Header/footer overhaul may land before the build session and change the production chrome defaults. The build session uses **whatever the production chrome defaults are at build time** — do not hard-code around today's chrome.)*
- **Text widget content is Quill HTML:** preserve `data-list="bullet"`/`data-list="ordered"` markup and the `<span class="ql-ui" contenteditable="false"></span>` spans inside list items — the editor requires them.

---

## 3. Page list

Seven nav entries: six curated pages + the reused `/demo` dark-side page. Slugs chosen to match the generator's existing URL shapes (news posts live under `news/…`, so the index is `/news`).

| # | Page | Slug | Purpose in the prospect demo | Primary widget handles |
|---|------|------|------------------------------|------------------------|
| 1 | Home / landing | `home` | First impression; tours the product's range in one scroll | `hero`, `text_block`, `image`, `events_listing`, `blog_listing`, `three_buckets` |
| 2 | Events | `events` | Shows live events rendering against generated data | `events_listing`, `event_calendar` |
| 3 | Donate | `donate` | Shows the donation surface + giving tiers | `donation_form`, `three_buckets`, `text_block` |
| 4 | About / Board | `about` | The "real org" story + the board widget | `text_block`, `image`, `board_members` |
| 5 | Contact | `contact` | A working contact form + a map | `web_form`, `map_embed`, `text_block` |
| 6 | News | `news` | Blog index over the generated posts | `blog_listing` |
| 7 | Demo (dark-side) | `demo` | The auto-login conversion point | reused from `sessions/public website/demo.json` (see §5.2) |

**Event detail and blog-post detail are NOT curated pages** — see the data-alignment note in §4.2 and §4.6. Event-detail rendering is the system's native event template; blog-post detail pages are produced by the generator (each generated post already gets `text_block` + `blog_pager` widgets). Both are reached *from* the curated index/listing pages, and both survive the reset because the generator re-creates them every cycle. This is a deliberate decision driven by slug instability — detail widgets bound to a specific `event_slug` (`event_description`, `event_registration`) cannot be curated, because the generator assigns fresh random slugs on every reset (see Gap G-A006-2).

---

## 4. Per-page composition + copy

Layout/padding follow §2. Where a widget reads CRM data, the `query_config` is the existing data-contract shape that widget already declares (handles + contracts confirmed by reading `app/Widgets/` this session) — the build session sets it through the page builder UI; the values below are the intent.

### 4.1 Home — `/` (`home`)

A single top-to-bottom tour. Bands alternate white / tinted / dark per §2.

**Band 1 — Hero** (`hero`, full-bleed). Background image from the sample library (outdoors/parks). Overlay opacity ~40 so text reads. `text_position: center-center`.
- Content (Quill): `<h1>Keep the green in our neighborhood.</h1>` + one line: *"Greenline Community Trust protects the parks and trails our neighbors love — with volunteer hands, small gifts, and a long memory."*
- `ctas`: **[Become a member → `/donate`]** (primary), **[Find a volunteer day → `/events`]** (secondary).

**Band 2 — Mission / "what we do"** (`#d4d4f2` tinted band, 1-column `text_block`, centered, `text_max_width` constrained).
- `<h2>Small trust, big patch of green.</h2>` + 2 short paragraphs: who we are (a neighbor-run land trust), what we steward (12 miles of trail, three pocket parks, one restored creek), how (volunteer Saturdays, member dues, the occasional bake-sale-scale fundraiser). Signed-off tone, not corporate.

**Band 3 — Featured upcoming event** (white band; `events_listing` configured to a compact preview).
- `events_listing` config: `heading: "Next time we're outside"`, `columns: 1`, `items_per_page: 1`, `sort_default: soonest`.
- `query_config`: the widget's declared `event` contract (`fields` incl. `title, starts_at, location, is_free, image`; `filters: date_range from now, order_by starts_at asc`). Renders the soonest **generated** event. **Data alignment:** generator makes 6 future-dated events (`starts_at` = now + 1–30 days) → this band is always populated.

**Band 4 — Recent news** (`#d4d4f2` tinted band; `blog_listing` preview).
- `blog_listing` config: `heading: "From the trail log"`, `columns: 3`, `items_per_page: 3`, `sort_default: newest`.
- `query_config`: declared `post` contract. **Data alignment:** generator makes 5 published `type:post` pages → three render here.

**Band 5 — Give / impact** (dark band `#373c44`, white text; `three_buckets`).
- `three_buckets`: three giving framings (not a form here — the form is on `/donate`). Headings + short bodies + one CTA each → `/donate`:
  - **"A morning of work"** — *"$25 buys gloves, bags, and coffee for a volunteer crew."* → [Give $25]
  - **"A young tree"** — *"$100 plants and waters one native tree through its first dry summer."* → [Give $100]
  - **"A stretch of creek"** — *"$500 funds a season of streambank repair on the Greenline."* → [Give $500]
- Buttons: primary on dark. *(If a ghost-on-dark variant is wanted and absent, that is a gap — use the existing primary, carried by whitespace, per the marketing-site precedent.)*

**Band 6 — Signed close** (white band, 1-column `text_block`, centered).
- Short paragraph signed *"— Ellen Park, Executive Director"* + CTA **[See what membership includes → `/donate`]**.

### 4.2 Events — `/events`

**Band 1 — Header** (`text_block`, white): `<h1>Get outside with us.</h1>` + one line on how volunteer days + fundraisers work.

**Band 2 — Calendar** (white; `event_calendar`, `default_view: month`, `heading: "What's coming up"`). Sources event data internally; renders the 6 generated future events on the month grid.

**Band 3 — Listing** (`#d4d4f2` tinted; `events_listing`): `heading: "All upcoming events"`, `columns: 3`, `items_per_page: 6`, `sort_default: soonest`, `show_search: true`. Declared `event` contract. Renders all 6 generated events; each card links to the event-detail URL (`url` field).

**Data alignment + scope:** the listing/calendar are **slug-agnostic** (they query events live), so they survive every reset. **Event detail** is the system's native event rendering reached via each card's `url` — *not* a curated page, because `event_description`/`event_registration` require a fixed `event_slug` and the generator re-slugs events each reset (Gap G-A006-2). The build session verifies event-detail pages render for generated events; if the product has no default event-detail rendering, that is a gap to surface, **not** a curated page to author against an ephemeral slug.

### 4.3 Donate — `/donate`

**Band 1 — Header + form** (white; 2-column layout). Left column `text_block` (`<h1>Become a Friend of Greenline.</h1>` + 2 short paragraphs: dues fund stewardship year-round; one-time gifts welcome too). Right column `donation_form`:
- config: `heading: "Make a gift"`, `amounts: "25,50,100,250"`, `show_monthly: true`, `show_annual: true`, `success_page: "/donate"` (or a thank-you slug if the build session adds one).

**Band 2 — Giving levels** (`#d4d4f2` tinted; `three_buckets`): the three tiers as named memberships:
  - **Trail Friend — $25/yr** — *"Window decal, monthly trail log, and our gratitude."*
  - **Grove Guardian — $100/yr** — *"Everything above, plus an invite to the spring planting."*
  - **Watershed Steward — $500/yr** — *"All of it, plus a named tree on the Greenline and a hard-hat tour with our crew."*
  - Each CTA scrolls to / links the form. **Data alignment:** these tiers are *copy*, independent of the 15 generated memberships; they describe what a visitor can buy. (The 15 generated memberships populate the admin CRM the prospect explores after auto-login — they are not surfaced on this public page.)

**Band 3 — Impact / why give** (dark `#373c44`, white text; `text_block` centered): 2–3 sentences of concrete impact ("Last year, 240 neighbors gave a Saturday…"). Signed-off optional.

**Important — RecentDonations is NOT used here (Gap G-A006-1).** The session-prompt sketch suggested `recent_donations` on the donation page. Reading `app/Widgets/RecentDonations` shows it declares `requiredPermission: 'view_donation'` and is an admin record-detail widget with **no public-facing config** — it will not render for an anonymous public visitor. Surfacing live donor amounts publicly is also a privacy smell. **Decision:** do not place `recent_donations` on the public donate page. The "social proof / momentum" intent is met by the impact `text_block` (Band 3) and the giving-levels band instead. The 30 generated donations remain visible where they belong — inside the admin CRM after auto-login. Logged as Gap G-A006-1.

### 4.4 About / Board — `/about`

**Band 1 — Story** (white; 2-column: `text_block` + `image`). `<h1>Run by neighbors, for neighbors.</h1>` + 2–3 paragraphs (founded around a threatened creek; all-volunteer for years; one part-time director now; what stays the same). Image: sample-library community/outdoors placeholder, labelled for real-photo swap.

**Band 2 — How we work** (`#d4d4f2` tinted; `text_block`, centered): short paragraph on the volunteer-first model + the small annual budget honesty (the anti-SaaS-equivalent "we're small and we like it" beat).

**Band 3 — Board** (white; `board_members`).
- `board_members` config: `heading: "Our board"`, `items_per_row: 3`, `image_shape: circle`, bound to a **curated `board_members` collection** (see data alignment).
- **Data alignment (build-session action required):** `board_members` reads a **collection** (`SOURCE_WIDGET_CONTENT_TYPE`), *not* the generated contacts. The generator makes 40 contacts but **no curated board**. So the build session must **seed a `board_members` collection** with these four entries (carried in the demo bundle as `payload.collections` so the reset re-establishes them). Field mapping: `name_field`, `title_field`, `description_field`, plus `image_field` (sample-library portrait placeholder per member). Logged as Gap G-A006-3 (curated content beyond the generator).

**Board roster (curated copy):**
- **Maya Trujillo — Board Chair.** *Retired city parks planner; has walked every foot of the Greenline twice.*
- **David Osei — Treasurer.** *Owns the hardware store on 4th; keeps our budget as tight as his inventory.*
- **Karen Whitfield — Secretary.** *Third-grade teacher; runs our school-group trail walks.*
- **Tom Becker — At-large.** *Founded the Tuesday-night trail-running club; our loudest advocate for better signage.*

### 4.5 Contact — `/contact`

**Band 1 — Form** (white; 2-column: `text_block` intro + `web_form`).
- `text_block`: `<h1>Say hello.</h1>` + one line ("Questions, a downed tree to report, or want to volunteer? Send a note.").
- `web_form` config: `form_handle` → a **contact form** the demo install seeds. **Data alignment / build action:** `DatabaseSeeder` already seeds forms; the build session confirms a suitable contact form handle exists (or adds one to the demo seed) and binds it. Submissions are visible in the admin CRM the prospect explores. *(Note: form definitions are seeded by `DatabaseSeeder`, not necessarily carried in the page bundle — see §5.3.)*

**Band 2 — Map** (`#d4d4f2` tinted; `map_embed`).
- `map_embed` config: `heading: "Find us at the trailhead"`, `map_input` = an embed centered on **downtown Madison, WI** (recognizable placeholder per decision #2), `aspect_ratio: 16/9`. No street address published.

### 4.6 News — `/news`

**Band 1 — Header** (`text_block`, white): `<h1>The trail log.</h1>` + one line.

**Band 2 — Index** (white; `blog_listing`): `heading: ""` (header band already set it) or `"Latest posts"`, `columns: 3`, `items_per_page: 6`, `sort_default: newest`, `show_search: true`. Declared `post` contract.
- **Data alignment:** generator makes 5 published `type:post` pages with slugs under `news/…`, each already carrying `text_block` + `blog_pager` widgets → the index is populated and each card links to a working post-detail page. **The post-detail pages are generated, not curated** — they survive the reset because the generator re-creates them. Curating a post-detail page would bind to an ephemeral generated slug (same issue as events). So `/news` is the only curated blog page; `blog_pager` lives on the generated detail pages, not here.
- *(Optional polish, not required: the build session may seed 1–2 named curated posts — e.g. "Why we pulled 300 lbs of buckthorn" and "Meet our newest trail" — for a more believable top-of-feed. If seeded, carry them in the bundle as `type:post` pages. Flagged optional because the generated posts already populate the index.)*

---

## 5. Nav, chrome, `/demo` integration, reset wiring

### 5.1 Nav + chrome

- Chrome (header/footer) is **global** (the public layout), not per-page (§2). The demo site uses the production chrome defaults **as they are at build time** (E16 may improve them first — do not hard-code around today's).
- The header `nav` is bound to a **navigation menu** carried in the demo bundle (`payload.navigation_menus`, supported by the importer since session A001). Menu items, in order: **Home `/`, Events `/events`, Donate `/donate`, About `/about`, News `/news`, Contact `/contact`**, plus a trailing emphasized **Demo → `/demo`**. The footer reuses the same links (or the chrome default footer if E16 provides one).
- `logo` widget: text branding "Greenline Community Trust" (or a sample-library mark placeholder), `link_url: /`.
- **Build action:** if the chrome's nav binds to a menu by handle, the bundle's `navigation_menus` entry must use that handle so the import re-points the demo chrome at the demo menu on every reset.

### 5.2 `/demo` dark-side page integration

Reuse, do not redesign. Mechanism (kept deliberately simple — the user has said the exact execution doesn't matter):
1. The `/demo` page already exists as a single page entry in the **main** marketing site's export: `sessions/public website/demo.json` (one page, slug `demo`, the dark/light 50/50 split with the `/demo/enter` auto-login CTA).
2. **Clip** that single page object out of `demo.json`'s `payload.pages[0]` and **include it verbatim** as one of the pages in the demo bundle (§5.3). No edits to its structure; the lorem copy in it is replaced by real demo copy only if/when the user supplies it (out of scope here — it ships as-is).
3. On import, it lands at `/demo` in the demo install and is added to the demo nav. That's the whole mechanism — no clipping tooling, no cross-site linkage.

### 5.3 Reset wiring (the load-bearing build instruction)

**Goal:** a defaced or wiped demo restores the real curated site on every `demo:reset`.

**Current reset path** (confirmed by reading the code this session):
- `app/Console/Commands/DemoResetCommand.php` — `demo:reset` (guarded by `isDemoMode()`; FAILS off-demo). Full mode runs `Artisan::call('migrate:fresh', ['--seed', '--force'])` (→ `DatabaseSeeder`), then `(new DemoBaselineSeeder())->run()`. `--soft` skips the rebuild and runs `DemoBaselineSeeder` only.
- `database/seeders/DemoBaselineSeeder.php` — logs in a super-admin system user, then `RandomDataGenerator->wipe()` + `->generate(self::BASELINE)` (40 contacts, 6 events, 25 registrations, 30 donations, 15 memberships, 5 posts, 6 products).
- `database/seeders/DatabaseSeeder.php` — seeds roles, permissions, the demo user+role (in demo mode), base/system/portal pages, forms, system collections (events/products/posts), email templates, etc. **No page-bundle import today.**
- `app/Services/ImportExport/ContentImporter.php` — `import(array $bundle, ImportLog $log, array $opts = [], ?string $mediaRoot = null): void`. Validates `format_version` (major must match), runs in a DB transaction. Relevant opts (defaults): `import_pages:true`, `replace_duplicate_pages:true`, `import_media:true`, `import_navigation:true`, `import_collections:true`. Pages upsert by slug; navigation upserts by handle and resolves `page_slug → page_id` **after** pages import (so menu links resolve); collections import (A001).

**The wiring to add (build session):**

1. **Export the curated pages to one bundle.** Author pages 1–6 + the clipped `/demo` page in the CMS, export to a single format-1.1.0 bundle: `sessions/public website/demo-site-pages.json` (the durable, committed record). The bundle's `payload` carries: `pages` (the 7), `navigation_menus` (the demo menu, §5.1), `collections` (the `board_members` collection, §4.4). Media: sample-library images ride along via the page `media` arrays / the bundle `media` section as the marketing-site exports already do.
2. **Place a runtime copy the seeder can read.** `sessions/` is not deployed, so the seeder cannot read from there. Copy the same JSON to a runtime app location — e.g. `database/seeders/data/demo-site-pages.json` — committed with the app. (Pick the location to match any existing fixture convention the build session finds; the point is *a deployed path*, not `sessions/`.)
3. **Import on reset.** In `DemoBaselineSeeder::run()`, **after** `RandomDataGenerator->generate(...)` (super-admin auth context still active, which the importer's `resolveAuthorId()` uses), load the runtime JSON and call:
   ```php
   $bundle = json_decode(file_get_contents(database_path('seeders/data/demo-site-pages.json')), true);
   app(\App\Services\ImportExport\ContentImporter::class)->import(
       $bundle,
       new \App\Services\ImportExport\ImportLog(),
       ['import_pages' => true, 'replace_duplicate_pages' => true,
        'import_navigation' => true, 'import_collections' => true, 'import_media' => true],
   );
   ```
   Placing it in `DemoBaselineSeeder` (not only `DemoResetCommand`) means **both** the full reset and the `--soft` reset re-establish the pages. `replace_duplicate_pages:true` makes the curated `home`/`about`/etc. overwrite the base-seeded starter pages by slug — desired, the demo gets its curated site.
4. **Resulting reset path:** rebuild → seed (roles, demo user/role, base pages, forms, system collections) → `DemoBaselineSeeder`: super-admin login → wipe + generate CRM baseline → **import curated demo bundle (pages + nav + board collection)** → restore auth. A wiped/defaced demo comes back to the real site every cycle.
5. **Forms caveat (§4.5):** if the importer does not carry form *definitions* (it carries pages/nav/collections/media/products/events), the contact form the `web_form` widget binds to must be ensured by the **demo seed** (`DatabaseSeeder` already seeds forms) — confirm the contact form handle exists there and that `web_form.form_handle` matches. If the importer *does* cover forms, carry it in the bundle instead. The build session verifies which and wires accordingly. (Flagged so it isn't discovered late.)

---

## 6. Data-alignment summary (what renders against generated data vs. what the build session must seed)

| Page / widget | Renders against generator output? | Build-session-seeded content beyond the generator |
|---|---|---|
| Home `events_listing` (1) | ✅ soonest of 6 future events | — |
| Home `blog_listing` (3) | ✅ of 5 published posts | — |
| Events `event_calendar` + `events_listing` | ✅ 6 future events | — |
| Events detail | ✅ system-rendered per generated event | — (not curated; Gap G-A006-2) |
| Donate `donation_form` | n/a (form, not a reader) | — |
| Donate `three_buckets` tiers | copy (independent of the 15 generated memberships) | tier copy in the bundle |
| About `board_members` | ❌ reads a collection, not contacts | **curated `board_members` collection (4 entries)** — Gap G-A006-3 |
| Contact `web_form` | n/a | **contact form handle** (via `DatabaseSeeder`, §5.5) |
| Contact `map_embed` | n/a | placeholder map embed (Madison, WI) |
| News `blog_listing` | ✅ 5 posts; detail pages generated | optional 1–2 curated featured posts |
| All imagery | ✅ generator attaches sample-library images to events/posts/products | sample-library placeholders on static bands (Hero, About) labelled for real-photo swap |

---

## 7. Gaps surfaced

Per the marketing-site gap discipline: surfaced, not unilaterally resolved beyond the in-spec workaround.

- **G-A006-1 — `recent_donations` is not public-renderable.** It declares `requiredPermission: 'view_donation'` and is an admin record-detail widget with no public config; it will not render for an anonymous visitor (and publishing live donor amounts is a privacy smell). **Workaround:** dropped from the public donate page; the "momentum/impact" intent met by an impact `text_block` + the giving-levels band. The 30 generated donations stay in the admin CRM. **Recommended action:** accept the workaround; if a public "X gifts this month / $Y raised" band is wanted later, it needs a new public-safe aggregate widget — a separate lift, not this build.
- **G-A006-2 — slug-bound event/blog detail widgets can't be curated against regenerated data.** `event_description` / `event_registration` (and a curated `blog_pager` post page) require a fixed `event_slug` / post slug, but the generator re-slugs events and posts every reset. **Workaround:** curate only the slug-agnostic listing/index pages; rely on the system's native event-detail rendering and the generator's own post-detail pages (which already carry `text_block` + `blog_pager`). **Recommended action:** accept; this is the correct architecture for reset-surviving pages. If a hand-authored detail page is ever wanted, it needs a curated event/post with a stable slug excluded from the generator's wipe — a separate decision.
- **G-A006-3 — `board_members` needs a curated collection the generator doesn't produce.** The generator makes contacts, not a board. **Workaround:** seed a `board_members` collection (4 entries) carried in the demo bundle as `payload.collections` so the reset re-establishes it. **Recommended action:** implement as part of the build (it's in scope for the build session, not a deferral).
- **G-A006-4 (watch, not blocking) — form definitions in the reset path.** Confirm whether `ContentImporter` carries form definitions; if not, the contact form must be ensured via `DatabaseSeeder`'s form seed and the handle matched. **Recommended action:** the build session verifies and wires; documented here so it isn't found late.

No *new widget* gap exists — the inventory covers every page.

---

## 8. What the build session does (pointer)

See `sessions/demo-site-pages-build.PROMPT-DRAFT.md`. In short: author pages 1–6 + clip `/demo` in the CMS; seed the `board_members` collection; export the single format-1.1.0 bundle to `sessions/public website/demo-site-pages.json` + a deployed runtime copy; add the `ContentImporter` step to `DemoBaselineSeeder` after CRM generation; verify the whole reset re-establishes the populated site via the `APP_ENV=demo` local dress-rehearsal (session-321 isolated-stack recipe). It is a normal local session (it touches app code — seeder + fixture — unlike this design-only session).
