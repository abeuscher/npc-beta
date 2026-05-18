# CI Fix — Tests error: `could not translate host name "postgres"`

**Status:** root-caused, fix is a 1-step-per-job workflow change. No test-code change.

## Symptom

Every test in CI (`tests.yml`) errors with:

```
SQLSTATE[08006] [7] could not translate host name "postgres" to address:
Temporary failure in name resolution
(Connection: pgsql, SQL: drop database if exists "nonprofitcrm_test_test_4")
```

## Root cause

`phpunit.xml` forces the DB host:

```xml
<env name="DB_HOST" value="postgres" force="true"/>
```

`force="true"` makes PHPUnit **override the process/CI environment**. So although `tests.yml` correctly sets `DB_HOST: 127.0.0.1` in each step's `env:`, the moment `php artisan test` boots PHPUnit the value is clobbered back to `postgres`.

- Docker dev: `postgres` is the Compose service hostname → resolves.
- GitHub Actions: the job runs on `ubuntu-latest` with Postgres as a **service container** reachable at `127.0.0.1:5432`. There is no host named `postgres` → name-resolution failure on the first DB connection → every worker/test errors.

Notes:
- `php artisan migrate --force` earlier in the job succeeds because it runs as a plain artisan command using the workflow env (`127.0.0.1`); only `php artisan test` boots PHPUnit and hits the forced `postgres`.
- The `_test_4` suffix is just `--parallel` (paratest) creating per-worker DBs — **parallelization did not cause this**; it exposed a pre-existing forced-host mismatch in the new CI path.
- Redis/cache/queue are forced to non-network drivers in `phpunit.xml`, so only `DB_HOST` matters. Postgres is the whole problem.

## Fix (do this)

In `.github/workflows/tests.yml`, add this step to **both** test jobs (the `php artisan test --parallel --exclude-group=slow` job *and* the `php artisan test --group=slow` job), positioned **after** PHP/deps setup and **before** the migrate/test steps:

```yaml
      - name: Alias the postgres service hostname for the forced phpunit env
        run: echo "127.0.0.1 postgres" | sudo tee -a /etc/hosts
```

That is the entire fix. The Postgres service is mapped on `127.0.0.1:5432` and `phpunit.xml` forces port `5432`, so aliasing the hostname is sufficient. It keeps the forced-env safety contract (prevents tests ever hitting the wrong DB) and makes CI mirror Docker semantics. No change to `phpunit.xml`, local dev, or any test.

## Do NOT do this instead

Removing `force="true"` from the `DB_*` entries in `phpunit.xml` is more idiomatic but weakens an intentional safety mechanism, risks local Docker runs that depend on the forced values, and interacts with paratest's `_test_N` DB-name derivation. Not worth it for a CI hostname mismatch. Use the `/etc/hosts` alias.

## Verify

After the change, push the branch and confirm both `tests.yml` jobs go green (the fast `--parallel --exclude-group=slow` job and the `--group=slow` job). Locally nothing changes — `docker compose exec app php artisan test` continues to work because `postgres` already resolves on the Compose network.
