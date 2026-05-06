# Code Review — Large Files & Partial Extraction Candidates

**Date:** 2026-05-04
**Scope:** Repo-wide audit for (a) files over 500 lines and (b) inline blocks (HTML strings, repeated Blade markup, repeated form schemas, scoped CSS) that could be lifted into partials/components.
**Posture:** Advisory only. No code changes. Each item below identifies the smell, names the proposed extraction, and flags the rough payoff vs. risk.

---

## 1. Files over 500 lines

Counted with `find … | xargs wc -l` against `app/`, `resources/`, `routes/`, `config/`, `database/`, `tests/`. Sorted descending.

| Lines | Path | Type |
| ----: | ---- | ---- |
| 1193 | `app/Filament/Pages/Concerns/InteractsWithImportWizard.php` | PHP trait |
| 1017 | `app/Filament/Pages/ImportContactsPage.php` | Filament page |
|  955 | `app/Filament/Pages/Concerns/InteractsWithImportProgress.php` | PHP trait |
|  930 | `resources/js/page-builder-vue/stores/editor.ts` | Pinia store |
|  846 | `tests/Feature/PageBuilderApiTest.php` | Pest test |
|  786 | `app/Filament/Pages/ImportInvoiceDetailsProgressPage.php` | Filament page |
|  754 | `resources/js/page-builder-vue/components/LayoutInspectorPanel.vue` | Vue SFC |
|  676 | `app/Http/Controllers/Admin/PageBuilderApiController.php` | Controller |
|  666 | `app/Filament/Pages/ImportEventsProgressPage.php` | Filament page |
|  653 | `app/Filament/Pages/ImportProgressPage.php` | Filament page |
|  620 | `resources/js/page-builder-vue/components/PreviewCanvas.vue` | Vue SFC |
|  611 | `tests/Feature/ImportEventsTest.php` | Pest test |
|  594 | `app/Filament/Pages/ImportDonationsProgressPage.php` | Filament page |
|  579 | `app/Filament/Pages/ImportMembershipsProgressPage.php` | Filament page |
|  572 | `tests/Feature/ContentImportExportTest.php` | Pest test |
|  563 | `app/WidgetPrimitive/ContractResolver.php` | Service |
|  544 | `app/Filament/Pages/Settings/FinanceSettingsPage.php` | Filament page |
|  533 | `app/Services/AssetBuildService.php` | Service |
|  528 | `tests/Feature/ImportFinancialTest.php` | Pest test |
|  528 | `app/Http/Controllers/Admin/RecordDetailViewBuilderApiController.php` | Controller |
|  519 | `resources/js/page-builder-vue/components/primitives/GradientPicker.vue` | Vue SFC |
|  516 | `app/Services/ImportExport/ContentImporter.php` | Service |
|  515 | `resources/js/theme-editor/TypographyPanel.vue` | Vue SFC |
|  507 | `resources/views/filament/pages/import-progress.blade.php` | Blade view |
|  505 | `app/Services/Import/ImportSessionActions.php` | Service |

Of the 25 files, **14 are part of the CSV-importer subsystem** (5 progress pages, 5 progress views, 2 traits, 1 wizard page, 1 service). The biggest win available in this codebase is concentrated there.

---

## 2. Highest-leverage extractions (importer cluster)

### 2.1 Five `*ProgressPage` classes share the same 4 abstract-method shapes

`InteractsWithImportProgress` defines `emptyDryRunReport()`, `processOneRow()`, `accumulateOutcome()`, `buildRowContext()`, `cancelRedirectUrl()`. All five concrete pages (`ImportProgressPage`, `ImportEventsProgressPage`, `ImportDonationsProgressPage`, `ImportMembershipsProgressPage`, `ImportInvoiceDetailsProgressPage`) implement the same surface — they only differ in which entity they create and which fields they map. The shared trait is already 955 lines and growing.

**Proposed extraction:**

- Move per-entity row-shaping logic out of the page classes into dedicated `Importers\<Entity>\RowProcessor` (and `OutcomeAccumulator`) classes. Each becomes ~150-200 lines, tested in isolation.
- The Filament page becomes a thin shell: routing, query string, view rendering, polling. Target: <250 lines per page.
- `InteractsWithImportProgress` keeps only the chunked-tick state machine, mount/dry-run/commit lifecycle, finalisation, downloads. Should fall under ~500 lines.

**Payoff:** Eliminates ~1,200 lines of near-duplicate page code. Makes per-entity behaviour unit-testable without spinning up a Livewire component. **Risk:** Medium — touches the core import path; needs solid test coverage on the existing flows before refactoring.

### 2.2 Five progress Blade views are 80% the same template

`import-progress.blade.php` (507) is the most complete; the other four (`import-events-progress` 406, `import-donations-progress` 377, `import-notes-progress` 349, `import-organizations-progress` 300, `import-memberships-progress` 297, `import-invoice-details-progress` 274) are partial copies of it. Each contains the same five UI chunks:

1. **PII rejection card** — identical except for one prose word ("contacts" vs "events" vs …).
2. **Stats grid** (`Would create / update / skip / errors`) — identical structure, copy-pasted.
3. **Skip-reasons panel** — same scaffold, different reason keys.
4. **Errors table + download button** — identical.
5. **In-progress polling card** with progress bar + stats — identical.
6. **Done card** with "Save mapping?" prompt + nav buttons — identical.

`resources/views/filament/pages/partials/` already exists (one file: `button-preview.blade.php`), so the convention is in place.

**Proposed extraction** (Blade components in `resources/views/filament/pages/partials/import-progress/`):

- `pii-rejection-card.blade.php` — props: `$entityLabel`, `$piiHeaderBlocked`, `$rejectionReason`, `$piiViolations`, `$piiTruncated`.
- `dry-run-stats.blade.php` — props: `$dryRunReport`, `$createLabel = 'Would create'`.
- `skip-reasons.blade.php` — props: `$skipReasons` (array of `key => [count, message]`).
- `errors-table.blade.php` — props: `$errors`, `$errorCount`, `$identityFields`.
- `committing-card.blade.php` — props: `$processed`, `$total`, `$imported`, `$updated`, `$skipped`, `$errorCount`, `$percent`.
- `done-card.blade.php` — props: `$importSessionId`, `$importSourceId`, `$sourceName`, `$mappingSaved`, `$primaryRedirect`, `$secondaryRedirect`.
- `relational-preview.blade.php` — props: `$orgPreview`, `$tagPreview`, `$notePreview` (the awaitingDecision relational summary block).

Each progress page view shrinks to a shell of `<x-filament-panels::page>` + a phase switch + `<x-importer.pii-rejection-card …>` calls. Estimate: 7 partials of ~50–80 lines each replace ~2,000 lines of duplicated markup; per-page views drop to ~60 lines.

**Payoff:** Large. **Risk:** Low — view-only changes, easily checked visually and via existing Playwright/feature tests.

### 2.3 `InteractsWithImportWizard` (1193 lines) emits HTML as PHP strings

The trait has 8+ inline `new \Illuminate\Support\HtmlString("…")` blocks (lines 55–58, 64, 347, 392–399, 427, 440, 446, 471, 871, 1173, 1189) building wizard chrome by string concatenation. Three of those — `topNav`, `buildTemplateDownloadLink`, the auto-custom banner, the saved-mapping banner — are pure presentation that belongs in Blade. The preview-table builder (`buildNamespacedPreviewSchema`, lines 815–873) constructs an entire `<table>` in a PHP heredoc-style loop — 60 lines of HTML concatenation that should be a partial.

**Proposed extraction:**

- `resources/views/filament/forms/import-wizard/top-nav.blade.php` rendered via `view('…', [...])->render()` and wrapped in an `HtmlString`. Same for `template-download-link`, `saved-mapping-banner`, `auto-custom-banner`, `review-summary`, `review-guidance`, `review-empty-state`.
- `resources/views/filament/forms/import-wizard/preview-table.blade.php` for the namespaced preview rendering, replacing the 60-line `buildNamespacedPreviewSchema` content loop. The trait method becomes data-shaping only (build the rows array; pass to the partial).
- Move the constant button SVG in `buildTemplateDownloadLink()` into the Blade partial — no need for it in PHP source.

**Payoff:** Drops ~150 lines from the trait, makes inline copy editable without touching PHP, makes the markup designer-readable. **Risk:** Low — views are pure presentation, no behaviour change.

### 2.4 `ImportContactsPage` (1017 lines) duplicates the trait's namespaced importer flow

Compare `ImportContactsPage::processUploadedFile()` (lines 192–279) with `InteractsWithImportWizard::processUploadedFileNamespaced()` (lines 678–804): the two are 90% the same loop with different sentinel constants and a slightly different match-key derivation. Same for `runImport()` (lines 936–1016) vs `serializeColumnMaps()` + `createSessionAndLog()` (trait lines 914–1060). The contact wizard pre-dates the trait extraction, and was never folded back in.

**Proposed cleanup:**

- Generalise the trait's `processUploadedFileNamespaced()` to accept the contact's `__custom__` / `__org_contact__` / `__note_contact__` / `__tag_contact__` sentinel set as parameters. Have `ImportContactsPage::processUploadedFile()` call it with contact-specific arguments.
- Same for `runImport()` — it can lean on `serializeColumnMaps` + `createSessionAndLog` with contact-specific sentinel and field-map keys.
- Move `detectCollisions`, `wouldCauseCollision`, `seedCollisionDefaults`, `collisionResolutionSchema`, `applyCollisionResolutions` into a `HasContactCollisionResolution` trait — they're contact-only and account for ~150 lines of the page.

**Payoff:** Page drops from ~1000 to ~400 lines; collision logic becomes a tested unit of its own. **Risk:** Medium-high — contacts is the most exercised import path; needs careful side-by-side dry-run comparison against current behaviour.

---

## 3. Page-builder cluster

### 3.1 `LayoutInspectorPanel.vue` (754 lines) — Grid vs Flex sub-panels

The component is split visually into three tabs (`column-settings`, `margin-padding`, `background`) but the column-settings tab itself is two large `<template v-if>` branches (grid: lines 271–395; flex: lines 397–505). Each has its own field cluster: `gridTemplateMode`, `gap`, `align-items`, `justify-items`, `grid-auto-rows` for grid; `justify-content`, `align-items`, `gap`, `flex-wrap`, per-column `flex-basis` for flex.

**Proposed extraction:**

- `LayoutGridPanel.vue` (props: `layout`, emits `update:layoutConfig`) — wraps the grid branch.
- `LayoutFlexPanel.vue` (props: `layout`, emits `update:layoutConfig`) — wraps the flex branch.
- `LayoutCommonPanel.vue` for the `Full width / Display / Columns` block at the top of the column-settings tab.

Pull the gap input + presets into a `GapInputWithPresets.vue` (used twice in the grid panel — lines 328-348 — and once in the flex panel — lines 436-456 — currently copy-pasted).

**Payoff:** ~250 lines removed from the parent, three small reusable pieces. **Risk:** Low — clean prop boundaries; the data flow already routes through `setLayoutConfigKey`.

### 3.2 Scoped CSS dominates the largest Vue files

Of the ~615 lines below the `<template>` tag in `LayoutInspectorPanel.vue` (line 530+), `PreviewCanvas.vue` (line 395+), and `GradientPicker.vue` (line 292+), the majority is BEM-style scoped CSS. `LayoutInspectorPanel.vue` has 27 unique `.layout-inspector__*` selectors; `PreviewCanvas.vue` has 30 `.preview-canvas__*`; `GradientPicker.vue` has 30 `.gradient-picker*`.

**Proposed extraction:**

- Move each scoped block to a sibling `.scss` (or `.css`) module — e.g. `LayoutInspectorPanel.styles.css` — imported via `@import` or registered through a Vite stylesheet entry. The tags-coupled `:scoped` semantics survive because Vue applies the scope-id via the `<style scoped>` pragma equally on imports.
- Or: move the shared "field row + label + hint + input" pattern to a `<FormField>` primitive component (`label` slot, `hint` slot, default slot). The three big inspector panels (`LayoutInspectorPanel`, `TypographyPanel`, `GradientPicker`) all repeat this `__field`/`__label-row`/`__hint` triple 8–15 times each.

**Payoff:** Each Vue file drops 200+ lines of CSS, and a `<FormField>` primitive eliminates a dozen near-duplicates per inspector. **Risk:** Medium — verify scope-id propagation and ensure no specificity regressions.

### 3.3 `PageBuilderApiController.php` (676 lines) groups 4 responsibilities

The controller currently bundles widget CRUD (10 methods), layout CRUD (3 methods), reference-data lookups (`widgetTypes`, `collections`, `collectionFields`, `tags`, `pages`, `events`, `dataSources`), image management (`uploadImage`, `removeImage`, `uploadAppearanceImage`, `removeAppearanceImage`), and tree formatting (`buildTree`, `formatLayout`, `formatWidget`, `formatWidgetWithPreview`, `stripDefaults`, `filterConfigToSchema`, `filterAppearanceConfig`, `filterLayoutAppearanceConfig`).

**Proposed split:**

- `WidgetCrudController` — `index`, `store`, `update`, `destroy`, `copy`, `reorder`, `preview`.
- `LayoutCrudController` — `storeLayout`, `updateLayout`, `destroyLayout`.
- `PageBuilderLookupController` — `widgetTypes`, `collections`, `collectionFields`, `tags`, `pages`, `events`, `dataSources`, `updateColorSwatches`.
- `WidgetImageController` — the 4 image methods.
- `WidgetTreeFormatter` service — the 8 private formatter methods (`buildTree` + helpers). All four controllers depend on it via constructor injection.

`routes/admin-api.php` would route by responsibility instead of one fat controller.

**Payoff:** Each file ≤ ~200 lines; tree-formatting becomes injectable into other contexts (e.g. tests, future GraphQL/API endpoints). **Risk:** Low — splitting a controller by URL/method group is mechanical; the existing 43-test `PageBuilderApiTest` validates the surface.

### 3.4 `editor.ts` (930 lines) — split the Pinia store

The single `useEditorStore` mixes (a) widget tree state + CRUD, (b) layout state + CRUD, (c) inspector tab state, (d) preview/dirty tracking, (e) bootstrap/API client wiring, (f) widget-type registry, (g) collections/tags/pages/events lookups. That's seven concerns in one composable.

**Proposed split:**

- Keep `useEditorStore` as the *aggregate* store but delegate to focused composables: `useWidgetActions(api)`, `useLayoutActions(api)`, `useLookups(api)`, `useInspectorTabs()`, `usePreviewState()`. Each lives in its own file under `stores/` and exposes a small surface that the aggregate re-exports.
- Or: split into Pinia stores (`useWidgetStore`, `useLayoutStore`, `useLookupStore`, `useInspectorStore`) with cross-references as needed.

**Payoff:** Each unit is testable in isolation; the 930-line file shrinks to ~200 lines of orchestration. **Risk:** Medium — many components reach into the store directly; renames need codemod-style sweeps and compile checks.

---

## 4. Settings + service classes

### 4.1 `FinanceSettingsPage.php` (544) — Stripe & QuickBooks live in one file

Three logical groupings:
- Stripe (publishable key + secret keys + webhook secret + payment-method types) — lines 79–127, 134–147.
- QuickBooks OAuth + sync settings — `quickBooksSection()` 253–393, `quickBooksSyncSection()` (referenced) — about 250 lines.
- Shared "secret key set/change" UI — `secretKeySection`, `setKeyAction`, `changeKeyAction` (lines 149–251).

**Proposed extraction:**

- Move `secretKeySection`/`setKeyAction`/`changeKeyAction` into a `BuildsSecretKeyFields` trait alongside the other `Concerns/InteractsWith*` traits (it already has the right shape and is reused by `quickBooksSection`).
- Optionally split QuickBooks into its own page (`QuickBooksSettingsPage`), or keep as a section but move the section builders into a `QuickBooksSettingsSections` trait.

**Payoff:** ~300 lines moved out; the secret-key trait is reusable in any future settings page (Mailgun, S3, etc.). **Risk:** Low — the methods don't touch state outside `$this->data` and `SiteSetting`.

### 4.2 `ContractResolver.php` (563) — one resolver class with 8 entity-specific resolvers

Methods `resolveSystemModel`, `resolveNote`, `resolveDonationList`, `resolveEventOne`, `resolvePost`, `resolveEvent`, `resolveProduct`, `resolveProductOne`, `resolveMembershipOne` each pick a model and run a different filter+order+project pipeline. The resolver is the dispatch table; each handler is a discrete strategy.

**Proposed extraction:**

- Strategy pattern: `EntityResolverInterface` with a single `resolve(DataContract, SlotContext, &$cache): array` method. Concrete classes per system model (`NoteResolver`, `DonationResolver`, `EventResolver`, `ProductResolver`, `MembershipResolver`, `PostResolver`).
- `ContractResolver::resolveSystemModel()` becomes a registry lookup: `match ($contract->systemModel) { … => $this->registry[$key]->resolve(…) }`.
- Shared helpers (`resolveOrderBy`, `applySwctOrderBy`, `applyTagFilters`, `fallbackRowsFor`) can move to a `ResolverSupport` trait used by every concrete resolver.

**Payoff:** Each resolver becomes ~50 lines, unit-testable without exercising the full dispatch. The main file drops to ~150 lines. **Risk:** Medium — touches the runtime hot-path for every page render; needs benchmark + smoke-test.

### 4.3 `ContentImporter.php`, `ImportSessionActions.php`, `AssetBuildService.php` (500–533 each)

These are coherent service classes — long but cohesive. Splitting for size alone isn't justified. Spot extractions worth considering:

- `AssetBuildService::LIBRARY_SOURCES` constant (lines 21–47) holds inline JS module source heredocs. Move those JS snippets to `resources/js/build-stubs/{swiper,jcalendar}.js` and read at build time. Keeps build assets co-located with the rest of the JS.
- `ImportSessionActions` and `ContentImporter` have private helpers that could become testable static helper classes (e.g. row formatters) — but pause the refactor unless duplication shows up elsewhere.

---

## 5. Test files

`tests/Feature/PageBuilderApiTest.php` (846), `tests/Feature/ImportEventsTest.php` (611), `tests/Feature/ContentImportExportTest.php` (572), `tests/Feature/ImportFinancialTest.php` (528) are large but each test is ~15–25 lines and the file contains 15–43 distinct cases. The shared setup helpers (`apiUser`, `apiPage`, `apiWidget`, `apiLayout`, `apiChildWidget`, `apiPrefix`) at the top of `PageBuilderApiTest.php` are good candidates for a `tests/Support/PageBuilderTestHelpers.php` file (or a Pest preset under `tests/Pest.php`). That alone trims ~80 lines off the test file and lets the helpers be reused by future tests.

**Recommendation:** Don't split these solely because they're long. Long Pest files are normal when each `it()` is short. Do extract the *helpers* into `tests/Support/`.

---

## 6. Inline-block extractions worth doing in isolation

Independent of the larger restructures above:

1. **Top-nav button strings** — `InteractsWithImportWizard::topNav()` builds `← Back` / `Next →` as PHP-concatenated HTML with x-on:click handlers. Single Blade partial: `filament.forms.import-wizard.top-nav` with `$currentIndex`, `$isFirst`, `$isLast` props. Cleanest possible extraction; one method, two interpolated buttons.

2. **The "saved mapping banner" + "auto custom banner" + "detected preset banner"** — three near-identical `Forms\Components\Placeholder::make()->content(new HtmlString(...))` patterns scattered across `InteractsWithImportWizard` and `ImportContactsPage`. A single `BannerPlaceholder` factory method on the trait taking `(string $key, string $message, string $tone = 'gray')` and returning the configured Placeholder eliminates 3 separate methods and keeps all banner styling in one place.

3. **Heroicon SVG inline in `LayoutInspectorPanel.vue`** (lines 191–194) — an inline trash-can SVG. Replace with either a `<TrashIcon>` component import or a sprite. Small but illustrative of the pattern repeated across other inspectors.

4. **`columnMappingRowSchema` (ImportContactsPage 521-658) vs `buildNamespacedMappingRow` (trait 530-664)** — two ~135-line methods that build effectively the same `Group::make([$select, $customSubForm, $orgSubForm, $noteSubForm, $tagSubForm])` row, differing only in their sentinel set and afterStateUpdated hook. Parameterise. (See 2.4.)

5. **Filament page `getBreadcrumbs()`** — six import pages all return `[ImporterPage::getUrl() => 'Importer', 'Import …']`. A `protected function importerBreadcrumbs(string $current): array` on a shared `BelongsToImporter` trait removes one repeat.

---

## 7. Suggested sequence

If acted on, do them in this order — earlier items unblock or de-risk later ones:

1. **2.2 — Extract progress-view Blade partials.** Pure view work, no behaviour change, unblocks future test-id consistency. Low risk, fastest win. (~1 session.)
2. **6.1, 6.2, 6.5 — Trait-level inline-HTML and breadcrumb extractions.** Mechanical. (~½ session.)
3. **2.3 — Move `InteractsWithImportWizard` HTML strings to Blade partials.** Low risk, finishes the import-view de-stringification. (~1 session.)
4. **3.3 — Split `PageBuilderApiController`.** Mechanical, well-tested. (~1 session.)
5. **4.1 — `BuildsSecretKeyFields` trait.** Localised, low risk. (~½ session.)
6. **2.1 — Per-entity `RowProcessor` extraction from progress pages.** Bigger lift. Requires tests on existing flows first. (~1 session.)
7. **3.1, 3.2 — Vue component splits + scoped-CSS extraction.** Frontend refactor; needs visual QA. (~1 session each.)
8. **2.4 — Fold `ImportContactsPage` back onto `processUploadedFileNamespaced` / `serializeColumnMaps`.** Most risky; needs solid before/after dry-run comparison. (~1–2 sessions.)
9. **4.2 — Strategy split of `ContractResolver`.** Performance-sensitive; needs benchmarking. (~1 session.)
10. **3.4 — Pinia store split.** Largest blast radius; defer until other restructures land. (~1–2 sessions.)

---

## 8. What was *not* recommended

- **Splitting tests purely on line count.** Pest test files grow naturally; cohesion is more important than length.
- **Splitting `ImportSessionActions.php`, `ContentImporter.php`, `AssetBuildService.php` for size alone.** They're large but coherent; size is symptom not disease.
- **Extracting partials from one-off Blade views** (`events/show.blade.php`, `layouts/public.blade.php`, `livewire/page-builder.blade.php`) — they're under the 500-line bar and don't show meaningful duplication with other views.
- **Refactoring `editor.ts` types.** Out of scope; it's a TypeScript shape that already has clean sectioning by `// ── …` comments.
