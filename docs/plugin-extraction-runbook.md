# Plugin Extraction Runbook

**Written from:** session 385 (Plugin Architecture arc P9 — Stage C-lite), the LogoGarden extraction.
**What this is:** the repeatable procedure for moving a Stage B plugin package out of the core repo into its own repository, consumed back as a composer VCS dependency. Each numbered step states the generic action; the indented *Worked example* lines show exactly what session 385 did for LogoGarden. Run the whole list top to bottom; the verification gates are not optional.

The contract this implements: `docs/plugin-contract.md` § Package shape — External repo (Stage C-lite). Companion precedent: the CRM ↔ Fleet Manager relationship (separate repos, tagged versions, a contract doc both sides program against, changes contract-first).

---

## 0. Preconditions

- The plugin is already a **Stage B package**: it lives at `plugins/{PascalName}/` with its own `composer.json` (explicit `version` field, PSR-4 `Plugins\{PascalName}\` → `""`), resolved through a root path repository with a bound require, lock-pinned, no auto-discovery. If it isn't, do the Stage B packaging first (382/383 mechanics) — this runbook starts from that shape. Two Stage-B packaging notes learned at 387 (arc D1): the `distribution.json` entry must flip `folder` → `path` **in the same commit** that adds the plugin's `composer.json` (`DistributionManifestGuardTest` asserts a folder entry's directory has no manifest); and the Dockerfile's plugin COPY lines must appear **in the manifest's path-entry order** (the guard compares them as an ordered map).
- If the plugin uses a third-party library, the require is **package-owned from Stage B on** (contract § Package shape, session 387): the plugin's `composer.json` declares it, root drops its direct line in the same commit, and the lock must resolve the identical version (now transitive). Extraction then carries the dependency out with the package automatically — nothing dependency-related changes in this runbook's steps.
- Fast suite green, route parity at its baseline, CI green on `main`.
- Decide the target repo name. **Naming pattern: `crm-plugin--{kebab-handle}`** (the handle is the kebab-case of the plugin's PascalName — the same derivation `PluginServiceProvider::handleFor()` uses).

Survey the plugin's location-keyed touchpoints before starting (each has a step below):

| Touchpoint | Where to look |
|---|---|
| Distribution-manifest entry | the plugin's `distribution.json` entry — its `source` flips `path` → `vcs` in step 5 (session 386: the manifest is the plugin-set authority; `DistributionManifestGuardTest` pins every surface below to it, so a missed step now fails the suite loudly) |
| Asset paths | the definition's `assets()` — `plugins/{PascalName}/…` strings |
| Dockerfile manifest COPYs | `COPY plugins/{PascalName}/composer.json …` — one line per composer stage |
| Guard scans | every test globbing `plugins/*` (see step 8) |
| Direct file reads in tests | grep tests for `plugins/{PascalName}/` — e.g. `base_path('plugins/…/template.blade.php')` reads, migration-path fixture reaches (`basePath('plugins/…/database/migrations')`) |
| Own testsuite | a `plugins/{PascalName}/tests/` phpunit testsuite (Events has one; LogoGarden doesn't) — see step 9 |
| Cross-plugin asset inputs | another plugin's definition declaring this plugin's (or core's) asset paths — flagged at 382 for EventsListing → BlogPager |

> *Worked example (385):* LogoGarden — eight files, no schema, no migrations, no routes, no admin pages, no in-plugin tests. Touchpoints found: two asset paths, two Dockerfile COPY lines, six guard tests globbing `plugins/*`, one direct template read (`AppearanceConfigRenameTest`), the pilot test's asset-path assertions. Nothing else.
>
> *Worked example (387):* Payments — six files, no assets, no migrations, no in-plugin tests, no Filament pages; the first **foundation** plugin extracted (consumers reach it only through the surface-13 capability layer, so nothing outside the plugin needed an edit) and the first dependency-carrying package (`stripe/stripe-php` — already package-owned from Stage B, nothing extra at extraction). Touchpoints found: two Dockerfile COPY lines, three direct test file reads (`PaymentModuleBoundaryTest` ×2, `ConventionDriftTest` ×1), two comment-only mentions. Nothing else.
>
> *Worked example (388):* Events — 89 files, the known-edges run (first migration-owning, first testsuite-carrying, first with assets since LogoGarden, and the run that emptied `plugins/`). Touchpoints found: five asset-path strings in three widget definitions (plus the EventsListing→BlogPager **core** stylesheet input, which stays a core repo-relative path); two Dockerfile COPY lines; the `Events` phpunit testsuite; nine direct test file reads (`ContentListingWidgetsTest` ×6 + `AppearanceConfigRenameTest` ×1 template reads, plus the two fixture migrator reaches — `EventsRemovedTestCase` / `EventsDisabledViaFlagTestCase`, `basePath('plugins/Events/database/migrations')`); one line-keyed SCSS baseline entry (`WidgetColorTokenConsumptionTest`); the schema-dump owner map in `PluginPackageGuardTest`; seven `thumbnails/static.png` traveling with the copy.
>
> *Worked example (391):* Donations — 62 files, the fourth run, every edge D2-proven at smaller scale (second migration-owning, second testsuite-carrying; `plugins/` emptied again with zero new guard relaxations — the 388 set held). Touchpoints found: one asset-path string (DonationForm `script.js`; RecentDonations declares none; no cross-plugin asset input); two Dockerfile COPY lines; the `Donations` phpunit testsuite (14 files); the two fixture migrator reaches (`DonationsRemovedTestCase` / `DonationsDisabledViaFlagTestCase`); **four path reads inside the plugin's own traveling tests** (`DonationsSchemaSession390Test`'s migrator-path assertion + `DonationCreditsImporterUntouchedTest`'s three file reads — a plugin's own tests can carry `plugins/…` reads that must go vendor-relative in the extracted copy, step 3's edit, not step 7's); the schema-dump owner map in `PluginPackageGuardTest`. No procedure deltas — the runbook ran as written.
>
> *Worked example (393):* Memberships — the fifth run, and the second run compressed into the same session as the plugin's packaging (the 387 Payments shape). Touchpoints found: one asset-path string (PricingChart `styles.scss`; MembershipStatus declares none; no cross-plugin asset input); two Dockerfile COPY lines (added at the same-session package step, removed here); the `Memberships` phpunit testsuite (8 files); one core template read (`InlineEditingFoundationSession304Test`'s PricingChart read); **two path reads inside the plugin's own traveling tests** (the 391 nuance again — `PricingChartDefinitionTest`'s asset assertion + `MembershipsSchemaSession393Test`'s migrator-path assertion, step 3's edit); the schema-dump owner map. Same-session compression nuance: the fixture migrator reaches and the guard owner map can land **vendor-relative at the package step** — the path-repo symlink makes `vendor/{vendor}/{package}/…` valid in both states, so extraction needs no re-point for them. No procedure deltas — the runbook ran as written.
>
> *Worked example (395):* MemberPortal — the sixth run, the second compressed into the same session as the packaging. Touchpoints found: two asset-path strings (PortalSignup + PortalChangePassword `script.js`; the other four widgets declare none; no cross-plugin asset input); two Dockerfile COPY lines (added at the same-session package step, removed here); the `MemberPortal` phpunit testsuite (6 files, 44 tests); **one path read inside the plugin's own traveling tests** (the 391/393 nuance — `MemberPortalSchemaSession395Test`'s migrator-path assertion, step 3's edit; the provider's `loadMigrationsFrom(__DIR__)` resolves the symlink to the in-repo path at the package step, so that one assertion cannot land vendor-relative early — unlike the core fixtures' direct migrator reaches, which did); two comment-only mentions (`PaidCheckoutTest`, the boundary guard docblock); two doc lines (`app-reference`, `permission-matrix`) + one `routes/web.php` comment. Fixtures and the guard owner map landed vendor-relative at the package step (the 393 nuance held). No procedure deltas — the runbook ran as written.
>
> *Worked example (396):* Blog — the seventh run, the first compressed into the same session as the carve AND the packaging, and the first plugin with **no owned schema** (posts are core `Page` rows; no migrations travel, the twin fixtures permanently register no migrator path, no mark-as-applied deployment step). Touchpoints found: two asset-path strings (BlogListing scss+js; BlogPager declares none — its former folder stylesheet was the shared listing pager bar, lifted to core-owned `resources/scss/widgets/_shared-pager.scss` at the carve per ADR 0007, with a `crm-plugin--events` v0.1.3 rider re-pointing EventsListing's declaration — the recorded step-0 cross-input case resolved); two Dockerfile COPY lines (added at the same-session package step, removed here); the `Blog` phpunit testsuite (6 files, 23 tests); one core file read (`WidgetAssetsCollectionRetiredGuardTest`'s PostController source read — landed vendor-relative at the package step, the 393/395 nuance); eight core template reads (`ContentListingWidgetsTest` ×7 + `AppearanceConfigRenameTest` ×1, re-pointed at extraction); two comment lines (`routes/web.php`, the boundary-guard docblock) + `docs/app-reference.md` (4 rows) / `docs/runbooks/permission-matrix.md` (1 link). No procedure deltas — the runbook ran as written.

## 1. Create the target repository

Owner creates the repo (empty — no README/license/gitignore autogenerated, so the first push is clean) and clones it as a **sibling** of the core repo checkout.

**Decision point — visibility:** a **public** repo keeps `composer install` credential-free in every context that builds the image (CI tests job, CI e2e image build, GHCR deploy build, a fresh machine). A private repo works but every one of those contexts then needs a GitHub token composer can read (`COMPOSER_AUTH` or `auth.json`) — audit all of them before choosing private.

> *Worked example:* `https://github.com/abeuscher/crm-plugin--logo-garden` — public, empty, checked out at `../crm-plugin--logo-garden`. Publicness verified with an unauthenticated `curl https://api.github.com/repos/… → "private": false`.

## 2. Copy the plugin's files to the new repo root

`cp -r plugins/{PascalName}/. ../crm-plugin--{handle}/` — the package's `composer.json` lands at the repo **top level** (composer's VCS driver reads it there). Git history stays in the core repo; note that in the initial commit message rather than attempting a history-preserving subtree split (the core repo remains the archaeology).

## 3. Adjust the extracted copy (two mechanical edits + a README)

1. **Delete the `version` field** from the extracted `composer.json`. The field was the *path-repo* determinism device (path repos resolve without git metadata). Under a VCS repository this **inverts**: git tags are the version metadata, and a retained field that disagrees with the tag is a composer install error. The guard in step 8 pins this.
2. **Rewrite the asset paths** in the definition's `assets()` from `plugins/{PascalName}/…` to `vendor/{vendor}/{package}/…`. They are repo-relative strings resolved off the **consuming** repo's disk by `AssetBuildService::collectSources()` via `base_path()` — core is untouched. (A `baseDir()`-derived declaration would be cleaner but changes every plugin's contract surface — backlogged, not part of this procedure.) A plugin with no assets records this step **n/a — plugin declares no assets** rather than skipping silently (the 387 Payments precedent; gate 4 gets the same treatment).
3. **Write a README** mirroring the FM discipline: what the plugin is, which plugin-contract version it implements, the tag/release discipline (tags are releases; core bumps its lock deliberately), where its tests live (step 9's decision).

> *Worked example:* `version: "0.1.0"` dropped; `plugins/LogoGarden/styles.scss` → `vendor/nonprofitcrm/logo-garden/styles.scss` (and the `.js` twin); README states contract 0.9.0.
>
> *Worked example (388):* Events — five rewrites across three definitions (EventMiniCalendar scss+js, EventRegistration js, EventsListing scss+js); EventsListing's second scss entry, core `app/Widgets/BlogPager/styles.scss`, **left as-is** — a cross-plugin asset input is declared relative to the consuming repo and doesn't move with the declaring plugin. README notes the tests-travel-with-the-package posture (step 9).

## 4. Commit, tag, push the new repo

```
git add -A && git commit
git tag v0.1.0          # first tag = the version the core require already binds (^0.1)
git push origin main --tags
```

The tag must be on GitHub **before** step 5 — composer resolves the require against published tags.

## 5. Swap the core repo onto the VCS repository

First, in `distribution.json` (session 386 — the plugin-set authority): flip the plugin's `source` from `{"type": "path", "path": "plugins/{PascalName}"}` to `{"type": "vcs", "url": "https://github.com/{owner}/crm-plugin--{handle}.git"}`; `provider`, `package`, and `constraint` stay unchanged. The config list doesn't change on extraction (same providers, same order), so `plugins:manifest-sync` reports no-op — the edit is what moves the plugin into the guard's vcs-sourced set (lock pinning, no Dockerfile COPY lines, `PluginPackageGuardTest`'s tagged-VCS assertions all key off it).

Then, in root `composer.json`:

- Replace the plugin's `{"type": "path", "url": "plugins/{PascalName}"}` repository entry with `{"type": "vcs", "url": "https://github.com/{owner}/crm-plugin--{handle}.git"}`.
- The require line **stays bound and unchanged** (`"{vendor}/{package}": "^0.1"`).

Then, in order:

```
git rm -r plugins/{PascalName}
composer update {vendor}/{package}       # re-resolves from the tag, rewrites the lock
composer validate --strict               # must stay clean
```

**Ordering caveat (387):** once the source directory is deleted, the stale vendor symlink dangles and **artisan cannot boot** until `composer update` replaces it — run composer before any artisan command (including a `plugins:manifest-sync` check; the sync is a provider-list no-op on this walk anyway).

Confirm the lock entry: `source.type = git` at the exact tag commit, `dist.type = zip` (GitHub zipball), `version = v0.1.0`. The lockfile change ships **in the same commit** as the manifest change.

> *Worked example:* `vendor/nonprofitcrm/logo-garden` became a real extracted directory (path-repo packages are symlinks); the events symlink beside it is the visible difference between the two stages.

## 6. Dockerfile cleanup

Delete the plugin's manifest COPY line from **both** composer stages (`COPY plugins/{PascalName}/composer.json plugins/{PascalName}/`). A VCS-resolved package needs no source COPY at all — `composer install` fetches it from GitHub during the build (which is why visibility/auth was step 1's decision). Since session 386 a forgotten cleanup fails the suite: `DistributionManifestGuardTest` asserts the Dockerfile's plugin COPY lines are exactly one per *path*-sourced manifest entry per stage.

## 7. Re-point direct file reads

Any test or script that read the plugin's files by their `plugins/…` path now reads `vendor/{vendor}/{package}/…`. Grep is the tool; the step-0 survey found them.

> *Worked example:* `PluginPilotSession377Test`'s synced-asset-path assertions → vendor paths; `AppearanceConfigRenameTest`'s `file_get_contents(base_path('plugins/LogoGarden/template.blade.php'))` → the vendor path.
>
> *Worked example (388):* the seven template reads (`ContentListingWidgetsTest` ×6, `AppearanceConfigRenameTest` ×1) → the vendor path; **the two fixture migrator reaches** (`EventsRemovedTestCase`, `EventsDisabledViaFlagTestCase`) → `vendor/nonprofitcrm/events/database/migrations` — the disabled-≠-uninstalled semantics run against the vendor'd migrations with assertions unchanged; the `WidgetColorTokenConsumptionTest` baseline key and `PluginPackageGuardTest`'s schema-dump owner map re-keyed to the vendor path.

## 8. Extend the standing guards to the vendor path

The zero-allowlist guarantees must not silently shrink when a plugin leaves the repo. The shared helper **`extractedPluginPackageDirs()`** (`tests/Support/helpers.php`) returns the non-symlink directories under `vendor/nonprofitcrm/*` — extracted packages only; path-repo symlinks are skipped so no file is scanned twice (baseline-diff guards would report a symlink duplicate as a new violation). Every guard that scans `plugins/*` loops the helper alongside its existing globs. As of session 385 that set is:

- `WidgetTemplateBoundaryTest` (template purity)
- `PluginAdminCssGuardTest` (no plugin admin CSS)
- `PublicAlpineCspGuardTest` (CSP-safe Alpine — both the blade-file walk and the script/template globs)
- `InlineEditingFoundationSession304Test` (Tier-B annotation ban)
- `WidgetColorTokenConsumptionTest` + `WidgetStyleBoundaryConsumptionTest` (SCSS baselines)

A **new** guard added since 385 that scans `plugins/*` must adopt the helper too — that's the standing rule, not a per-extraction decision.

**If the extraction empties `plugins/` (388):** git tracks no empty directories, so the folder itself vanishes from a fresh checkout — guards that *assume* it exists fail, beyond the ones a survey of `plugins/{PascalName}` greps finds. The 388 relaxations, all scoped to "tolerate an empty in-repo set, assert the shape for whatever exists": `PluginAdminCssGuardTest` skips a missing `plugins/` root (`is_dir`), `WidgetTemplateBoundaryTest`'s sanity check treats in-repo + extracted as one plugin set, and `PluginPackageGuardTest`'s in-repo assertions (the auto-discovery glob's non-empty check moves to the combined set; the path-pinning loop asserts explicitly on the possibly-empty glob so phpunit doesn't flag it risky). The zero-allowlist scans themselves are unchanged — the extracted-package roots still cover every plugin file.

`PluginPackageGuardTest` is the stage split, and since session 386 it needs **no edit**: its extracted set derives from `distribution.json`'s vcs-sourced entries, so the step-5 manifest edit already moved the package from the path-pinning assertion to the VCS one (lock-pinned to a tag-shaped version at an exact git commit, dist zip, no `version` field in the vendor manifest, still no auto-discovery). The former hand-maintained `EXTRACTED_PLUGIN_PACKAGES` const is gone — the manifest is declared once.

## 9. Test membership — two postures (settled at 388)

Normative rule 5's target posture is that plugin-subject tests ship with the plugin. The postures, per what the plugin carries:

- **The plugin has its own `tests/` testsuite** (the 383 dismantling shape): the suite **travels with the package**, and the core repo's `phpunit.xml` testsuite re-points `plugins/{PascalName}/tests` → `vendor/{vendor}/{package}/tests` — same files, same counts, running in core CI (and under paratest) unchanged. This is the plan § 6.3 "central build runs the distribution's plugin suites" disposition made real. `DistributionManifestGuardTest`'s testsuite assertion only polices `plugins/` suites, so no guard edit is needed.
- **The plugin has no in-plugin suite** (core-side subject tests only): those tests stay in the core repo (owner ruling, 384 close — LogoGarden, Payments).

Either way, per-plugin CI in the extracted repo (the tests boot the full Laravel app, so core becomes a dev dependency — non-trivial plumbing) remains the standing follow-up (arc D11).

> *Worked example (385/387):* `PluginPilotSession377Test`, `LogoGardenContractRetrofitTest`, `LogoGardenWidgetTest`, and Payments' subject tests stay core.
>
> *Worked example (388):* the `Events` testsuite (29 files) re-pointed to `vendor/nonprofitcrm/events/tests` — 236 tests, identical counts from the vendor path.
>
> *Worked example (391):* the `Donations` testsuite (14 files) re-pointed to `vendor/nonprofitcrm/donations/tests` — 103 fast + 1 slow, identical counts from the vendor path.
>
> *Worked example (393):* the `Memberships` testsuite (8 files) re-pointed to `vendor/nonprofitcrm/memberships/tests` — 71 tests, identical counts from the vendor path.
>
> *Worked example (395):* the `MemberPortal` testsuite (6 files) re-pointed to `vendor/nonprofitcrm/member-portal/tests` — 44 tests, identical counts from the vendor path.
>
> *Worked example (396):* the `Blog` testsuite (6 files) re-pointed to `vendor/nonprofitcrm/blog/tests` — 23 tests, identical counts from the vendor path.

## 10. Verification gates (all binary; run every one)

1. **Fast suite** green at the prior baseline plus any guard additions, assertion content unchanged — including the plugin's `PLUGINS_DISABLED={handle}` vanish coverage (activation is namespace-keyed and must be provably location-independent).
2. **Route parity** at the baseline (`php artisan route:list`).
3. **`composer validate --strict`** clean; lock pins the tag.
4. **Bundle content** — the surface-11 gate, the one failure a green suite won't catch: `collectSources()` **silently skips** asset paths missing on disk. Run `widgets:sync` then `build:public`, then prove the built bundle carries the plugin's content (grep the CSS for the plugin's class names, the JS for its registered components, check its libs in the manifest). The pilot's paths-exist-on-disk sync test is the standing net once the vendor paths are asserted. For a plugin that declares no assets, record the gate **n/a — plugin declares no assets** (387: Payments). **Cross-plugin asset inputs get their own grep** — a core (or other-plugin) stylesheet the plugin declares as a build input keeps its original path and must still land in the bundle (388: EventsListing's BlogPager pager styles, proven alongside the plugin's own `events-listing` / `mini-calendar` rules, the three registered Alpine components, and swiper in the manifest).
5. **In-image proof** (the 382 procedure): `docker build --target app`, then
   `docker run --rm --entrypoint sh <image> -c 'ls vendor/{vendor}/ && php -r "require \"vendor/autoload.php\"; var_dump(class_exists(\"Plugins\\\\{PascalName}\\\\…\"));"'`
   — the package must arrive from GitHub inside the build (no COPY lines left), autoload through the optimized `--no-dev` autoloader, and `plugins/{PascalName}` must be absent from the image.
6. **CI green on the pushed branch** — the e2e job builds the image, re-proving the VCS resolve cold on a fresh runner.

## 11. Docs

- `docs/plugin-contract.md`: the extraction is a Package-shape event — confirm the External repo section still describes what you did, bump/changelog if the procedure deviated.
- The plugin repo's README carries the implemented contract version — update it when the contract bumps.
- Plan doc / roadmap entries per the session's close discipline.

---

## What stays the same (do NOT touch these)

- **`config/plugins.php`** — the provider FQCN line is unchanged (extraction moves code, not composition; since session 386 the file is generated from `distribution.json`, and an extraction changes only the entry's `source`, which doesn't touch the generated list). The config list stays the installed superset + sole runtime ordering authority.
- **Activation** — handles derive from the provider FQCN's namespace; `PLUGINS_DISABLED` and the remove-the-line guarantee work identically wherever the class autoloads from. An extraction that adds any location-keyed activation logic is wrong.
- **The root `"Plugins\": "plugins/"` PSR-4 mapping** — stays while any in-repo plugin remains; it simply no longer covers the extracted package (the package's own PSR-4 is now the only mapping for it — the contract's "retires per-plugin" line).
- **Core code** — `AssetBuildService`, `WidgetRegistry`, `PluginServiceProvider` are untouched; asset paths are opaque repo-relative strings to them.

## Follow-ups this procedure deliberately leaves open

- **Per-plugin CI in the extracted repo** (with core as a dev dependency) — the standing follow-up to step 9's accepted debt.
- **Core dependency modeling** (the package requiring core at composer level) — still absent by design; the package programs against the contract doc.
- **Migration-owning plugins** *(exercised at 388 — no longer open)*: a plugin carrying `database/migrations/` needs nothing extra at extraction beyond re-pointing any core-side fixture migrator reaches (`basePath('plugins/…/database/migrations')` → the vendor path) — the disabled-≠-uninstalled fixtures pass against the vendor'd migrations with assertions unchanged, and `loadMigrationsFrom(__DIR__ …)` in the provider is location-independent by construction. The steps above (7, 9, gate 4's cross-input grep) carry the Events worked-example lines.
- **Thumbnail regeneration**: `scripts/generate-thumbnails.js` writes to `app/Widgets/{Name}/` or `plugins/{Name}/` — it cannot regenerate an extracted plugin's thumbnails. Ship regenerated thumbnails from the plugin's own repo when its visuals change (serving keeps working — `thumbnailDir()` resolves by reflection from the class file's location).
