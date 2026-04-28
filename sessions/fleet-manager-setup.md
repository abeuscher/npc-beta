# Fleet Manager — Local Setup Guide (WSL2)

Quick orientation for bootstrapping the Fleet Manager repo on WSL2, mirroring the CRM setup that delivered the performance win.

---

## The order of operations (and why)

Your imagined order — "spin up Docker → commit → swap to WSL" — has the WSL2 step at the wrong end. The performance lever is **placement of the project files in the WSL2 Linux filesystem**, *not* the Windows filesystem mounted at `/mnt/c/` or `/mnt/e/`. Cross-filesystem I/O between Windows and WSL2 is the bottleneck. Once the project lives at `/home/<you>/fleetmanager/`, every Docker bind-mount, every Composer install, every `php artisan` run is fast.

So the actual order is **WSL placement first**, then everything else inside it:

1. Create the project directory inside the WSL2 home (`/home/<you>/fleetmanager`)
2. Initialize the Laravel app inside that directory
3. Initialize git
4. Author the Docker compose stack (mirror the CRM's shape)
5. Build & start
6. Smoke-test
7. Push to GitHub

There is no "swap to WSL once running" step. You start in WSL2 and stay there.

---

## Prerequisites (already in place from the CRM setup)

- WSL2 with Ubuntu running
- Docker Desktop with **WSL2 Integration** enabled for the Ubuntu distro (Settings → Resources → WSL Integration)
- Git configured inside WSL2 (`~/.gitconfig`, an SSH key in `~/.ssh/` registered with GitHub)

If `docker compose ps` works from inside Ubuntu (`wsl -d Ubuntu` or just opening the Ubuntu terminal), you're good.

---

## Step-by-step

### 1. Place the project in WSL2 native filesystem

```bash
cd ~
mkdir fleetmanager
cd fleetmanager
pwd                          # should print /home/<you>/fleetmanager
```

If `pwd` shows anything starting with `/mnt/c/` or `/mnt/e/`, **stop and move** — you'll lose the performance win.

### 2. Initialize the Laravel skeleton (without installing PHP on the host)

Run Composer inside a throwaway container so you don't need PHP on WSL2 itself:

```bash
docker run --rm -v "$(pwd):/app" -w /app composer:2 \
  composer create-project laravel/laravel .
```

This drops the Laravel 11 skeleton into the current directory. File ownership ends up as your user on the host.

### 3. Initialize git

```bash
git init
git add .
git commit -m "Initial commit — Laravel skeleton"
```

### 4. Author the Docker compose stack

Easiest path: copy the CRM's `Dockerfile`, `docker-compose.yml`, and `docker/` directory from `~/nonprofitcrm/` as a starting template, then rename and re-port. The substitutions are mechanical:

| CRM | Fleet Manager |
|---|---|
| `nonprofitcrm` (network name, container name prefix, project) | `fleetmanager` |
| host port `80` (nginx) | `8001` |
| host port `5432` (postgres) | `5433` |
| host port `6379` (redis) | `6380` |
| `nonprofitcrm_app`, `_nginx`, `_postgres`, `_redis`, `_worker` | `fleetmanager_app`, `_nginx`, `_postgres`, `_redis`, `_worker` |

Then add to the FM's `app` and `worker` service definitions in `docker-compose.yml`:

```yaml
    extra_hosts:
      - "host.docker.internal:host-gateway"
```

This is the WSL2 / Linux directive that makes `host.docker.internal` resolve to the WSL2 host gateway from inside the FM containers — required for FM to reach the CRM at `http://host.docker.internal/api/health` once the CRM-side endpoint exists. The CRM does not need any change.

### 5. `.env` setup

Copy `.env.example` to `.env`, set `APP_NAME=FleetManager`, set `DB_*` to match the FM postgres (`DB_HOST=postgres`, `DB_PORT=5432` — that's the **container's** port, not the host-published `5433`), set `REDIS_HOST=redis`. Same Resend credentials as the CRM for now (per the planning spec — separate Resend account post-launch).

### 6. Build and start

```bash
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Visit `http://localhost:8001/` — you should get the default Laravel landing page.

### 7. Smoke-test inter-stack reachability with the CRM

With **both** stacks running (CRM on host port 80, FM on host port 8001):

```bash
# From inside the FM app container:
docker compose exec app curl -v http://host.docker.internal/
```

Expect HTML from the CRM's home page. If you get `connection refused`:

- Confirm the CRM's nginx is up: `docker compose --project-directory ~/nonprofitcrm ps`
- Confirm the `extra_hosts` directive landed in FM's compose file
- Confirm the CRM is publishing host port 80 (it does by default — see CRM's `docker-compose.yml`)

### 8. Push to GitHub

Create the empty repo on GitHub first (via web UI). Then:

```bash
git remote add origin git@github.com:<you>/fleetmanager.git
git branch -M main
git push -u origin main
```

If your GitHub SSH key isn't set up inside WSL2 (separate from your Windows-side SSH), generate one in WSL2 and add the public key to GitHub:

```bash
ssh-keygen -t ed25519 -C "<email>"
cat ~/.ssh/id_ed25519.pub      # paste into github.com → Settings → SSH keys
```

---

## Bring with you from the CRM repo

Files to seed the FM repo from this CRM repo (copy, then adapt to FM perspective):

- `sessions/fleet-manager-planning-spec.md` → the FM repo's product spec (rename to `sessions/product-spec.md` or keep the name; trim CRM-vantage phrasing)
- `docs/fleet-manager-agent-contract.md` → the FM repo's local cached copy of the contract spec, e.g. at `docs/imported/fleet-manager-agent-contract.md`. The FM repo treats this as **read-only**; the canonical version lives in the CRM repo and is fetched via WebFetch on every boundary-touching session
- `CLAUDE.md` → adapt the CRM's session-pacing rules into the FM repo's CLAUDE.md (session branch naming, never push or merge, etc.)
- `sessions/template-base-prompt.md` / `sessions/template-session-prompt.md` / `sessions/template-session-log.md` → the prompt templates. The FM repo's base-prompt template carries the **same cross-repo coordination flag** the CRM template carries; reference `https://raw.githubusercontent.com/abeuscher/npc-beta/main/docs/fleet-manager-agent-contract.md` as the canonical source

---

## Common gotchas

- **Wrong filesystem placement.** Detected by `pwd` showing `/mnt/...`. Move the directory to `~/fleetmanager`.
- **Port collisions.** If the CRM is running, you can't also bind host port 80. Use 8001 for FM nginx; same shift logic for postgres and redis.
- **Docker Desktop integration disabled.** `docker compose` from inside WSL2 fails. Open Docker Desktop → Settings → Resources → WSL Integration → enable Ubuntu.
- **Git from WSL2 vs Windows.** WSL2 has its own `~/.ssh/`, distinct from Windows-side keys. First-time push from WSL2 may need a fresh ssh-keygen + GitHub SSH key registration.
- **Composer running out of memory inside the throwaway container.** If step 2 fails, add `-e COMPOSER_MEMORY_LIMIT=-1` to the docker run command.
- **`host.docker.internal` resolves but connection still refused.** The host-gateway directive resolves the *name* but the CRM container still has to be listening on the host. Confirm with `docker compose --project-directory ~/nonprofitcrm ps` that the CRM's nginx is up and bound to `0.0.0.0:80`.

---

## What this doc is not

- Not the CRM's deploy guide (the CRM has its own deploy server at DigitalOcean, with `docker-compose.prod.yml`). FM will get its own deploy procedure when it's ready to leave dev — that's a separate doc, not this one.
- Not the FM's product spec — that's `sessions/fleet-manager-planning-spec.md` in the CRM repo (and after duplication, the equivalent doc in the FM repo).
- Not the agent-contract spec — that's `docs/fleet-manager-agent-contract.md` (currently a v0.0.0 stub; first real version lands at the CRM-side Fleet Manager Agent Phase 1 session).
