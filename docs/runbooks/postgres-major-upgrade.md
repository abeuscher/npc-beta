---
title: PostgreSQL Major-Version Upgrade
description: Operator workflow for migrating an existing CRM install across a PostgreSQL major-version bump (e.g. 16 → 17).
updated: 2026-05-08
tags: [ops, postgres, infrastructure, upgrade]
category: runbook
---

# PostgreSQL Major-Version Upgrade

This runbook covers the operator workflow for migrating an existing CRM install across a PostgreSQL major-version bump — for example, the 16 → 17 bump shipped at session 269/2 to align the app container's `pg_dump` major with the database server's major (the prior skew produced backups containing `transaction_timeout` directives the older server could not ingest, blocking restore-from-blob).

The procedure is **dump-and-restore**: take a logical dump from the old major, stop the cluster, replace the data directory, start the new major, load the dump. PG16 dumps do not contain PG17-only directives, so they forward-load into PG17 cleanly.

`pg_upgrade` is the alternative path (in-place binary catalogue rewrite) but has more moving parts — an additional `postgres:N-old` container alongside the new one, careful version-coupled `pg_upgrade` invocation, manual analyze step. Not recommended for the project's pre-Beta droplet count; revisit only if the dump-and-restore window becomes operationally costly.

## When this runbook fires

Whenever the `postgres` image major in `docker-compose.yml` / `docker-compose.prod.yml` is bumped, every install with persistent `postgres_data` volumes must run this procedure before the new image can start. The new-major postgres binary refuses to read a data directory written by an older major, producing a `FATAL: database files are incompatible with server` log line and an immediate exit.

A fresh install (no existing `postgres_data` volume) does not need this runbook — `migrate:fresh --seed` produces the new shape natively.

## Pre-flight

1. **Confirm the bump is real.** Look at the deploy-target's docker-compose file. The pinned postgres image major must differ from the `postgres_data` volume's current shape. (If the volume is missing, you have a fresh install — skip this runbook.)

2. **Schedule a maintenance window.** The procedure stops the database for the duration of the dump + restore. Sizing reference: a 4.5 GB compressed backup blob (the fixture the v2.3.0 backup-blob endpoint was tested against) restored in roughly 8 minutes on a stock DigitalOcean 4 GB / 2 vCPU droplet. Real restore time scales with row counts, index counts, and disk throughput.

3. **Confirm a recent backup exists.** The backup pipeline (`php artisan backup:run`) is the authoritative pre-upgrade snapshot. The procedure below produces its own dump as a working copy; the spatie backup blob is the rollback insurance if the working dump is corrupted or the restore aborts mid-load.

   ```bash
   docker compose exec app php artisan backup:run
   docker compose exec app php artisan backup:list   # confirm the just-run blob is present
   ```

## Procedure

All commands run on the deploy-target host (the droplet running the CRM stack). Substitute the actual stack-relative paths if your install uses a non-default project directory.

1. **Take a logical dump from the old major.** Use `pg_dumpall` to capture roles + databases + grants in one file (the spatie backup pipeline only dumps the application database; the upgrade needs everything).

   ```bash
   docker compose exec -T postgres pg_dumpall -U postgres > /tmp/pg-pre-upgrade.sql
   ```

   The dump file lands on the host filesystem, not inside the container — important, because the container is about to be destroyed. Confirm it's non-empty and parseable:

   ```bash
   wc -l /tmp/pg-pre-upgrade.sql
   head -20 /tmp/pg-pre-upgrade.sql   # should show `-- PostgreSQL database cluster dump` plus role definitions
   ```

2. **Stop and remove the old stack, deleting the volume.** This is the destructive step — the `postgres_data` volume contains the old-major data directory, which is unusable by the new major. `docker compose down -v` removes named volumes; existing host-bind mounts are unaffected.

   ```bash
   docker compose down -v
   ```

   Verify the volume is gone:

   ```bash
   docker volume ls | grep postgres_data   # should return nothing
   ```

3. **Pull the new postgres image and start the stack.** The compose file's pinned image major is now in effect.

   ```bash
   docker compose pull postgres
   docker compose up -d postgres
   ```

   Wait for the healthcheck to clear (`pg_isready` succeeds against the new server):

   ```bash
   docker compose exec postgres pg_isready -U postgres
   # /var/run/postgresql:5432 - accepting connections
   ```

4. **Load the dump into the new major.** Pipe the host-side SQL file through `psql` running inside the new postgres container. The dump's role-creation statements need a superuser connection; `postgres` is the default superuser role created by the postgres image's first-boot init.

   ```bash
   cat /tmp/pg-pre-upgrade.sql | docker compose exec -T postgres psql -U postgres
   ```

   `pg_dumpall` output includes `\connect` directives that switch databases mid-load; `psql` handles these automatically. Watch for errors — a clean run should emit only `CREATE`, `ALTER`, `GRANT`, and `INSERT` notices. Any `ERROR:` line aborts that statement; if it aborts a critical role / extension creation, the rest of the load may cascade — re-run from step 2 after debugging.

5. **Bring up the rest of the stack.** With the database loaded, the application services can start.

   ```bash
   docker compose up -d
   ```

6. **Verify the load.** Confirm row counts match pre-upgrade expectations on a few representative tables.

   ```bash
   docker compose exec app php artisan tinker --execute="
       echo 'users: ' . \App\Models\User::count() . PHP_EOL;
       echo 'contacts: ' . \App\Models\Contact::count() . PHP_EOL;
       echo 'pages: ' . \App\Models\Page\Page::count() . PHP_EOL;
       echo 'widget_types: ' . \App\Models\WidgetType::count() . PHP_EOL;
   "
   ```

   Compare against the pre-upgrade row counts (capture before step 2 if you want exact comparability).

7. **Run the version-skew test as a structural check.** This is the test added at session 269/2 that mechanically catches client/server major-version skew.

   ```bash
   docker compose exec app php artisan test --filter=PostgresVersionSkewTest
   ```

   Expected: 1 passed. A failure here indicates the Dockerfile's `postgresql-client-N` pin and the compose file's `postgres:N-alpine` image pin disagree — re-check the pins before continuing.

8. **Take a fresh post-upgrade backup.** The pre-upgrade spatie blob is now stale (it carries an old-major-shaped dump inside it, which would forward-load into the new major fine but is no longer the authoritative snapshot). Run the pipeline to produce a new-major-native blob.

   ```bash
   docker compose exec app php artisan backup:run
   ```

   This blob is the new authoritative pre-incident snapshot for any future restore-from-blob operation (FM session 022 and successors).

9. **Clean up the working dump.** Once the upgrade is verified end-to-end, the host-side `/tmp/pg-pre-upgrade.sql` can be deleted. Do not skip this if the dump file contains production data — `/tmp` survives across reboots on most Linux distributions and the file is unencrypted.

   ```bash
   shred -u /tmp/pg-pre-upgrade.sql   # or rm if shred is unavailable
   ```

## Rollback

If the load aborts and the install is unrecoverable, the spatie backup blob from pre-flight step 3 is the rollback path. The fastest rollback drops the install back to the previous major:

1. Stop the stack: `docker compose down -v`.
2. Edit `docker-compose.yml` (and `docker-compose.prod.yml` if relevant) to revert the `postgres:N-alpine` pin to the previous major.
3. Pull the previous image: `docker compose pull postgres`.
4. Start the stack: `docker compose up -d postgres`.
5. Restore from the pre-flight spatie blob via FM session 022's restore-to-fresh-node primitive (or manual `pg_restore` if FM is unavailable).
6. Bring the rest of the stack up: `docker compose up -d`.

The Dockerfile's `postgresql-client-N` pin can stay at the new major during rollback (newer client against older server is the *opposite* skew direction — newer pg_dump against older server breaks; older pg_dump against newer server works for the cases that don't need new directives, but the ingest path on backup-restore would still misbehave). Revert the Dockerfile pin too if rolling back operationally.

## Failure-mode reference

- **`FATAL: database files are incompatible with server`** — the volume wasn't wiped. Re-run step 2 (`docker compose down -v`).
- **`role "postgres" does not exist`** during `psql` load — the new container's first-boot init didn't run, usually because an earlier `up` populated the volume with a non-default state. Re-run step 2.
- **Dump load aborts mid-stream with foreign-key errors** — the dump's statement order isn't naive-FK-safe. `pg_dumpall` is supposed to be safe (it dumps in dependency order), but if it isn't, `--disable-triggers` on the load side bypasses constraint checks during insert: `psql -U postgres --set ON_ERROR_STOP=on -1 < /tmp/pg-pre-upgrade.sql` (single transaction, full rollback on first error) is the diagnostic mode; per-statement load is the fallback.
- **Application can't connect after step 5** — the application's DB credentials may have been re-created with a new password by the dump's role-creation statements. Confirm the `.env` `DB_PASSWORD` matches what the dump set (the dump preserves the original password hash, so this is unusual but possible if the original install used a non-standard role-creation path).

## Notes

- The CRM contract surface is unchanged across this upgrade. FM does not need to know about the major bump; the `/api/health` and `/api/backup/blob` endpoints behave identically.
- The Fleet Manager fleet should run this runbook on every install in sequence, not in parallel — a partial fleet on the new major + partial on the old is supportable (each install backs up itself), but FM's restore-from-blob primitive (FM 022) requires source and destination to share a major. Mixed-major fleets cannot cross-restore until the laggards complete the upgrade.
- The data-dir wipe + dump load is idempotent — if interrupted, re-run from step 2 with the same `/tmp/pg-pre-upgrade.sql`.
