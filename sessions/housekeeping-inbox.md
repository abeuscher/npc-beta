# Housekeeping Inbox

Small items noticed between sessions. One bullet per item, free-form text. When the inbox accumulates 5–10 items, batch them into a housekeeping session (e.g. Phase E's E12 "Housekeeping Batch 2"). Items that grow into "own session" shape get promoted to `release-plan.md` entries.

This file is the only home for items that are *small enough to bundle but large enough to break scope when absorbed mid-session by a feature*.

---

## Inbox

*(Items destined for the next housekeeping batch session. Items default to **pre-Public-Website-Complete** unless tagged `[post-milestone]` — see Rule 12 in `release-plan.md`.)*

- Hero widget: button-group alignment control (left / right / center).
- Text editor: changing text color should not change the editor's own preview color — white text becomes illegible on the light editor background.
- Default paragraph and list-item padding: replace the zero-reset with ~6px top / ~12px bottom on `p` and `li`.
- Hero widget: expose a control for how the hero text block's max-width is bound inside the widget.
- `[post-milestone]` Random Data Generator widget: add Organizations to the entity-generation list. *(Admin tool — doesn't block the public website demo.)*
- Logo widget: respond to the hero widget's text-color override when the logo sits on a full-bleed hero (so the logo doesn't fight the chosen text color).
- Logo widget: default `href` = site home, with override available in the inspector.
- Default logo image: ship a placeholder logo asset so a fresh install has one.
- Logo widget: text field → rich text + appearance control (small scope; just enough to set color / weight / size).
- Default button style refresh — quick visual pass (not blue-on-blue, gentle gradients, hover states). Pre-design-system-editor stopgap.
- Column layouts: default `layout_config.background_full_width` to `true` for parity with widget defaults shipped at E10 (verified s282 — widget_types ships bg=true/content=false; column layouts still fall back to bg=false in `AppearanceStyleComposer::resolveColumnLayoutFullWidth`).
- Link-color mechanism consolidation (s290): there are now **three** link-recolor mechanisms and they need a clarity pass. (1) Nav widget's own `--nav-link-color` (Nav inspector, sets its own link color). (2) Hero's `nav_link_color` / `nav_hover_color` reach-over — a Hero knob that restyles the *site-chrome nav* (a sibling widget) but only when `overlap_nav: true`; effectively dead on this site because G12 forced overlap off everywhere (logo-contrast). (3) New generic `text.link_color` → `--np-link-color` (s290 G22 resolution) — recolors content links inside any widget; does NOT reach the nav. These are not duplicates (they target different elements), so the s290 link-color work did **not** make #2 removable — #2 is the real smell (a widget reaching across to restyle another widget), and that decision belongs to **G12** (Hero↔Nav overlap-nav / logo-contrast coupling), not to the link-color feature. Also a real UX-confusion point: a Hero inspector showing both "Link Color" (#3, hero content) and "Nav Link Color" (#2, site chrome, overlap-only) will confuse operators — fix is labeling + the G12 overlap decision. Cross-ref G12/G21/G22 in `sessions/public website/gap-report.md`.
- Widget non-colour hardcoded values (s300 survey — scope-fenced, deliberately not migrated): rgba shadows / gradient stops / opacity tints — BlogPager (`--color-surface-hover` hover tint), MapEmbed + SocialSharing (rgba scrims under the kept `#fff` accents), Nav (dropdown `box-shadow` rgba), PricingChart (`rgba(0,0,0,.08)` hairline), ProductCarousel (fade-gradient `#000000` stops + rgba placeholder/mask). Not in the `--np-color-*` contract by design; held as deliberate exceptions in `WidgetColorTokenConsumptionTest`'s allow-list. Revisit only if a shadow/gradient token vocabulary is ever introduced.
- Per-widget colour-picker PHP schema defaults don't flow from the theme (s300 survey): BoardMembers (`pane_color #ffffff`, `border_color #cccccc`), LogoGarden (`container_background_color #ffffff`), Nav (`link_color #1d4ed8`, `drop_link_color #1d4ed8`, etc.) — concrete hex defaults in the widget Definition PHP, so a fresh widget instance ignores the Theme palette until the operator picks a colour. s300 tokenised the unreachable SCSS dead-fallbacks (zero render change); the *schema defaults themselves* are appearance-config territory and 301/scheme-adjacent, not s300's SCSS-consumption scope. Decide later whether per-widget pickers should default to a token rather than a literal.
- Contact hero readability overrides (s290, related): the hero also stacks two coexisting "make content readable on the gradient" workarounds — the WebForm widget's white-card `background.color` (G21 form-label workaround) and the email-fallback widget's `text.link_color: #ffffff` (G22 resolution). Both work side by side, not urgent. Same housekeeping pass: once G21 lands the same `--np-*-color` var pattern the white card may be unnecessary; either way annotate the remaining overrides in `sessions/public website/contact.json` so a future editor understands *why* the hero carries them.
- `[test-integrity]` s296 Tier-3 deferred (explicitly out of s296 scope; the audit is closed — these are dispositions, not a re-audit): `DonationCheckoutTest` "creates a pending donation record on checkout initiation" — factory-echo / fixture-mirrors-reality; rewrite to drive the real checkout initiation or rescope the name. `QuickBooksSyncTest` + `QuickBooksCustomerMatchingTest` "creates a … receipt" sync-job test names are subject-mocked → rename-rescope to what they actually assert (the redaction/skip sibling tests are fine, leave them). `ProductCarouselTest` "seeder total widget count includes product_carousel" — magic-count change-detector; assert the specific handle's presence, not a total. `tests/e2e/page-builder/full-width-matrix.spec.ts` "every widget handle renders without console errors" — name overclaims the narrow regex it actually checks; rename to match. `tests/e2e/page-builder/layout-inspector.spec.ts` "…persists background + padding" — name implies a render round-trip it does not perform; keep-with-note (rename or add the round-trip assertion).
- `[test-integrity]` s296 incidental (same anti-pattern as Finding 9, found while editing `TaxReceiptTest` but outside the audit's fixed scope, so noted not chased per the no-widening rule): `TaxReceiptTest` "includes only active donations in receipt calculation" and "excludes donations from other tax years" re-implement the `Donation` query filters inline and assert their own copy — fold into a future pass that routes them through the real `DonorsPage::buildBreakdown()` (or the receipt service) like Finding 9 did for the two in-scope tests.
- Export-artifact TTL sweeper (s303): queued bundle exports write zips to `storage/app/private/exports/bundles/{uuid}/` and are never reaped — they accumulate until manually cleared. Add a scheduled sweep (delete artifacts older than ~24–48h) alongside the existing `backup:clean`/`media-library:clean` schedule wiring. Implementation deliberately out of s303 scope; the gated download route + uuid dir-per-export are already in place, so this is purely a janitor task.
- `[test-integrity]` e2e `tests/e2e/event-registration/ticket-tier-picker.spec.ts` is **`describe.skip`'d** as of the e2e-stabilization handback. After the two shared CI-only infra causes were fixed (Composer dev-deps in the e2e image; the Vite build volume mounted into nginx) the rest of the Playwright suite is green, but this one suite stayed intermittently unstable across runs (pass / flaky / hard-fail). Cause is specific to its shape: it creates a fresh published event + page + `event_registration` widget per test via raw SQL and immediately hits that *freshly-created* public page, so it eats (a) a cold first render the SCSS warm-up doesn't cover (warm-up only hits the pre-seeded `/` and `/news`) and (b) a probable `event_registration` widget dependency on the public widget bundle (`public/build/widgets/`), which the isolated e2e stack deliberately never builds (no build server in CI, by recorded design). **Look at it next housekeeping session and probably delete it** — unless we decide to either give the e2e stack the widget bundle (architectural; contradicts the recorded "no external build in CI" stance) or rework the spec to warm/await its per-test page deterministically. Coverage note: skipping drops the multi-quantity event-registration spinner's *e2e* coverage (C2a, s279); service-layer/feature Pest coverage is unaffected.
- `[test-integrity]` A003 deleted the first test of `tests/e2e/importer/site-import-export.spec.ts` — "renders both sections and surfaces snapshot counts on Export Site". Surfaced via the A003 diagnostic digest: after fixing the spec's URL (it navigated to `/admin/site-import-export` but Filament's auto-slug for `SiteImportExportPage` is `/admin/site-import-export-page` — that URL fix is permanent), the click on the Export Site action button correctly mounted the action server-side (the modal HTML rendered into the DOM with the right live snapshot counts: 15 pages, 6 templates, theme (2 design keys), 44 media files), but Alpine never flipped the modal's `isOpen` state to true in headless chromium so the modal stayed hidden. The same `requiresConfirmation()` pattern is exercised cleanly by the importer approve table-action test and by Resource ListPage `->form([...])` header actions (the Import Bundle action on `/admin/pages`). Only the **header-action `requiresConfirmation()` modal on a custom `Filament\Pages\Page`** combination fails in CI — likely belongs to Filament's open-modal dispatch wiring on custom pages but the diagnosis was out of scope for a stabilization session. The companion second test in the same file ("Import Site analyzes the bundle on upload and reveals gated toggles", which exercises the `->form([...])` Import Bundle action, mirroring the working `content-bundle-two-step.spec.ts` shape) is kept. Coverage tradeoff: loses e2e coverage of the Export Site confirmation modal's snapshot-count rendering; the server-side `SiteImportExportPage::getSnapshotCounts()` calculation is straightforward and could carry a one-test Pest covering page/template/media counts if a future session wants the floor.
- `[test-integrity]` A003 deleted five additional flaky e2e tests after surfacing the full flake history across six CI runs on `session-A003/2`. User stance (recorded in the A003 session log) is delete-by-default for any spec that intermittently fails without a working fix; per-test waitForTimeout bumps are not the answer when CI variance is the root cause. The deletions:
  - `tests/e2e/page-builder/full-width-matrix.spec.ts` — removed the `Editor — column bg vs content toggle independence` describe block (lines 318-368). 33% flake rate across the A003 runs on the `(bg:false, content:false)` Editor variant. Coverage tradeoff: page-builder Vue editor's column-layout container/grid class rendering is no longer e2e-verified per state, but the public-side matrix in the same file (`Public-side — column-layout full-width matrix`, plus the `Editor — column bg parity with public side` block at line 245) still covers the rendered output paths.
  - `tests/e2e/importer/events-update-strategy.spec.ts` (whole file) — single-test file. The 120s `import-progress-phase-done` wait was just barely sufficient and tipped over in one A003 run. Coverage tradeoff: re-import-with-update-strategy for the events importer is no longer covered at e2e level; the same workflow shape is covered for contacts / donations / memberships / invoice-details in their respective `*-update-strategy.spec.ts` files, and the events import happy-path stays covered by `tests/e2e/importer/events-happy-path.spec.ts`.
  - `tests/e2e/importer/content-bundle-two-step.spec.ts` (whole file) — single-test file. FilePond `processing-complete` poll just exceeded its 60s timeout. The `ImportBundleAction` it exercises is the **same shared action class** invoked from `tests/e2e/importer/site-import-export.spec.ts` (the kept "Import Site analyzes the bundle on upload" test) — same analyzer flow, same gated-toggle reveal, same data-testid surface — so no actual coverage is lost.
  - `tests/e2e/importer/donations-happy-path.spec.ts` (whole file) — single-test file. The A003 run-5 failure was actually in the shared `resetAndLogin` auth helper's `/admin/login` navigation (45s timeout, not retry-recovered), but the user noted the spec has been chronically flaky since it was added. Coverage tradeoff: donations import end-to-end is no longer covered as a dedicated happy path; `tests/e2e/importer/donations-update-strategy.spec.ts` still exercises donations import (with the re-import-with-update-strategy flow), and `tests/e2e/importer/donations-error-report.spec.ts` covers the error reporting path.
  - `tests/e2e/importer/donations-mapping-indicator.spec.ts` — removed only the first test ("rows render incomplete by default and flip to complete when mapped"). The second test in the file ("mapping select exposes a search input when opened") is kept — it never flaked across the six A003 runs and covers a distinct UI behavior. Coverage tradeoff: the row indicator's incomplete → complete visual transition during column mapping is no longer e2e-verified; the underlying mapping logic is covered by all the importer happy-path and update-strategy specs that complete a full mapping.

---

## Promotion candidates (need release-plan.md entries)

*(Items too big to bundle. Need their own entry before scheduling.)*

- ~~**Table widget.**~~ Promoted at 282 audit → **E15** in `release-plan.md`. Pre-Public-Website-Complete.
- ~~**Header / footer defaults overhaul.**~~ Promoted at 282 audit → **E16**. Pre-Public-Website-Complete.
- ~~**Borders on widget controls + columns.**~~ Promoted at 282 audit → **E17**. Pre-Public-Website-Complete (may fold into Design System Editor track if it lands first).

---

## Folded into existing entries

*(Items absorbed by an existing release-plan entry — captured here as a paper trail.)*

- **Text editor reachability fix + Quill full-screen button + Playwright usability test** → folded into **E8 (UI/UX Sprint)**. E8 already covers Quill drag-resize handle; expand its scope at session start.
- **Default button style long-term parameterization** → folded into the **Design System Editor** track (already on radar — "buttons first").

---

## Recently dispositioned

*(Log of what left the inbox and where it went. Keep the last ~2 batches' worth.)*

- **2026-05-13 (session 282 close):** 16 housekeeping items surfaced from operator running list:
  - 11 → Inbox (above)
  - 3 → Promotion candidates (Table widget; Header/footer overhaul; Borders pass)
  - 2 → Folded (E8 absorbs text-editor scope; Design System Editor absorbs button parameterization)
  - 1 → Verified as already shipped at E10 for widgets (s267); column-layouts parity gap lifted into Inbox as its own item.

---

## Disposition rules

When walking the inbox at session start / close, each item leaves via one of:

- **(a)** Fold into the next available housekeeping batch session (the default for inbox items).
- **(b)** Fold into an existing planned entry — note here under "Folded into existing entries" and cross-ref in the target entry's session prompt.
- **(c)** Promote to its own `release-plan.md` entry — move from Inbox to "Promotion candidates" first, then add the entry, then remove from this file.
- **(d)** Drop as no-longer-relevant — note briefly under "Recently dispositioned" with the date.

No states, no priorities, no timestamps beyond "Recently dispositioned." The list is the system.
