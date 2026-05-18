# Deploy Fix — e2e: readiness probe hits a DB-dependent route → `relation "site_settings" does not exist`

**Status:** root-caused, fix is a one-line workflow change (probe URL). No app/DB/test change.

## Symptom

The `playwright` CI job fails; Postgres logs repeat, ~every 10s, then the step exits 1:

```
nonprofitcrm_e2e_postgres | ERROR: relation "site_settings" does not exist at character 15
  STATEMENT: select * from "site_settings" where "key" = $1 limit 1
nonprofitcrm_e2e_postgres | ERROR: relation "site_settings" does not exist at character 15
  STATEMENT: select * from "site_settings"
...
Error: Process completed with exit code 1.
```

## Root cause — workflow ordering/probe bug (not an app or DB bug)

The `playwright` job's **"Wait for the e2e site"** step polls `http://localhost:8090/admin/login` every 10s (`seq 1 60` → up to 600s) and only proceeds on HTTP 200. Its inline comment claims that route is *"intentionally DB-independent, so it green-lights while the e2e DB is still empty"*, and on that basis the **"Migrate + seed the e2e database"** step is sequenced *after* the wait.

That premise is false (verified on `main`):

- `app/Providers/Filament/AdminPanelProvider.php` reads `SiteSetting::get('admin_brand_name'…)`, `admin_logo_path`, `admin_primary_color`, `admin_secondary_color` **on panel boot** → `select * from "site_settings" where "key" = $1 limit 1`.
- `AppServiceProvider` / `AuthServiceProvider` also read `SiteSetting` on boot → `select * from "site_settings"`.
- The Filament panel boots on **every** `/admin/*` request, including `/admin/login`.

So `/admin/login` is DB-dependent. On a fresh e2e stack the DB has no schema (the migrate step is deliberately placed *after* this gate), so every poll returns **HTTP 500** with `relation "site_settings" does not exist`. The loop never observes 200, runs to its 600s timeout, and `exit 1`s — and the "Migrate + seed" step never runs because the gate fails before it. The pasted log is a window of that poll loop: one `/admin/login` request per ~10s, each emitting the two `site_settings` errors (`where "key"=$1` from `SiteSetting::get`, the bare `select *` from the boot-time bulk load), ending at the step's exit 1.

This is the next link after the vendor fix: autoload now works, so the app boots far enough to reach the DB and surface this.

## Fix (do this — one line)

Point the readiness probe at the framework health route, which is genuinely DB-independent. `bootstrap/app.php` declares `->withRouting(health: '/up', …)`, so `/up` returns 200 as soon as PHP-FPM/nginx can serve, without booting the Filament panel or touching `site_settings`.

In `.github/workflows/tests.yml`, the **"Wait for the e2e site"** step, change the probe URL:

```
-  code=$(curl -s -o /dev/null -w '%{http_code}' http://localhost:8090/admin/login || true)
+  code=$(curl -s -o /dev/null -w '%{http_code}' http://localhost:8090/up || true)
```

Keep the existing step order (wait → migrate+seed → playwright); it becomes correct once the probe no longer depends on DB state. Also correct the now-wrong inline comment — `/admin/login` is **not** DB-independent (the Filament panel provider reads `SiteSetting` on boot); `/up` is.

## Dependency / sequencing note

Necessary but not sufficient alone: the "Migrate + seed the e2e database" step runs `docker compose … exec -T app php artisan migrate:fresh`, which still requires the **vendor bind-mount fix** (`app_vendor_e2e` named volume) from the previous report. If vendor is still shadowed, that step fatals with the `vendor/autoload.php` error instead. Apply in order: vendor-mount fix → this `/up` probe fix. They are independent root causes on the same path; both are needed before the e2e job can go green.

## Verify

After the change: the "Wait for the e2e site" step should green-light quickly (HTTP 200 from `/up` once nginx/FPM are serving, no DB needed), the "Migrate + seed" step then creates the schema, and the chromium project runs. Locally unaffected — `/up` is always available.
