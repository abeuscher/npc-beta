# Code Review — Large-File Advisory (W11)

Standing inter-cycle artifact for the **Code Review & Cleanup** track. It carries the per-language LOC baselines and the disposition of every >2×-average outlier, so each cycle's W11 pass starts from the prior dispositions instead of re-litigating them. **Refresh the counts at every audit session** by re-running the grep (the recipe is below); update dispositions only where a file crossed a threshold or entered active work.

Lineage: created session 270 (pre-Cycle-2); reference went dangling (file absent at the 343 close); **regenerated at session 344** (Cycle 3 audit) with fresh counts. The per-session detail lives in the matching audit logs; this file is the rolling disposition table.

## Refresh recipe

Per-language: average LOC across eligible files (exclude `vendor/`, `bootstrap/cache/`, `node_modules/`, `public/build/`, `database/migrations/`), `2×` threshold, outliers `>2×` sorted desc. File-sets: PHP = `app database/seeders database/factories tests routes config`; Blade = `resources/views` + `app/Widgets/*/template.blade.php`; Vue = `resources/js`; TS = `resources/js tests/e2e`; JS = `resources/js scripts`; SCSS = `resources/scss` + `app/Widgets/*/styles.scss`.

## Per-language baseline — session 344 (2026-06-06)

| Language | Avg LOC | Files | 2× threshold | # outliers >2× |
|---|---|---|---|---|
| PHP | 133 | 995 | 266 | 115 |
| Blade | 71 | 149 | 142 | 22 |
| Vue | 279 | 40 | 558 | 5 |
| TS | 139 | 43 | 278 | 5 |
| JS | 95 | 23 | 190 | 3 |
| SCSS | 175 | 27 | 350 | 4 |

## Disposition table (top outliers)

**Legend:** WF = standing won't-fix (consequence-of-pattern); WATCH = principled domain complexity, no action; NEW = new/grown candidate for an apply pick; CARVE = lifted to a dedicated session.

### PHP

| File | 344 LOC | 271 LOC | Disposition |
|---|---|---|---|
| `tests/Feature/ContentImportExportTest.php` | 2407 | 572 | WATCH — test file; large but covers the bundle import/export round-trips. |
| `app/Services/ImportExport/ContentImporter.php` | 1958 | 516 | NEW — bundle-import subsystem grew ~3.8× since Cycle 2; candidate for a future sub-extraction pass, not this apply. |
| `app/Filament/Pages/Concerns/InteractsWithImportWizard.php` | 1192 | 1193 | WF — consequence-of-pattern (per-step namespaced orchestration). |
| `app/Services/ImportExport/ContentExporter.php` | 1168 | (n/a) | NEW — paired with ContentImporter growth; same future-pass note. |
| `app/Filament/Pages/ImportContactsPage.php` | 1014 | 1017 | WF — Flag B (bare `__custom__` divergence is load-bearing). |
| `app/Filament/Pages/Concerns/InteractsWithImportProgress.php` | 950 | 950 | WF — consequence-of-pattern. |
| 7 importer ProgressPages (594–784) | — | — | WATCH — template-method shape; real domain complexity. Small W7 trait-dedup picks exist (see 344 log W7). |
| `app/Filament/Pages/Settings/FinanceSettingsPage.php` | 681 | 544 | WATCH — settings-form heavy. |
| `app/Http/Controllers/Admin/PageBuilderApiController.php` | 696 | 678 | WATCH — page-builder API surface. |

The importer cluster (wizard/progress concerns + 7 progress pages) dominates the PHP outliers exactly as in Cycles 1–2; reaffirmed WF/WATCH.

### Vue / TS

| File | 344 LOC | Disposition |
|---|---|---|
| `resources/js/page-builder-vue/components/InlineFormatToolbar.vue` | 1768 | **CARVE** — new dominant outlier (session 306 inline-editing). Composable-extraction (`useInlineToolbarPosition`, `useInlineLinkPopover`) is behaviour-sensitive; lifted to a dedicated Cycle-3 carve-out session. |
| `resources/js/page-builder-vue/stores/editor.ts` | 845 | NEW — grew back from the 273 extraction's 700 (inline-edit coordination + dedup-prompt clusters un-extracted). Modest re-extraction; apply-pick-sized. |
| `resources/js/theme-editor/TypographyPanel.vue` | 649 | WATCH — principled. |
| `resources/js/page-builder-vue/components/LayoutColumnSettingsTab.vue` | 637 | WATCH — extracted at 273; column config has accreted; correct home. |
| `resources/js/page-builder-vue/components/PreviewCanvas.vue` | 625 | WATCH — principled. |
| `resources/js/page-builder-vue/components/primitives/ColorPicker.vue` | 594 | WATCH — principled. |
| `resources/js/page-builder-vue/composables/useInlineEdit.ts` | 367 | WATCH — principled (inline-edit core). |

### SCSS

| File | 344 LOC | 273/274 | Disposition |
|---|---|---|---|
| `resources/scss/_custom.scss` | 941 | 757 (deferred at 273, re-skipped at 274) | WF — re-skipped twice; chrome rules at layout level, per-widget rules below have no clean partial-extraction target. Revisit only on a forcing function. |
| `app/Widgets/Nav/styles.scss` | 441 | 338 | WATCH — the most complex widget (per-instance mobile collapse). |
| `resources/scss/_base.scss` | 406 | — | WATCH — base reset + button system. |
| `app/Widgets/EventsListing/styles.scss` | 352 | — | WATCH — listing/calendar widget. |

## Standing rule

**Auto-skip-with-note:** an outlier >5× its language average that pre-existed two cycles ago and is not in active work is reaffirmed with a one-line note rather than re-argued. Lift only on a forcing function (a feature touching the file, or a flag finally maturing). The three importer PHP outliers and `_custom.scss` are the standing two-cycle skips.
