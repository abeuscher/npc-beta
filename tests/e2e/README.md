# End-to-End (Playwright) Specs

Dev-only browser-driven regression tests. **Never runs in CI or on the deploy server.** Run from the WSL2 host against the local Docker stack.

## Prerequisites

- Docker stack up (`docker compose up -d`).
- `.env` contains `APP_URL`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`, and the `DB_*` credentials.
- `npm install` has been run.
- Chromium installed: `npx playwright install chromium` (one-time).

## Running

| Command | When |
|---|---|
| `npm run test:e2e` | Default run. Headless. Excludes `@stress`- and `@on-demand`-tagged specs. |
| `npm run test:e2e:ui` | Interactive Playwright UI — best for authoring and debugging. |
| `npm run test:e2e:headed` | Watch the browser drive. |
| `npm run test:e2e:stress` | Run only `@stress`-tagged specs (long-running, e.g. 10k-row imports). |
| `npm run test:e2e:on-demand` | Run only `@on-demand`-tagged specs (deeper coverage authored alongside a release-plan stub; not part of the regular regression cycle). |
| `npm run test:e2e:show-report` | Open the last HTML report. |

## Database state

The global setup (`global-setup.ts`) runs `migrate:fresh --seed` once per `playwright test` invocation, then logs in the admin user and saves `storageState` to `tests/e2e/.auth/admin.json` (gitignored).

Specs that need a clean mid-run state call `resetDatabase()` from `helpers/db.ts` in a `beforeAll`. Reset is expensive (several seconds) — reset per spec file, not per test.

## `data-testid` naming

`{area}-{thing}` or `{area}-{thing}-{identifier}`. Lowercase, hyphens. Stable across Livewire/Filament rerenders. Never rely on Filament-generated IDs. Examples:

- `import-contacts-wizard`, `import-commit-button`
- `map-column-0`, `map-column-1`
- `importer-action-approve-{session_id}`, `importer-modal-approve-submit`

## Adding a new spec

1. Create `tests/e2e/{area}/{descriptive-name}.spec.ts`.
2. Use fixtures under `tests/e2e/fixtures/{area}/`. Keep them small and PII-free.
3. Add any new UI affordances to the relevant Blade/Filament file as `data-testid` attributes before writing selectors against them.

## Importer coverage

All five CSV importers (contacts, events, donations, memberships, invoice details) have happy-path and update-strategy regression coverage — 10 specs total under `tests/e2e/importer/`. Each importer has its own fixture folder `tests/e2e/fixtures/{importer}/` containing `happy-path.csv`, `update-second-pass.csv`, and `happy-path.expected.json`. Fixtures are handcrafted, small (3–6 rows), PII-free, and use canonical header names so the wizard's field mapper auto-resolves most columns. The eight non-contact specs complement `tests/Feature/ImportSession194Test.php` — that Pest suite covers update-strategy logic at the service layer; these specs cover it end-to-end through the UI.

## On-demand specs

Specs tagged `@on-demand` are excluded from the default run and run only via `npm run test:e2e:on-demand`. They exist for deep-coverage sweeps that don't earn their cost in the regular regression cycle but want to live in the suite for episodic re-runs. The Organizations importer (`organizations-happy-path.spec.ts` + `organizations-update-strategy.spec.ts`) is the first example. Future on-demand suites are slotted as named pre-T1 stubs in `sessions/release-plan.md`.

## Debugging a failure

Outputs land under `test-results/` (traces, videos, screenshots) and `playwright-report/` (HTML). All gitignored.

- `npx playwright show-trace test-results/{...}/trace.zip` — step-by-step trace viewer.
- `npm run test:e2e:show-report` — last HTML report.
- `npm run test:e2e:headed -- tests/e2e/importer/foo.spec.ts` — watch a single spec live.
