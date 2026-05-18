# Deploy Fix — Playwright e2e: `Failed opening required '/var/www/html/vendor/autoload.php'`

**Status:** root-caused, fix is a small `docker-compose.e2e.yml` change. No test/Dockerfile/workflow change required.

## Symptom

Playwright `globalSetup` fails in CI:

```
Warning: require(/var/www/html/vendor/autoload.php): Failed to open stream: No such file or directory in /var/www/html/artisan on line 10
Fatal error: Uncaught Error: Failed opening required '/var/www/html/vendor/autoload.php' ... in /var/www/html/artisan:10
Error: Command failed: docker compose -p nonprofitcrm_e2e -f docker-compose.e2e.yml exec -T app php artisan migrate:fresh --seed
  at resetDatabase (tests/e2e/helpers/db.ts:319) → globalSetup
```

## Root cause

`docker-compose.e2e.yml`'s `app` (and `worker`, `nginx`) services bind-mount the host repo over the container app root:

```yaml
volumes:
  - .:/var/www/html
  - ./docker/php/local.ini:/usr/local/etc/php/conf.d/local.ini
  - app_node_modules_e2e:/var/www/html/node_modules   # node_modules is protected…
  # …but there is NO equivalent for vendor/
```

The `app` image (Dockerfile `target: app`) **does** contain a complete, autoloaded `/var/www/html/vendor` — `COPY . .` then `composer dump-autoload --optimize` (Dockerfile ~line 130). But the `.:/var/www/html` bind mount **shadows** the image's `/var/www/html` with the host checkout. They already worked around this for `node_modules` with a named volume (`app_node_modules_e2e`) — **`vendor/` was overlooked**.

- Dev machine: works, because the developer has run `composer install` on the host, so the host `vendor/` exists and is mounted in.
- GitHub Actions: the **`playwright` job never runs `composer install`** (unlike the Pest `tests`/`Slow suite` jobs, which `composer install` on the runner). The runner checkout has no `vendor/` (it's gitignored), so the bind mount overlays an empty/absent vendor → `artisan` can't load `vendor/autoload.php` → `migrate:fresh` fatals in `globalSetup`.

Parallelization / earlier fixes are unrelated; this is purely the e2e bind-mount shadowing image vendor in a no-host-composer environment.

## Fix (do this — symmetric with the existing node_modules workaround)

In `docker-compose.e2e.yml`, add a named volume for `vendor/` to **both** the `app` and `worker` services (mirror exactly what `app_node_modules_e2e` does):

```yaml
    volumes:
      - .:/var/www/html
      - ./docker/php/local.ini:/usr/local/etc/php/conf.d/local.ini
      - app_node_modules_e2e:/var/www/html/node_modules
      - app_vendor_e2e:/var/www/html/vendor          # ← add
```

and declare it in the top-level `volumes:` block:

```yaml
volumes:
  postgres_data_e2e:
  redis_data_e2e:
  app_node_modules_e2e:
  app_vendor_e2e:                                     # ← add
```

Docker seeds a fresh named volume from the **image's** `/var/www/html/vendor` on first creation, so the container gets the image-baked, fully-autoloaded vendor regardless of the host checkout. Works identically on dev and CI; no workflow change, no extra `composer install` step, no second build/checkout (preserves the e2e stack's stated design intent).

## Dev-machine caveat (same tradeoff already accepted for node_modules)

A named volume persists across runs and will not reflect host `composer` changes until refreshed. After changing PHP deps locally, run `npm run e2e:down` (it uses `down -v`, which drops the e2e volumes) then `npm run e2e:up` so `app_vendor_e2e` re-seeds from the rebuilt image. This is the identical lifecycle `app_node_modules_e2e` already has — no new mental model.

## Alternative (not recommended)

Add a `composer install` step to the `playwright` job before "Bring up the isolated e2e stack" (mirroring the Pest jobs). It works, but reintroduces a host composer install the e2e stack explicitly avoids ("no second checkout, no second image build"), is slower every run, and leaves the dev/CI behavior asymmetric. Prefer the named-volume fix.

## Verify

After the change: in CI the `playwright` job's `globalSetup` (`migrate:fresh --seed`) should run cleanly and the chromium project should execute. Locally, `npm run e2e:down && npm run e2e:up` then `npm run test:e2e:isolated` should pass with no autoload error.
