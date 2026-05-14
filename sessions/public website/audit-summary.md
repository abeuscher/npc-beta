# Public Website Build — System Audit (PMW1)

A snapshot of the design-system surface area available to the marketing-website track. Inventory only — no recommendations, no editorial judgments. Each section answers one question: *what is actually in the system, today, that a page can use?*

Captured at session 284 (PMW1, 2026-05-14). Subsequent phases should re-check anything load-bearing; this is a starting point, not a permanent reference.

---

## 1. Type scales

The typography system carries **nine element rows**, each with a concrete value for every knob. These are the only headings and body-text rows the system styles:

| Element | Default size | Default weight | Default line-height |
|---------|-------------:|---------------:|--------------------:|
| `h1`    | 2.5 rem      | 700            | 1.2 |
| `h2`    | 2.0 rem      | 700            | 1.25 |
| `h3`    | 1.5 rem      | 700            | 1.3 |
| `h4`    | 1.25 rem     | 700            | 1.35 |
| `h5`    | 1.125 rem    | 600            | 1.4 |
| `h6`    | 1.0 rem      | 600            | 1.4 |
| `p`     | 1.0 rem      | 400            | 1.5 |
| `ul_li` | 1.0 rem      | 400            | 1.5 |
| `ol_li` | 1.0 rem      | 400            | 1.5 |

Per element, the system also stores: font family, letter spacing, case (none / upper / lower / capitalize), margin (top/right/bottom/left), padding (top/right/bottom/left). The two list rows additionally store `list_style_type` and `marker_color`.

**Font buckets:** three — heading family, body family, nav family — assignable independently. **Font catalog:** nine families, all available out of the box: Georgia, Inter, Lato, Merriweather, Montserrat, Open Sans, Playfair Display, Raleway, Source Sans 3. Default family across all elements: Inter.

**Where this lives:** stored as a JSON value in the `typography` site setting; resolved by `App\Services\TypographyResolver`; edited in the admin at **CMS → Theme → Text Styles tab**.

**For the marketing build:** no additional sizes, weights, or families exist. Any layout that calls for something not on the list above is a gap.

---

## 2. Button styles

The system carries **five button variants**, each with the same set of knobs:

- **Primary** — solid blue background, white text. Default for primary CTAs.
- **Secondary** — outlined, gray border, dark text. Default for secondary CTAs.
- **Text** — text-only, no background or border, blue text.
- **Destructive** — solid red background. (Admin-side; unused on public marketing.)
- **Link** — inherit-color, no decoration. (Admin-side; unused on public marketing.)

Per variant the operator can set: border radius (sharp / slightly-rounded / rounded / pill), background color, text color, border color, border width (0 / 1px / 2px), hover behavior (darken / lighten / opacity), font weight (400 / 600 / 700), text transform (none / uppercase).

**Two additional configuration groups** sit beside the variants: an **Icon Buttons** group (icon size, icon placement left/right/icon-only, mobile-collapse toggle) and a **Form-Append Buttons** group (default variant for buttons attached to form inputs).

**Where this lives:** stored as a JSON value in the `button_styles` site setting; edited in the admin at **CMS → Theme → Buttons tab**. Saving triggers a public CSS bundle rebuild via the build server.

**For the marketing build:** Hero widget CTAs surface **only three of the five variants** — primary, secondary, text-only (no destructive, no link). Other widgets that render buttons (Web Form submit, etc.) have their own per-widget variant pickers. Any layout that calls for a button shape not in the three above — for example a ghost-on-dark variant for the dark-band sections — is a gap, even though the variant slot may exist on the design-system side.

---

## 3. Appearance config schema

Every widget and every column layout carries an `appearance_config` block. The system composes inline CSS from it at render time via `App\Services\AppearanceStyleComposer`. The shape:

**On widgets:**

- `text.color` — hex value. Source of truth for the widget's foreground text color.
- `text.shadow` — boolean. Adds a drop shadow, useful on photo backgrounds.
- `background.color` — hex value. Solid background fill.
- `background.gradient.gradients[]` — array of gradient layers (linear / radial), each with from / to colors, angle, and alpha.
- `background.image_url` — direct URL fallback for a background image. (Spatie media collection `appearance_background_image` is preferred when present.)
- `background.use_current_page_header` — boolean. When true, pulls the page's header image as background.
- `background.alignment` — one of nine positions (top-left, top-center, …, bottom-right). Used for image / gradient anchoring.
- `background.fit` — `cover` or `contain`.
- `layout.padding.{top,right,bottom,left}` — pixel values.
- `layout.margin.{top,right,bottom,left}` — pixel values.
- `layout.background_full_width` — boolean. Whether the background slab extends edge-to-edge.
- `layout.content_full_width` — boolean. Whether the content area extends edge-to-edge.

**On column layouts:**

Same shape, minus the widget-only knobs: layouts carry `background.color`, `background.gradient`, `background.alignment`, `background.fit`, `layout.padding`, `layout.margin`. Layouts do **not** carry `text.color`, `text.shadow`, background image, or full-width on appearance_config (full-width on layouts lives on `layout_config` instead).

**Two quiet rules to remember:**

- Padding / margin values of `0` are treated as *"no override; let the intrinsic SCSS default apply."* So a concrete `0` and a missing key produce the same rendered CSS. This means the section-band convention (150 / 0 / 150 / 0) explicitly opts out of left/right padding and lets the container width win.
- Widgets sitting inside a layout slot have their full-width flags forced to `false` at render time, regardless of what appearance_config says.

---

## 4. Widget inventory

The system registers **38 widgets** via `App\Services\WidgetRegistry`, each in its own folder under `app/Widgets/`. Below, grouped by what the marketing build can plausibly use them for. Categories come from each widget's `category()` method.

### Likely useful for marketing pages

| Handle | Folder | Category | What it is |
|--------|--------|----------|------------|
| `hero` | Hero | content, most_used | Full-bleed banner with content + CTAs + optional gradient / image / video background. Used for hero bands and any band that needs in-section CTAs. |
| `text_block` | TextBlock | content, most_used | Rich-text content block. The workhorse for any band of body copy. |
| `image` | Image | content, media | Single image with optional caption / link. |
| `carousel` | Carousel | content, media | Auto-rotating slide carousel (Swiper.js under the hood). |
| `video_embed` | VideoEmbed | content, media | YouTube / Vimeo / direct video. |
| `logo_garden` | LogoGarden | content, media | Grid of small logos. Customer-logo row pattern. |
| `bar_chart` | BarChart | content | Animated bar chart. Allowed motion per brief. |
| `three_buckets` | ThreeBuckets | content | Three-column icon + heading + body grid. The closest existing match for a feature-grid pattern. |
| `board_members` | BoardMembers | content | Portrait + name + role + bio grid. |
| `social_sharing` | SocialSharing | content | Share buttons. |
| `map_embed` | MapEmbed | content | Embedded map. |
| `web_form` | WebForm | portal, forms | Generic form, configurable fields. **Demo page form lives here.** |

### Unlikely for marketing pages (but present)

- **Blog / events / portal scaffolding** — `blog_listing`, `blog_pager`, `event_calendar`, `events_listing`, `event_description`, `event_registration`, `this_weeks_events`, `donation_form`, `recent_donations`, `recent_notes`, `membership_status`, `portal_*` (login, signup, account dashboard, change password, contact edit, event registrations, forgot password), `product_carousel`, `product_display`. These all expect live CRM / portal data and aren't right for marketing-page content.
- **Admin-only / system widgets** — `logo`, `nav`, `quick_actions`, `setup_checklist`, `random_data_generator`, `memos`, `server` (Contracts).

### What the inventory does not include

- No **tabbed feature explainer** — the brief's named-candidate gap. Closest substitute: stacked text blocks under headings, or a vertical `three_buckets`. Track surfaces this in the gap report.
- No **headline-only widget** distinct from the hero — anywhere a section needs just a heading on a band, a text_block is the only option today.
- No **hero variant** that lays content cleanly without `overlap_nav: true` for non-top-of-page placements. Hero defaults work but make assumptions about being at the top of the page.

(These are observations, not action items. Gap-resolution discipline per `sessions/tracks/public-marketing-website.md`.)

---

## 5. Sample image library

The product ships a curated set of placeholder images under `resources/sample-images/`, exposed to the page builder via `App\Models\SampleImage`. Four categories:

| Category constant | Folder | File count | Subject matter |
|-------------------|--------|-----------:|----------------|
| `CATEGORY_LOGOS` | `logos/` | 14 | Recognizable brand logos (mixed SVG / PNG). Used by `logo_garden`. |
| `CATEGORY_PORTRAITS` | `portraits/` | 11 | Headshot-style photos of people. Used by `board_members`, demo content. |
| `CATEGORY_PRODUCT_PHOTOS` | `product-photos/` | 10 | Object / product photography. |
| `CATEGORY_STILL_PHOTOS` | `still-photos/` | 9 | Mixed landscapes / scenes / lifestyle. The closest substitute for the "real product screenshot inside a soft container" pattern in the references is **not** in this set — there is no screenshot-of-the-product category. |

**Where this lives:** files in `resources/sample-images/{category}/`; the `SampleImage` model walks each folder on demand.

**For the marketing build:** every placeholder lands in the cleaned home / about / pricing / contact / demo pages from one of the four folders above. Per the brief, each placeholder is marked (widget label or custom field note) so a later pass can swap it for real photography. No screenshot-of-the-product category means hero / feature sections that want a product preview today must use a still photo as a stand-in.

---

## 6. Page JSON import mechanism

The brief assumes JSON import works but does not pin where it lives. Located here for the record.

**Location:** Admin → **CMS → Pages → "Import Bundle" header action** (top-right of the Pages list page). Same action surface also exists on the Posts list and Templates list.

**Mechanism:** uploads a single JSON file (up to 50 MB) to a private storage directory, then hands it to `App\Services\ImportExport\ContentImporter::import()` for parsing and persistence. The Filament action lives at `app/Filament/Actions/ImportBundleAction.php`.

**Format requirement:** bundle must include `format_version: "1.x.y"` (major version 1). The exporter writes `1.1.0` today; the importer accepts any 1.x.y.

**Slug-matching behavior:** pages with matching slugs are **overwritten in place** — the existing widget tree on the matched page is wiped and the imported tree becomes canonical. Pages with new slugs are created. Soft-deleted pages are restored if the import re-introduces them.

**Permission gate:** `update_page`. The action is hidden from users without it.

**Feedback:** a notification reports `Imported N page(s), M template(s)` on success. Warnings (e.g. unknown widget handle, dropped field) accumulate and surface in the same notification.

**Reverse direction:** the **"Export selected"** bulk action on the same Pages list streams a JSON download covering whichever pages are checked. This is the action that produced `existing-home-page-export.json` in this folder.

**For the marketing build:** every page produced by the track is re-imported through this surface. No CLI / API path exists today.
