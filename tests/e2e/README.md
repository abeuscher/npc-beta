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
| `npm run test:e2e` | Default run. Headless. Excludes `@stress`-tagged specs. |
| `npm run test:e2e:ui` | Interactive Playwright UI — best for authoring and debugging. |
| `npm run test:e2e:headed` | Watch the browser drive. |
| `npm run test:e2e:stress` | Run only `@stress`-tagged specs (long-running, e.g. 10k-row imports). |
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

## Debugging a failure

Outputs land under `test-results/` (traces, videos, screenshots) and `playwright-report/` (HTML). All gitignored.

- `npx playwright show-trace test-results/{...}/trace.zip` — step-by-step trace viewer.
- `npm run test:e2e:show-report` — last HTML report.
- `npm run test:e2e:headed -- tests/e2e/importer/foo.spec.ts` — watch a single spec live.
