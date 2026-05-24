# Demo Site Pages — Design Spec

Design spec for the demo install's public website. The demo node (the public auto-login showcase — session 321) ships with a curated pretend-nonprofit site that demonstrates the product to a prospect, sitting on top of the CRM data `demo:reset` already generates. This document is build-ready: the build session authors these pages in the CMS from this spec, exports their JSON, wires them into the demo baseline so the daily reset re-establishes them, and verifies via the `APP_ENV=demo` dress-rehearsal.

**This is a design session deliverable — no application code, no page JSON is produced here.** The companion draft `sessions/demo-site-pages-build.PROMPT-DRAFT.md` is the build session's prompt.

Companion to the marketing-site specs (`homepage-layout-spec.md`, `about-layout-spec.md`, `pricing-page-spec.md`, `contact-page-spec.md`, `demo-page-spec.md`). It reuses their section-band / appearance-config / page-JSON-import conventions rather than re-deriving them; read `build-summary.md` for those conventions and the standing gap list.

---

## 0. Resolved open questions

| Question | Decision | Why |
|---|---|---|
| **Does the supplanting touch fresh-install defaults, or only the demo node?** | **Demo-node only.** Leave the "Default Content State" stub's fresh-install seeding (one sample event + one sample post) alone. The demo pages are a demo-server asset. | Changing what every customer's fresh install seeds is a separate, larger product decision. The demo site realizes the *showcase* intent better, but only for the demo node. **Surfaced for ratification** — this is the one decision that changes downstream scope. |
| **Contact-page map:** real address or placeholder? | **Generic placeholder.** `MapEmbed` configured to a recognizable city center (Portland, OR — `45.5152, -122.6784`), no street address implying a real location. | The org is fictional; a real address would be misleading. A city-center embed renders populated without implying a real office. |
| **Fictional org identity** | **Mill Creek Conservancy** (see §1). | A broadly relatable community / environment / education cause, distinct from the real `nonprofitcrm.com` brand, that exercises every CRM record type the demo generates (events, donations, memberships, posts, products). |

---

## 1. Org identity

**Name:** Mill Creek Conservancy
**One-line mission:** *Protecting and restoring the Mill Creek watershed through volunteer stewardship and community education.*
**Voice/tone:** warm, grounded, civic, plainspoken. A small but credible local nonprofit — not a slick national charity, not amateur. First-person plural ("we", "our volunteers"). Concrete over abstract ("we planted 4,000 native seedlings" beats "we drive impact").
**Visual feel:** outdoors / nature / community. Pages draw from the sample-image library's **`still_photos`** category for hero and supporting imagery (the same category the `RandomDataGenerator` attaches to its events/posts, so the curated pages and the generated records share a visual register). The store page draws from **`product_photos`**.

This identity anchors all copy below. It maps cleanly onto the generated data:

| Generated record | Reads in the demo site as |
|---|---|
| Events (6, future-dated) | Stewardship days, work parties, the annual benefit |
| Donations (30) | Gifts to the watershed fund |
| Memberships (15) | "Friends of Mill Creek" supporter tiers |
| Posts (5, type=post under `news/`) | Field-notes blog entries |
| Products (6) | Native-plant kits + merch in the store |
| Contacts (40) | The supporter base behind it all |

---

## 2. Page list

Six authored pages + one reused page = **seven** (within the 5–7 target). All slugs are the demo install's public URLs.

| # | Page | Slug | Purpose in the prospect demo | Widget handles |
|---|---|---|---|---|
| 1 | **Home / landing** | `/` (slug `home`) | The first impression: mission, a live featured event, a donation CTA, recent posts — proves the site is alive against real data. | `hero`, `text_block`, `events_listing` (or `this_weeks_events`), `three_buckets`, `blog_listing`, `image` |
| 2 | **Events** | `/events` | Shows the event system populated: a calendar + listing pulling the generated events; registration is reachable from each event's detail. | `events_listing`, `event_calendar`, `text_block` |
| 3 | **Donate** | `/donate` | The conversion surface: giving levels, a working donation form, and social proof from real donations. | `three_buckets`, `donation_form`, `recent_donations`, `text_block` |
| 4 | **About / board** | `/about` | The credibility page: who runs the org, the story, a named board. | `text_block`, `image`, `board_members` |
| 5 | **Contact** | `/contact` | A working contact form + a map, demonstrating form submissions and the map embed. | `web_form`, `map_embed`, `text_block` |
| 6 | **News (blog index)** | `/news` | The blog index against the generated posts; each post detail (generator-built) carries `text_block` + `blog_pager`. | `blog_listing` (index); post detail = `text_block` + `blog_pager` (generator-made) |
| 7 | **Demo gate** | `/demo` | Reused dark-side conversion page, clipped from the main marketing site (`demo.json`). Not redesigned. | `layout` (50/50 split) + `text_block` + button (see `demo-page-spec.md`) |

**Slug note — blog prefix.** The generator writes posts at `config('site.blog_prefix', 'news') . '/' . slug`. The blog index slug **must** match that prefix so `blog_listing` and the post-detail routing line up. This spec uses `news` (the default). If the demo install's `site.blog_prefix` is set to anything else, the build session sets the index slug to match it (and `recent posts` on Home inherits the same).

**Optional 5-page contraction.** If the build session wants to stay at the lower end of 5–7, fold News into a Home "recent posts" band and drop the standalone `/news` index, and/or merge Donate into a Home band. Lean: **keep all seven** — each surfaces a distinct widget family the prospect should see, and the daily reset cost is identical.

---

## 3. Per-page composition + copy

Conventions: bands are `layout` widgets carrying the background (backgrounds on the layout, not on widgets — except the `/demo` split per its spec); alternate light/dark bands for rhythm; content sits in the standard container; appearance via `appearance_config`. Follow the marketing-site band patterns in `home.json` / `about.json`.

### 3.1 Home — `/`

Band-by-band, top to bottom:

1. **Hero band** — `hero`. Full-width image hero (a `still_photos` watershed/forest image), headline overlay.
   - H1: **A healthy creek is everyone's work.**
   - Subhead: *Mill Creek Conservancy brings neighbors together to protect and restore the watershed that runs through our community.*
   - Primary CTA: **Join a work party** → `/events`. Secondary CTA: **Donate** → `/donate`.
   - Keep overlap-nav disabled (site convention, gap G12).
2. **Mission band** (light) — `text_block` + `image` in a Text+Image cell pattern (the side-by-side pattern the marketing site uses to side-step G15/G16/G20).
   - H2: **What we do**
   - Body (≈3 short paragraphs): *Mill Creek winds twelve miles through the heart of our town before it reaches the river. For decades it carried the runoff of everything upstream. Since 2009, our volunteers have planted native buffers, pulled invasive ivy by the truckload, and turned a neglected drainage into a living corridor. / We run on small gifts and big weekends. Every restoration day is led by neighbors, not contractors. Every dollar stays in the watershed. / Come see what a few hundred committed people can do for one creek.*
3. **Featured event band** (light/tinted) — `events_listing` configured to show the **next upcoming event** (limit 1, order by `starts_at` ascending), or `this_weeks_events` if a tighter window is wanted.
   - Band intro `text_block` H2: **Coming up on the creek**
   - Renders against generated events (all future-dated 1–30 days, so this is never empty).
4. **Donation CTA band** (dark) — `three_buckets`. Three giving levels as scannable cards.
   - Intro H2 (white): **Your gift plants the next mile.**
   - Bucket 1 — **$35**: *Plants ten native seedlings.*
   - Bucket 2 — **$100**: *Funds a volunteer work party's tools and supplies.*
   - Bucket 3 — **$500**: *Restores a full streambank section.*
   - Each bucket CTA → `/donate`.
5. **Recent posts band** (light) — `blog_listing` configured to a small grid (limit 3, newest first) reading `news/` posts.
   - Intro H2: **Field notes**
   - Renders the generated posts' titles + thumbnails (generator attaches `still_photos` to each post).
6. **Closing CTA band** (light) — `text_block` CTA-only (the G17 placeholder pattern): **Ready to get your boots muddy?** → **See upcoming events** (`/events`).

### 3.2 Events — `/events`

1. **Page-head band** (light) — `text_block`.
   - H1: **Get out on the creek.**
   - Body: *Every event is hands-on, family-friendly, and led by people who know the watershed. No experience needed — we bring the gloves, the tools, and the coffee.*
2. **Calendar band** (light) — `event_calendar`. Month view; the generated events (future-dated 1–30 days) populate the current and next month.
3. **Listing band** (light) — `events_listing`. Card list, order by `starts_at` ascending, no limit (shows all 6 generated events).
   - Each card links to the event detail.
- **Event detail** is **system-rendered** from the event-detail template, which carries `event_description` + `event_registration`. The build session does **not** author a per-event page — it confirms the demo install's event-detail template renders those two widgets, and that `event_registration` is reachable (registration write is allowed for the `demo` role per session 321). **See §6 gap E-1** if the demo install has no event-detail template — in that case the build session authors one detail page wired to a sample generated event, accepting that the specific event rotates each reset.

### 3.3 Donate — `/donate`

1. **Page-head band** (light) — `text_block`.
   - H1: **Every dollar stays in the watershed.**
   - Body: *We're a small organization with a simple promise: your gift goes to the creek, not to overhead. Here's what your support makes possible.*
2. **Giving-levels band** (light/tinted) — `three_buckets` (same three levels as the Home CTA band, §3.1.4; keep the amounts consistent across pages).
3. **Donation-form band** (light) — `donation_form`. The working give flow.
   - Intro `text_block` H2: **Make a gift**
   - **Mail/payment note:** on the demo node, outbound is firewalled (Stripe fails closed) and mail is forced to `log` (session 321). The form renders and accepts input; the prospect sees the flow, the charge does not complete externally. The build session does **not** need live Stripe keys — the demo's value is the flow, not a real charge. No spec change needed; noted so the build session doesn't chase a "payment didn't go through" non-bug.
4. **Recent-donations band** (light) — `recent_donations`. Social proof from the 30 generated donations (≈80% active → ≈24 show). Configure to show donor first-name + amount or anonymized per the widget's privacy options; lean to showing first-name + amount since the generated contacts are fake.
   - Intro H2: **Recent supporters**

### 3.4 About / board — `/about`

1. **Story band** (light) — `text_block` + `image` (Text+Image cell).
   - H1: **Founded by neighbors. Run by neighbors.**
   - Body (≈3 paragraphs): *Mill Creek Conservancy started in 2009 around a kitchen table, after a winter storm sent the creek over its banks and into three backyards. A handful of residents decided the watershed needed advocates who actually lived along it. / Fifteen years later we steward eleven restoration sites, run a native-plant nursery, and host more than thirty volunteer events a year. We've never had more than two paid staff. / The work is unglamorous and the results take seasons to show. We think that's exactly why it matters.*
2. **Board band** (light) — `board_members`.
   - Intro `text_block` H2: **Our board**
   - **Data source — important:** `board_members` reads from a **content collection** (`collection_handle`), **not** from CRM contacts. The `RandomDataGenerator` makes contacts but **no board**. So the build session must seed a curated board collection (≈5–6 named members with title + short bio + portrait). See §5.2 and **§6 gap E-2**. Suggested board (names + one-line bios the build session can use verbatim):
     - **Dana Okафор**, *Board Chair* — Watershed ecologist; led the 2011 buffer-planting program.
     - **Marcus Reyes**, *Vice Chair* — Owns the hardware store on 9th; quartermaster for every work party.
     - **Priya Natarajan**, *Treasurer* — Retired credit-union CFO; keeps every dollar accounted for.
     - **Helen Whitebear**, *Secretary* — Tribal liaison and oral-history keeper for the lower creek.
     - **Tom Ledbetter**, *Member* — Third-generation farmer; donated the eastern restoration easement.
     - **Aisha Greene**, *Member* — High-school biology teacher; runs the youth stewardship program.
   - Portraits draw from the `still_photos` library (no people-portrait category exists — see §6 gap E-3; lean: use `still_photos` and accept non-portrait imagery, or leave the image field unset so the widget falls back to its placeholder, whichever reads cleaner in the dress-rehearsal).

### 3.5 Contact — `/contact`

1. **Single band** (light or gradient hero, following `contact.json`'s pattern) — a `web_form` + `map_embed`, two-cell.
   - H1: **Get in touch.**
   - Body: *Questions about an event, a gift, or how to get involved? Send us a note — a real person reads every message.*
   - **Form** (`web_form`): name / email / phone / message + a "what are you interested in?" select (Volunteering / Donating / Membership / Other). Mirrors the marketing `contact.json` form. Submissions fire the `form_submission` System Email (session 291) — on the demo node mail is `log`, so this exercises the flow without sending.
   - **Map** (`map_embed`): centered on Portland, OR (`45.5152, -122.6784`) per §0; no pin implying a real office, or a generic "we're in the watershed" pin at city center.
   - **mailto fallback:** include the marketing site's `mailto:` convention; on the demo node this can stay a placeholder (`hello@millcreek.example`) — the demo isn't a real inbox.

### 3.6 News (blog index) — `/news`

1. **Page-head band** (light) — `text_block`.
   - H1: **Field notes from the creek.**
   - Body: *Restoration updates, volunteer spotlights, and the occasional heron sighting.*
2. **Index band** (light) — `blog_listing`. Full index, newest first, reading `news/` posts (the 5 generated posts).
- **Post detail** is **generator-built**: each generated post already carries a `text_block` (10–20 paragraphs of body) + a `blog_pager` (prev/next). The build session authors **no** post-detail page — it confirms the index links resolve to the generated post details and the pager flows. The generated post titles are faker sentences (e.g. "Consequatur ut ..."), which read as lorem rather than on-brand. **Lean: accept the faker titles** for v1 (they prove the blog renders); a follow-up could seed curated posts. Flagged, not blocking — see §6 gap E-4.

### 3.7 Demo gate — `/demo`

Reused verbatim from the main marketing site's `demo.json` (see `demo-page-spec.md`). 50/50 dark/light split; dark side H1 *Instant Demo* + placeholder copy; light side a single large "Click Me" button → `/demo/enter` auto-login. **Not redesigned this session.** See §4.2 for how it's brought into the demo install.

> **Note (minor, surfaced):** a `/demo` gate *inside* the demo install is somewhat redundant — the visitor is already in the demo. The base prompt directs reusing it and placing it in the demo install's nav, so this spec honors that. Lean: keep it in the page set (it's part of "the whole site" the prospect tours) but place it in the **footer** nav rather than the primary header nav, so it doesn't compete with the real nonprofit nav. Build session's call; flagged for ratification.

---

## 4. Nav, chrome, and `/demo` integration

### 4.1 Header / footer nav

- **Header nav** (`nav` widget, site chrome): Home · About · Events · Donate · News · Contact. A prominent **Donate** button-style item at the right is conventional for a nonprofit — use the chrome's primary-action affordance if one exists at build time.
- **Footer nav:** the same links + **Demo** (`/demo`) + the `mailto:` + social (`social_sharing` if the chrome footer carries it). Privacy/Terms are out of scope here (they're a separate fresh-install starter-page concern, untouched per §0).
- **Chrome defaults — do not hard-code.** E16 (Header/Footer Defaults Overhaul, session 322) may land before the build session and change the production chrome defaults. **The build session uses whatever the production chrome defaults are at build time** and does not author around the current ones. This spec specifies *which links exist*, not the chrome's styling.

### 4.2 Bringing `/demo` into the demo install

Keep it simple (the user has said the exact mechanism doesn't matter):

- The main marketing site's `/demo` page already exists as `sessions/public website/demo.json`.
- The build session copies that bundle into the demo page set (alongside the new `demo-*.json` exports — see §5.1) and imports it through the same `ContentImporter` path as the other pages. No clipping logic, no main-site dependency at runtime — it's just one more bundle in the demo page set.
- The reused page keeps its `pending-copy` lorem on the dark side (the real copy swap is a separate, already-tracked follow-up — `build-summary.md` item 3). Not this build session's job.

---

## 5. Reset wiring — how the curated pages survive `demo:reset`

This is the load-bearing integration. Specified precisely so the build session just implements it.

### 5.1 Exported bundles

The build session authors each page in the CMS, then exports each to JSON. Two homes:

- **Working archive (this repo, reviewable):** `sessions/public website/demo-{home,events,donate,about,contact,news}.json` + the reused `demo.json` (already present) + a board-collection bundle `demo-board-collection.json` (see §5.2).
- **Shipped copies (must be in the production image):** the bundles the reset reads at runtime must ship in the `--no-dev` image. Place the canonical copies under **`database/seeders/demo_pages/`** (a new directory that ships with the repo). The working-archive copies under `sessions/` are the reviewable record; the `database/seeders/demo_pages/` copies are what the seeder loads. The build session keeps them identical (or makes `database/seeders/demo_pages/` the single source and the `sessions/` copies a convenience mirror — its call; lean: single source under `database/seeders/demo_pages/`, mirror into `sessions/public website/` for review).

### 5.2 Board-members collection

`board_members` reads a collection, and the generator makes none. The build session seeds a curated board collection. Two viable mechanisms — build session picks:

- **(a) Via the page bundle.** The `ContentImporter` already covers collections (session A001 extended it to collections). Export the board collection as part of `demo-board-collection.json` (or fold it into `demo-about.json` if the exporter bundles the collection a page references). Importing the bundle on each reset re-establishes it. **Lean: this** — keeps the board on the same import path as the pages, no separate seeder logic.
- **(b) Via a small seeder.** A `DemoBoardSeeder` creating the collection rows directly. Only if (a) proves awkward.

The `about` page's `board_members` widget config references the collection handle the bundle establishes.

### 5.3 The reset path

Today (`DemoResetCommand` + `DemoBaselineSeeder`, session 321):

```
demo:reset (full)
  → migrate:fresh --seed        # framework starter pages + base seeders
  → DemoBaselineSeeder::run()   # wipe scrub-data + RandomDataGenerator baseline
```

After this build session:

```
demo:reset (full)
  → migrate:fresh --seed            # framework starter pages
  → DemoBaselineSeeder::run():
       1. import curated demo pages   # NEW — ContentImporter over database/seeders/demo_pages/*.json
       2. wipe scrub-data             # existing
       3. RandomDataGenerator(BASELINE) # existing — events/donations/posts the listing widgets read
```

Precise implementation for the build session:

- Add a `importDemoPages()` step to `DemoBaselineSeeder::run()`, called **before** the existing `wipe()` + `generate()`, inside the same resolved-super_admin acting context (the importer/page authoring may need an authenticated actor; the seeder already logs in the actor).
- For each bundle in `database/seeders/demo_pages/` (deterministic order — `home`, `about`, `events`, `donate`, `contact`, `news`, `demo`, plus the board collection first if separate):
  ```php
  $bundle = json_decode(file_get_contents($path), true);
  app(\App\Services\ImportExport\ContentImporter::class)->import(
      $bundle,
      new \App\Services\ImportExport\ImportLog(),
      ['replace_duplicate_pages' => true, 'import_media' => true],
  );
  ```
- `replace_duplicate_pages: true` so the curated Home/About **override** the framework starter pages of the same slug (confirm the importer matches duplicates by slug — it does for pages). `import_media: true` so any embedded sample images come in.
- **Why pages-before-CRM-baseline:** the listing widgets (`events_listing`, `recent_donations`, `blog_listing`) query records at *request* time, so import order vs. generation order doesn't affect rendering. But importing first keeps the actor context tidy and means a `--soft` reset (which skips `migrate:fresh` and re-runs `DemoBaselineSeeder` only) also re-establishes the pages. Put the import inside `DemoBaselineSeeder::run()` (not only in the full path) so `--soft` restores a defaced page too.
- **Idempotency:** `replace_duplicate_pages` makes re-import idempotent for pages (overwrite by slug); the collection import must likewise upsert by handle (the importer's collection path does — confirm in the dress-rehearsal). Re-running `demo:reset` returns the same site, not duplicated pages.

### 5.4 The Faker / `--no-dev` blocker (carried from session 321)

`DemoBaselineSeeder` drives `RandomDataGenerator`, which uses model factories → `fakerphp/faker`, currently in **`require-dev`**. The production demo image is built `--no-dev`, so `demo:reset`'s baseline step fails with `Class "Faker\Factory" not found`. **This blocks the live demo node regardless of this session's pages.** The build session owns the resolution (it owns the reset wiring). Options, in order of preference:

1. **Move `fakerphp/faker` to `require`** (one-line `composer.json` change). Simplest; faker is small; the cost is shipping it in prod. **Lean: this.**
2. Build the demo node from a dev-deps image (the `public-dev` build the e2e stack uses).
3. Replace the factory-based baseline with non-factory seeding (largest change).

This is an application/dependency change and therefore **out of scope for this design session** — recorded here so the build session resolves it as a prerequisite to its dress-rehearsal passing on a `--no-dev`-equivalent stack.

---

## 6. Gaps surfaced

Composition gaps and content-seeding tasks the build session must handle. **None require a new widget** — every page composes existing widgets per the inventory. These are data/wiring/deploy items, not missing widgets.

| ID | Gap | Disposition |
|---|---|---|
| **E-1** | Event detail page — relies on the demo install having an event-detail template carrying `event_description` + `event_registration`. | Build session confirms the template exists and renders; if not, authors one detail page wired to a generated event (accepting the specific event rotates each reset). |
| **E-2** | `board_members` reads a collection; the generator makes no board. | Build session seeds the curated board collection (§5.2), bundled on the import path. |
| **E-3** | No people-portrait category in the sample-image library (carry of marketing-site gap G8). | Use `still_photos` for board portraits, or leave image unset for the widget's placeholder. Accept for v1; real portraits are a launch-time photography task. |
| **E-4** | Generated post titles/bodies are faker text (read as lorem, not on-brand). | Accept for v1 — proves the blog renders against real records. A follow-up could seed curated posts. Not blocking. |
| **E-5 (deploy)** | Faker in `require-dev` breaks `demo:reset` on the `--no-dev` image (§5.4, carried from 321). | Build session resolves (lean: faker → `require`) as a prerequisite to its dress-rehearsal. |
| **E-6 (minor)** | `/demo` gate inside the demo install is redundant (§3.7). | Keep in footer nav, not primary header; flagged for ratification. |
| **carryover** | Marketing-site standing gaps (G3/G5/G11/G12/G15–G21, see `build-summary.md`) apply to these pages too — they're built with the same widgets. | The same accepted workarounds hold (Text+Image cells, overlap-nav off, CTA-only placeholder pattern). No new resolution needed. |

---

## 7. Out of scope (restated for the build session)

- New widgets — composition only.
- Changing fresh-install default content (the Default Content State stub's fresh-install behavior is untouched; demo-node only per §0).
- The live `nonprofitcrm.com` marketing site.
- FM-side demo work (node identity, version pin, egress firewall).
- Real photography and the `/demo` dark-side real copy (launch-time tasks, already tracked in `build-summary.md`).
