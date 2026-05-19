# Cloud Agent Task — Stabilize the Playwright e2e suite

> Out-of-sequence task (not a numbered session). Archive when the agent completes the work.

## Mission
The repo's end-to-end (Playwright) suite is red and has been flagged flaky/not-green since session 299. Get the **full** e2e suite passing reliably on the isolated Docker stack, remove the temporary quarantine, and hand back a reviewed branch. This is self-contained CI/test/infra work — **do not modify product/feature code or the dev track.**

## Branch base
All the e2e infrastructure fixes **and** the temporary quarantine are already on **`main`** (merged before you start). Branch your work from `main` as `e2e-stabilization/1`. Quick sanity check on your base: `docker-compose.e2e.yml` contains an `app_public_build_e2e` volume **and** `playwright.config.ts` contains `QUARANTINED_SPECS`. If either is missing you are on a stale base — stop and surface to the user. Per `CLAUDE.md`: never commit on/push/merge `main`, no force-push, push your branch for review only.

## Orient (read first)
- `CLAUDE.md` — project instructions, git workflow, **test commands**, and the rule **Pest and Playwright must never run concurrently**.
- `sessions/template-base-prompt.md` — the project's standard base prompt (conventions/process), for orientation even though this is an infra task, not a feature session.
- `docs/app-reference.md` — architecture, esp. the single server-render path and the runtime-vs-build boundary.
- `tests/e2e/README.md` — the Playwright harness, fixtures, isolated stack.
- `playwright.config.ts` — read the `QUARANTINED_SPECS` block + comment; it states the diagnosis.
- `git log` on `main`, commits prefixed `CI:` — they document, in order, the infra cascade already fixed.

## Already fixed — do NOT redo
CI-confirmed: Docker named-volume populate race; readiness probe uses `/up` (not `/admin/login`); Postgres healthcheck + `compose up --wait`; Vite-manifest bind-mount shadow fixed via the `app_public_build_e2e` volume; `migrate:fresh --seed` retry; an explicit admin-login readiness gate with diagnostics. These are correct — build on them. The `playwright` job ships **parked** (`if: false`) on `main`; un-parking it is part of your work (see Definition of done).

## The actual problem — ONE shared root cause (key insight)
The ~14 remaining failures are **not 14 bugs**. They span six unrelated areas (importers, dashboard, memos, page-builder, theme) yet all fail at a uniform **~17–22s** — the signature of one shared environmental cause. Concrete evidence: `tests/e2e/event-registration/ticket-tier-picker.spec.ts` times out waiting for a public page's expected content, and that spec's own line-35 comment names it: *"First public-page render can be slow cold (runtime SCSS compile)."* The app compiles SCSS at request time; cold in the isolated container the page doesn't render expected content within the timeout (or errors so content never appears). **Prime lead:** the same bind-mount shadowing pattern that caused the vendor/node_modules/public-build issues may also hit the runtime-SCSS compiled-output path — *or* the cold compile needs warming/precompiling before the suite runs. Investigate where the isolated `app` container writes compiled SCSS and whether `.:/var/www/html` shadows it.

## Method (mandatory — hard-won)
1. **Find and fix the one shared cause first.** Do not patch specs one-by-one. **Do not add specs to the quarantine to chase green** — that is unbounded and forbidden.
2. **Evidence before action.** Reproduce locally on the isolated stack; read the actual Playwright error/trace, not run summaries; validate your reproduction methodology before concluding or pushing a fix.
3. Success **requires deleting `QUARANTINED_SPECS` entirely** and the full suite passing — the quarantine is only stop-the-bleeding.
4. Only after the shared cause is fixed and the suite re-run, for any test that *still* fails as a genuine **test-level** problem, apply this decision tree:
   - **(a) Fix the test** if it is malformed against the app in its present form.
   - **(b) Delete the test** if it covers behaviour no longer relevant.
   - **(c) Surface to the user** in project-manager-level language (plain, non-jargon: the decision + its trade-off) and let them choose — when it's ambiguous whether the test or the app is "right."
5. Never run Pest and Playwright concurrently. Run only the Playwright suite on your branch.

## How to run (fast loop)
- Local isolated stack: `npm run test:e2e:isolated` (see `tests/e2e/README.md`; bring it up via the README / `./dev e2e:up`). This is the fastest loop — prefer it over CI round-trips.
- For a fast CI signal on your branch, in `.github/workflows/tests.yml`: un-park the `playwright` job (remove its `if: false`), and temporarily set `if: false` on the `fast`/`slow`/`tests` jobs and drop the `playwright` job's `needs: slow`, so a push runs only Playwright. **Restore all of that before review** — `fast`/`slow`/`tests` are the real merge-gate.

## Definition of done
- The single shared cause identified and fixed at the environment/infra level.
- `QUARANTINED_SPECS` removed; full e2e suite passes reliably on the isolated stack (and in CI on your branch).
- Temporary workflow scoping restored (Pest gate intact); the `playwright` job **un-parked** — runs after `slow`, non-gating, as designed.
- No product/feature code changed. Branch pushed; PM-level summary for the user; not merged.
