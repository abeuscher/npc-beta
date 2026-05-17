# NNN. Page Builder — In-Page Text Editing, Handle-Based Reorder & Inline Formatting Affordance · DRAFT FOR REVIEW

> **Draft for the user to review.** Critical-path, Beta-1-blocking. The current "select the whole panel → edit everything in the right Inspector" model does not work well enough for beta testers. This restores in-page text editing, replaces whole-panel selection with explicit handle + Edit affordances, solves the inline rich-text formatting UX as a first-class problem, and adds standing tests that keep the builder preview and the public site in appearance lock-step.

One sentence: make the page builder editable *on the page* — drag via a real handle, trigger the Inspector via an explicit Edit affordance, edit text in place with a properly-designed inline formatting toolbar, all gated to the widgets where it's safe, and keep the preview and public render provably in lock-step.

---

## Stub reference (emergent, critical-path forcing function)

No `release-plan.md` entry or `session-outlines.md` stub yet — emergent. Forcing function: **beta testers cannot author pages effectively with the present model.** The whole widget panel is one selectable target and all editing (including text) happens in the right-hand Inspector; this is too indirect to ship. This session is grounded in two completed research passes (architecture + feature history) whose load-bearing findings are folded in below — do not re-derive them, but confirm specifics against the live code at start and adapt to drift.

**Load-bearing facts (canonical for this session's design):**

- The builder preview is **server-rendered HTML injected via `v-html`** behind a transparent click-to-select overlay (`PreviewRegion.vue`; `.preview-region__html { pointer-events: none }`). There is **one render path** — `WidgetRenderer::render()` — for both public and preview. There is no client-side model of the text; the text exists only inside an opaque server HTML string.
- Inline editing **existed** (session 137, Livewire) and was **never removed for cause** — it was de-scoped for sequencing (139) then dropped by attrition in the 147–152 Vue rewrite. The **only** design objection on record: a floating rich-text **toolbar over multiple editable regions** caused UX conflict — plain contenteditable shipped fine. That single constraint still applies and shapes Phase 3.
- Dormant scaffolds the rewrite never cleaned up, ready to re-activate: `data-config-key`/`data-config-type` annotations on **6 widget templates** (TextBlock, Hero, ThreeBuckets ×2, EventsListing, DonationForm), and the `.inline-editable` CSS at `public/css/admin.css:677–692`.
- Persistence/security seams already hold: the config-save allow-list includes `content`; `PageWidget::saving` sanitizes richtext. No schema, no new API — inline editing reuses `updateLocalConfig()` → debounced save.
- Two hazards are intrinsic and must be designed around, not discovered: **(a) token substitution** runs before render — writing serialized DOM back to config bakes resolved `{{tokens}}` and destroys templates; **(b) the preview-refresh echo** — every save re-renders and swaps the `v-html` blob, destroying a live editor mid-keystroke.

---

## Canonical UX requirements (from the product owner — these are not negotiable shape, only mechanism is open)

1. **Drag handle, top-left, hover-in.** A real handle element (not the whole surface) anchored top-left of each widget panel, hidden by default, **fading/sliding into view on hover**, carrying a clear drag icon so the user knows it's draggable. Reorder behaviour/persistence is unchanged — only the grab target changes.
2. **Selection model replaced.** Whole-panel selection is removed. Selecting a panel happens by **dragging it, or clicking in its body area**. Separately, there must be an **explicit "Edit" affordance** — a top-right per-panel control that reveals on hover (label/icon, e.g. says "Edit") — that the user unmistakably associates with **opening the Inspector**. The user must never be guessing what triggers the style panel.
3. **The rich-text toolbar problem must be solved well.** Formatting controls **must exist** — but they **cannot live in the right-hand Inspector** once text is edited inline (incoherent as an experience). This piece is split out and solved as a first-class UX problem: an inline/contextual formatting affordance bound to the actively-edited text. **Quill replacement is explicitly on the table** — owner's inclination is Quill remains the right tool, but if a swap is ever justified, this is the session to decide it (see Open Questions).
4. **Builder ↔ public appearance lock-step, tested regularly.** Because builder-preview and public render are *not* presently guaranteed to match appearance-wise, this session adds automated tests that assert they stay in lock-step — a standing regression guard, not a one-off check.
5. **Column / repeater management is simplified, not removed.** For widgets with an arbitrary number of repeated units (the canonical example: **PricingChart** columns; also ThreeBuckets-style sets), once text is edited *inline* the Inspector repeater no longer needs to manage text — it collapses to **structural** management only: a **column-specific table of the repeated units stacked as selectable rows, each with an order control and a delete/trash icon, plus an add ("+") button at the bottom**. This is a deliberate simplification of the existing `RepeaterField.vue` / `RepeaterRowField.vue` surface — the text moved inline; what remains is add / remove / reorder of the units. It is a *structural* affordance and stays in the Inspector (it is not text, so it does not belong in the inline formatting toolbar).

---

## PricingChart — the canonical stress test (validated; accommodations are mandatory, not optional)

**PricingChart is the diciest widget and the required acceptance test for Phases 2/2b/3.** A read of `PricingChartDefinition.php` + `template.blade.php` + `styles.scss` validated the following — the design must satisfy these, not gate PricingChart out:

- **Layout is *not* the risk.** The columns container is one shared row grid (`grid-template-rows: … repeat(var(--pc-attr-rows),auto) …`; columns are `subgrid; grid-row: 1/-1`). Tracks are `auto`, so editing existing text that changes height **self-heals across columns**. Inline *text* editing is layout-safe by construction; do not over-engineer here.
- **Binding must be path-addressed and multi-node — this overrides the original "single editable key" framing.** PricingChart has text/richtext at three depths: top-level (`heading`, `subheading`, `footnote`), `columns[i].{eyebrow,title,price,lead_content}`, `columns[i].attribute_rows[j].{label,value}`, plus CTA labels. `data-config-key` must carry a **path** (e.g. `columns.2.attribute_rows.0.value`) and inline edits write to that path in the raw config tree. Inline-eligibility = "has one or more addressable, token-free, non-data-driven text/richtext nodes," evaluated per-node — *not* "a single key."
- **PricingChart has zero dormant `data-config-key` hooks** (it was not one of the 6 annotated templates). Every anchor here is net-new and nested — the "cheap re-activation" framing does not apply to the hard widget; budget for it.
- **Text edits vs structural edits diverge on refresh.** A text-content edit is locally refresh-suppressible (the Phase 2 rule). A structural edit (add/remove/reorder a column or attribute row) changes `$maxAttrRows` → the widget-wide grid track count and every other column's placeholder padding → it **must** re-render server-side. The refresh-suppression rule is **text-only**; structural mutations are always a server round-trip. Stating this wrong yields misaligned columns.
- **Empty-state bootstrapping.** The template renders a node only `@if (value !== '')`; a blank field has no DOM anchor and a new chart (`columns => []`) renders almost nothing. Inline-eligible fields must render an **empty editable placeholder wrapper** even when blank, and structural "add" affordances must be reachable from selection/Inspector independent of rendered content. This is a cross-widget template-pattern change PricingChart makes unavoidable.
- **Mixed plain/rich at one visual level** (`title` plaintext vs `price`/`value` richtext, side by side): the formatting toolbar must engage on richtext nodes and correctly stay disabled on plaintext nodes (`data-config-type`-driven). PricingChart exercises the full matrix — use it as the toolbar correctness test.

If the structural model satisfies PricingChart, every simpler widget falls out. Treat a green PricingChart round-trip (text + nested structure + buttons) as a Phase-2b/3 exit criterion.

---

## 40-widget inline-edit safety pass — eligibility rule + canonical exempt set (validated)

A full pass over every widget (`app/Widgets/*`) confirmed the design and produced the rules below. **Canonical — do not re-derive.**

**Eligibility rule (load-bearing):** inline-eligibility is **opt-in via an explicit `data-config-key` / `data-config-type` annotation on a genuine display-prose node in the template** — it is **never** auto-derived from the schema `type`. The pass proved why: many fields are honestly typed `text`/`richtext` yet are semantically CSS values, attribute values, or chart config. A "make all text/richtext fields editable" rule would ship an editable CSS box on the page. The exempt set below is the canonical **negative test set**.

**Tier A — already neutralized (listed for completeness; no action, must stay excluded):** all `subtype=url` / `select` / `textarea` / `number` fields, and the `{{token}}`-bearing card/link *templates* (`BlogListing.content_template`, `BlogPager.prev_template`/`next_template`, `Carousel.caption_template`, `EventsListing.content_template`, `Nav.parent_template`/`child_template`) — covered by the type system + per-instance token-gating + data-driven exclusion. Also slug/URL/embed config: `DonationForm.success_page`/`amounts`, `ProductCarousel.success_page`, `MapEmbed.map_input`, `VideoEmbed.video_url`, `SocialSharing.mastodon_instance`, `Logo.link_url`, `Image.link_url`.

**Tier B — the genuine traps (declared `text`/`richtext`, token-free, rendered, but must NEVER be annotated inline-editable):**

| Widget | Field | Why it would break |
|---|---|---|
| PricingChart | `gap` | CSS value → inline `style` |
| ThreeBuckets | `gap` | CSS value → `--bucket-gap` CSS var |
| Image | `max_width` | CSS length → inline `style="max-width:…"` |
| Image | `alt_text` | renders only into `alt=""` |
| BoardMembers | `image_aspect_ratio` | parsed (`explode('/')`) into `padding-bottom` % |
| Nav | `branding_text` | renders into logo `alt=""` |
| BarChart | `x_label` | `json_encode`'d into `<script type="application/json">` for Chart.js |
| BarChart | `y_label` | same — Chart.js axis config |

**Guard:** add a regression test asserting **no Tier-B field key carries a `data-config-key` annotation** in any widget template — so a future template edit cannot silently reintroduce the trap. This is the audit's durable artifact.

**Two documented couplings (expected behaviour, not bugs — do not "fix", just don't be surprised):**
- `Logo.text` renders as visible prose *and* into the logo `alt=""`. It is a valid inline target; editing the visible node implicitly updates the alt (consistent — acceptable).
- `TextBlock.content` is scanned read-only for `ql-align-center`/`-right` to derive CTA alignment. Inline-editing content can shift button alignment on next render. Read-only inference, no write-back corruption — note in the log, no mechanism needed.

The only genuine inline-editable display-prose across the non-content widgets is the `heading` field on a handful (DonationForm, EventCalendar, EventsListing, MapEmbed, ProductCarousel, SocialSharing) — all already covered by the empty-field placeholder accommodation. PricingChart, TextBlock, Hero, ThreeBuckets remain the substantive targets. No NEW accommodation is required beyond those already in this prompt.

---

## Decided design — rich-text editor & toolbar (settled; do not relitigate in-session)

**Quill is retained. The formatting controls are a fully custom Vue toolbar driven by Quill's public API** (`quill.format()` / `quill.getFormat()` / the `editor-change` event), with Quill's built-in toolbar module disabled. This is a first-class, documented Quill pattern, not a workaround; the engine, the Delta/HTML it emits, and therefore the existing `PageWidget::saving` sanitization boundary are **unchanged** — no migration, no contract change, no storage-format change. A swap to another editor is **off the table** unless an unforeseen blocker surfaces, in which case stop and surface to the user (do not pick an alternative in-session).

Three intrinsic risks are **named design constraints for Phase 3**, to be engineered from line one, not discovered:

- **C1 — Selection/focus preservation (the primary footgun).** Clicking a toolbar control must not collapse the editor selection. Use `@mousedown.prevent` on the toolbar controls (or explicit capture-and-restore of the range). This is non-optional and is most likely what caused first-pass pain.
- **C2 — One shared, app-level toolbar bound to the active editor.** Not one toolbar per editor. A single toolbar component re-targets whichever Quill instance is focused. This makes the 137 multi-region conflict structurally impossible and is the clean answer to the multiple-editors concern. The genuine work here is **instance lifecycle**, not binding: Quill instances must mount/dispose cleanly as widgets are (de)selected and as the `v-html` preview re-renders — this is the *same* problem as Phase 2's preview-refresh suppression and must be solved together (dispose listeners, no leaks, preserve/restore selection across a refresh).
- **C3 — Font picker = registration, not difficulty.** Register the font whitelist via `Quill.import('formats/font')` + an attributor and ensure the corresponding font CSS is loaded; link editing gets a small Vue popover rather than Quill's raw `prompt()`. Bounded plumbing, explicitly not a risk item.

---

## Open questions to resolve at session start (the genuinely-open decisions)

1. **Capability-gate shape.** Per-widget-definition declaration (PHP property/method on the widget class — no migration; recommended) vs. a `widget_types` column (schema change — must be surfaced). "Inline-eligible" is **per-node, path-addressed** (validated against PricingChart): a node is editable if it is a text/richtext field reachable by a config path (including inside nested repeaters), is token-free in this instance, and is not data-driven/templated. Not "a single key." The open part is only *where the declaration lives*, not the model.
2. **Scope split (Rule 11).** Recommended split if it inflates: **Session A = Phases 1–2 (+2b)** (interaction model + first safe inline slice + preview-refresh/lifecycle + the simplified column/repeater control), **Session B = Phases 3–4** (custom toolbar + lock-step parity tests + widen the gate). Confirm the split with the user rather than running one mega-session.
3. **Parity-test relationship to the stale-stylesheet drift guard.** Coordinate with `sessions/NNN. Post-Incident Test Integrity — Stale-Stylesheet Drift Guard.md`: its guard = *served bundle ≡ saved settings*; this Phase 4 = *builder preview render ≡ public render appearance*. Complementary. If the drift-guard session shipped first, extend its harness; if not, keep the seams distinct and cross-reference.

---

## Phases

### Phase 1 — Interaction-model rework (independently shippable)

Replace whole-panel selection. Add: (a) the top-left hover-in **drag handle** with a drag icon, repointing the existing `vuedraggable` `handle=` selector at it (reorder payload/persistence untouched); (b) body-click / drag selects the panel; (c) the explicit top-right hover-in **"Edit" affordance** that opens the Inspector for that widget. No inline text editing yet. Playwright: selection via body-click, reorder via the handle, Inspector opens via the Edit affordance, and the *old* whole-panel-select behaviour is gone.

### Phase 2 — Inline text editing on safe widgets, behind the capability gate

Activate a Vue contenteditable layer in `PreviewRegion.vue` on `[data-config-key]` nodes **of the selected, inline-eligible widget only**, where `data-config-key` is a **config path** (supports nesting, e.g. `columns.2.title`). Bind edits to the value at that **raw config path**, route through `updateLocalConfig()` → existing debounced save, on blur + debounce. Implement the **capability gate** (Open Q1, per-node path-addressed) and the **token/data-driven exclusions**. **Refresh-suppression is text-only:** a text-content edit suppresses the preview refresh for the active node (mirror the `RichTextField.vue` dirty-guard); a *structural* edit (Phase 2b) always re-renders. Inline-eligible fields must render an **empty editable placeholder wrapper even when blank** (templates currently omit empty nodes — this is a cross-widget template-pattern change). Confirm sanitization + allow-list cover every inline path (`content`, `heading_N`, `body_N`, and nested `columns.*.…`). Start with the unambiguous targets: **TextBlock**, **Hero** (token-free), **ThreeBuckets** bodies; then prove the nested case on **PricingChart**. Playwright: type-in-place → blur → reload → persisted (incl. a nested `columns[i].attribute_rows[j].value`); a `{{token}}`-bearing node is **not** inline-editable; a data-driven widget is **not** inline-editable; a plaintext node shows **no** formatting toolbar.

### Phase 2b — Simplified column / repeater management (structural; Inspector-side)

Collapse the existing `RepeaterField.vue` / `RepeaterRowField.vue` surface to **structure only**: a table of the units stacked as selectable rows, each with an order control + a delete/trash icon, plus a bottom "+" add button — text content now lives inline (Phase 2), so the repeater becomes add / remove / reorder. **PricingChart is the canonical case and forces three accommodations the simple shape lacks:** (1) **nested repeaters ≥2 deep** — manage `columns`, and drilling into a selected column, its `attribute_rows` (drill-in / breadcrumb, not one flat table); (2) **per-row structural controls** — e.g. the per-column `emphasize` toggle is structural-not-text and rides in the row, so the table carries a small structural slot beyond reorder/trash/add; (3) **buttons split** — a CTA's label is inline text (Phase 2) while `url`/`style` are structural and stay in a control. Every structural mutation re-renders server-side (PricingChart's cross-column subgrid alignment recomputes — never refresh-suppressed). Add-affordances must be reachable from selection/Inspector even when the widget renders empty (`columns => []`). Persistence is the existing repeater config save path — unchanged shape, restructured UI. Playwright: add a column → reorder → delete → persists; drill into a column → add/reorder/delete an attribute row → persists; toggle `emphasize` from the row; a CTA label edits inline while url/style edit structurally; all independent of inline text round-trips.

### Phase 3 — The inline formatting toolbar (custom Vue over Quill API; user-in-the-loop on look/feel)

Per the **Decided design** section: build the **one shared, app-level Vue toolbar** that drives the active Quill instance via its API (built-in toolbar module disabled) — bold / italic / link / lists / headings / font as the content model supports. Constraints **C1 (selection/focus preservation), C2 (single shared toolbar + clean instance lifecycle, solved together with Phase 2's refresh-suppression), C3 (font-whitelist registration + link popover)** are implemented from the start, not retrofitted. The Inspector retains only **non-text** style (appearance / layout / colour). The *engineering* is settled and objective — verify it yourself (Playwright: apply each format, selection survives the click, state reflects on `editor-change`). The *look and feel* is a user-judgment surface: build the working candidate, then **pause and put it in front of the user** before widening to more widgets.

### Phase 4 — Builder ↔ public appearance lock-step parity tests (standing guard)

Add regular automated tests asserting the builder preview render and the public render are appearance-equivalent for a representative widget set — leveraging the single `WidgetRenderer` path. Assert structural + composed-style equivalence (the same seam the stale-stylesheet incident exploited at the bundle layer; this guards the render layer). Coordinate with the drift-guard session (Open Q4). This is a deliverable, not a check-and-discard.

### Phase 5 — Widen the gate *(optional / may defer to a successor)*

Once Phases 2–3 hold, extend inline eligibility to the remaining safe annotated widgets (DonationForm heading, EventsListing static heading — *not* its data rows). Each addition re-runs the Phase 2 + Phase 4 guards. Defer to a successor session if scope split per Open Q3.

---

## Out of scope

- Inline editing of **data-driven / templated** content (EventsListing rows, listing/pager bodies, anything token-bearing) — gated off by design, permanently in this session's scope.
- Any Fleet Manager contract / `/api/health` / schema change. CRM contract stays v2.3.0. Surface immediately if the work appears to need any of these.
- A page-builder architecture rewrite, a new state model, or replacing `v-html` server-rendered preview with client-rendered widgets — explicitly not this; the design works *with* the single render path.
- Re-introducing a floating toolbar that spans multiple simultaneous editable regions (the documented 137 failure).
- Mobile/breakpoint editing affordances — authoring is a desktop-builder surface; responsive *output* is unchanged and already handled elsewhere (E5/E7). Note, don't build.
- The stale-stylesheet *bundle* drift guard itself — that is its own session; Phase 4 here is the *render-parity* complement, coordinated, not duplicated.

---

## Testing

- **Slow groups:** none expected.
- **New Pest:** capability-gate eligibility logic; token/data-driven exclusion; sanitization + allow-list coverage of every inline key; **the Tier-B exempt-set guard** (no Tier-B field key carries a `data-config-key` annotation in any widget template — the 40-widget-pass durable artifact).
- **New Playwright:** Phase 1 interaction model (handle reorder, body-click select, Edit-affordance opens Inspector, old whole-panel select gone); Phase 2 inline-edit save round-trip + the two negative gates; Phase 2b column/repeater add-reorder-delete persistence; Phase 3 each format applies with selection surviving the toolbar click; Phase 4 the builder↔public parity harness (standing).
- **Manual (user judgment):** the Phase 3 toolbar **look and feel only** (the editor choice and engineering are settled per the Decided design section — not a user decision). Paused for at the Phase 3 handoff. Pest and Playwright run sequentially, never in parallel.

---

## Closing steps

Follow the close gate in the base prompt. Session-specific:

- **Log file:** `sessions/NNN. Page Builder — In-Page Text Editing, Handles & Inline Formatting — Log.md`.
- **Artifact:** the new interaction model; inline editing live on the safe widget set behind the capability gate; the contextual formatting affordance + the recorded Quill-vs-alternative decision; the builder↔public parity harness; new Pest/Playwright; `VERSION` `0.NNN.x`; any carry-forward to a successor session if split.
- **Branch:** `session-NNN/N` (final iteration), once numbered.
- **Next session:** the deferred phase-slice if split per Open Q3, drafted only when the user names it.
