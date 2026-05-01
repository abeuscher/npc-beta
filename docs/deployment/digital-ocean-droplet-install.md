# Digital Ocean Droplet Install

End-to-end runbook for provisioning a new NonprofitCRM droplet. Written from the rectanglemachine.com (public-demo) provisioning at session 252, after the original `droplet-setup.md` proved stale (it predates SSL/mTLS, the conf-file gap, and the 2375/2376 cleanup). Use this as the canonical procedure for any new droplet; treat `droplet-setup.md` as historical reference only.

The example throughout uses the public-demo droplet (`147.182.214.147` / `rectanglemachine.com` / `release/public-demo` branch / `DEMO_DEPLOY_*` secrets). For other environments substitute accordingly — the steps are identical, only names differ.

---

## 0. Prereqs

Before starting:

- A GitHub workflow file targeting this droplet's branch already exists in `.github/workflows/`. For prod that's `deploy.yml` (push-on-main); for demo that's `deploy-demo.yml` (push-on-`release/public-demo`). If you're spinning up a third instance, copy one of those workflows first and decide on a branch name + image-tag namespace.
- The domain you're parking has registrar access (you'll create A records in step 4).
- Local SSH access to your existing droplets (so you can copy reference files like nginx conf).

---

## 1. Provision the droplet

DigitalOcean control panel → Create → Droplets:

- **Image:** Marketplace → **Docker on Ubuntu 22.04** (ships with Docker + Docker Compose pre-installed).
- **Plan:** 1 GB RAM / 1 vCPU is sufficient for pre-Beta-1 / demo. Bump to 2 GB if you'll be running anything memory-intensive.
- **Region:** any.
- **Authentication:** add your SSH public key. Note: if you skip this and use root password, the droplet boots with the DigitalOcean DOTTY console-access key in `/root/.ssh/authorized_keys` — that key expires after a day or two, so add your real key right after first login.
- **Hostname:** descriptive name (e.g. `rectanglemachine`) — appears in the shell prompt later, easier to know which droplet you're on.

Note the droplet's IP address.

---

## 2. Initial security hardening

The Docker marketplace image ships with two security gaps that need closing before public traffic.

**SSH in as root:**
```bash
ssh root@<DROPLET_IP>
```

**a. Remove Docker remote API ports from UFW.**

The marketplace image opens TCP `2375` and `2376` to "Anywhere" — these are the unencrypted/encrypted Docker daemon API ports. If `dockerd` is bound to those ports (some image variants do this), anyone on the internet who finds them can spawn containers and own the box. First check whether anything is actually listening:

```bash
ss -tlnp | grep -E ':237[56] '
```

- **If something's listening** (likely `dockerd`): edit `/etc/docker/daemon.json` (or the systemd unit at `/etc/systemd/system/docker.service.d/`) to remove the `-H tcp://0.0.0.0:2375` flag, then `systemctl restart docker`. Lock the ports down regardless.
- **If nothing's listening**: the rules are dead config from the marketplace image. Either way, drop the rules:

```bash
ufw delete allow 2375/tcp
ufw delete allow 2376/tcp
ufw reload
```

**b. Open :80 and :443 for web traffic.**

```bash
ufw allow 80/tcp
ufw allow 443/tcp
ufw reload
ufw status   # confirm 80 + 443 now show ALLOW
```

**c. Replace the marketplace image's SSH `LIMIT` rule with `ALLOW`.**

The stock Docker on Ubuntu 22.04 image **ships with `LIMIT IN` already configured on `:22`** (both v4 and v6) — visible as `22/tcp LIMIT IN` in `ufw status`. The intent is rate-limiting (drops connections from any source IP exceeding 6 attempts in 30 seconds), but it breaks GitHub Actions deploys: `appleboy/scp-action` opens 5+ rapid SSH sessions per workflow run, trips the LIMIT mid-deploy, and the workflow fails with `dial tcp <ip>:22: i/o timeout` after a partial-success. The dropped packets never reach sshd, so the SSH journal shows nothing — only `/var/log/ufw.log` records the silent drops. For a key-only auth setup, the LIMIT adds little defensive value anyway (sshd rejects bad keys at the auth layer regardless of rate).

**Reliable removal sequence — interactive confirmation is required:**

```bash
ufw status numbered                       # find the [N] number for "22/tcp LIMIT IN"
ufw delete <N>                            # answer y at prompt
ufw allow 22/tcp                          # adds v4 ALLOW; ALSO auto-replaces any v6 LIMIT in place
ufw reload
ufw status verbose                        # confirm both v4 + v6 show ALLOW IN, NOT LIMIT IN
```

After the v4 delete, you may be tempted to `ufw delete <new_N>` for the v6 LIMIT separately — not needed. `ufw allow 22/tcp` updates BOTH protocols in one step (you'll see `Rule added` for v4 and `Rule updated (v6)` in the output).

**Common mistake — don't do this:**
```bash
ufw delete limit 22/tcp 2>/dev/null       # SILENTLY NO-OPS — the 2>/dev/null suppresses
                                          # the y/n prompt UFW expects, so the rule survives.
                                          # `ufw status` will still show LIMIT IN afterwards.
```

Final UFW state should look like:
```
22/tcp     ALLOW IN    Anywhere
80/tcp     ALLOW IN    Anywhere
443/tcp    ALLOW IN    Anywhere
(plus v6 equivalents, all ALLOW IN — no LIMIT anywhere)
```

---

## 3. SSH key for GitHub Actions

The deploy workflow SSHes to the droplet as a specific user with a private key stored in a GitHub secret. The matching public key must be in that user's `authorized_keys` on the droplet. **A keypair sitting unused in `~/.ssh/` is not the same as an authorized key — only `authorized_keys` is consulted at SSH-time.**

For the public-demo, the deploy user is `root` (acceptable for an isolated demo box; for prod consider creating a dedicated `deploy` user per the original `droplet-setup.md` pattern).

**Generate the keypair** (on the droplet, or anywhere — only the public half needs to land on the droplet):

```bash
ssh-keygen -t ed25519 -C "github-deploy-<dropletname>" -f ~/.ssh/github_key -N ""
```

That writes `~/.ssh/github_key` (private) and `~/.ssh/github_key.pub` (public).

**Authorize the public key:**

```bash
cat ~/.ssh/github_key.pub >> /root/.ssh/authorized_keys
chmod 600 /root/.ssh/authorized_keys
```

**Capture the private key for GitHub:**

```bash
cat ~/.ssh/github_key
```

Copy the entire output (including the `-----BEGIN OPENSSH PRIVATE KEY-----` and `-----END OPENSSH PRIVATE KEY-----` lines and the trailing newline). You'll paste this into the GitHub secret in step 9.

**Hygiene note:** the private key doesn't need to live on the droplet long-term — it belongs in GitHub Actions, not on the server. After the deploy works end-to-end, you can `rm ~/.ssh/github_key` from the droplet and keep `github_key.pub` as a record of which key is in `authorized_keys`.

---

## 4. DNS

In your domain registrar's DNS panel, add (using `rectanglemachine.com` as example):

| Type | Host | Value |
|------|------|-------|
| A    | `@`  | `<DROPLET_IP>` |
| A    | `www` | `<DROPLET_IP>` |

CNAME for `www` is an alternative to the second A record, **not in addition** — pick one.

**Verify propagation** before moving on:

```bash
getent hosts rectanglemachine.com           # should return the droplet IP
getent hosts www.rectanglemachine.com
```

If `getent` returns nothing or a stale IP, propagation is still in flight (typically 5–30 min). Wait it out before attempting certbot — the HTTP-01 challenge fails if DNS doesn't resolve to the droplet.

---

## 5. TLS cert via certbot

```bash
apt install -y certbot
```

Stop anything that might be bound to :80 (certbot --standalone needs the port free):

```bash
docker compose -f /opt/nonprofitcrm/docker-compose.prod.yml down 2>/dev/null
ss -tlnp | grep ':80 '   # confirm nothing is listening
```

Request the cert covering both names (one cert serves both, saves a second certbot run later):

```bash
certbot certonly --standalone -d rectanglemachine.com -d www.rectanglemachine.com
```

Cert lands at `/etc/letsencrypt/live/rectanglemachine.com/{fullchain,privkey}.pem`.

**If certbot times out with "likely firewall problem":** Let's Encrypt couldn't reach :80 from outside. Most common causes:
1. UFW not allowing :80 — re-check step 2b.
2. DNS hasn't propagated — re-check step 4.
3. DigitalOcean cloud firewall (separate from UFW) blocking :80 — check in DO control panel under Networking → Firewalls.
4. Something else still bound to :80 on the droplet (re-run `ss -tlnp | grep ':80 '`).

---

## 6. App directory and .env

```bash
mkdir -p /opt/nonprofitcrm
cd /opt/nonprofitcrm
nano .env
```

Minimum `.env` for a working install. Fill in the marked fields:

```dotenv
GHCR_OWNER=abeuscher                          # GitHub username/org (same on every droplet)
IMAGE_TAG=demo-latest                         # demo droplets pull demo-latest; prod pulls latest

APP_NAME="Rectangle Machine"
APP_ENV=production
APP_KEY=                                      # generate with: docker compose exec app php artisan key:generate --show
APP_DEBUG=false
APP_TIMEZONE=UTC
APP_URL=https://rectanglemachine.com

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=nonprofitcrm
DB_USERNAME=postgres
DB_PASSWORD=                                  # generate fresh per droplet

REDIS_CLIENT=predis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
CACHE_STORE=redis

BROADCAST_CONNECTION=log
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

MAIL_MAILER=log                               # safe default; never sends real mail

BUILD_SERVER_URL=                             # same value as prod
BUILD_SERVER_API_KEY=                         # same value as prod

ADMIN_NAME="Admin"
ADMIN_EMAIL=
ADMIN_PASSWORD=
```

**Per-droplet rules:**
- `APP_KEY` must be unique per environment. Generate anywhere (`openssl rand -base64 32` prefixed with `base64:` works) and never share across droplets.
- `DB_PASSWORD` must be unique per droplet.
- `APP_URL` matches the droplet's public domain.
- `IMAGE_TAG` selects which image namespace the droplet pulls. Demo droplets pull `demo-latest`, prod pulls `latest`.
- `BUILD_SERVER_URL` + `BUILD_SERVER_API_KEY` can be shared across droplets — the build server's bundle filenames are content-hashed so collisions are impossible.
- Integration keys (Stripe, Mailchimp, Resend, QuickBooks) — sandbox or empty for demo droplets.

---

## 7. nginx conf

The deploy workflow does **not** copy `docker/nginx/prod.conf` to the droplet — only `docker-compose.prod.yml` rides the deploy. The compose file declares a bind-mount:

```yaml
- ./docker/nginx/prod.conf:/etc/nginx/conf.d/default.conf:ro
```

The bind-mount source must exist on the **droplet's filesystem** before `docker compose up`. If missing, Docker silently creates an empty directory at the source path and nginx fails to load.

**This is technical debt** — both prod and demo droplets currently have manually-placed conf files that don't auto-update on deploy. A future session should extend the workflows with an scp step that pushes the conf alongside the compose file. Until then: place manually.

```bash
mkdir -p /opt/nonprofitcrm/docker/nginx
nano /opt/nonprofitcrm/docker/nginx/prod.conf
```

Paste a domain-substituted version of `docker/nginx/prod.conf` from the repo. The four lines that need substitution from prod's beuscher.net version:

```nginx
server_name rectanglemachine.com www.rectanglemachine.com;          # both http + https blocks
ssl_certificate     /etc/letsencrypt/live/rectanglemachine.com/fullchain.pem;
ssl_certificate_key /etc/letsencrypt/live/rectanglemachine.com/privkey.pem;
```

Everything else (mTLS gates, fastcgi, location blocks) stays identical to prod.conf.

---

## 8. fm-client.crt placeholder

The nginx conf references `/etc/nginx/certs/fm-client.crt` for mTLS on `/api/health` and `/api/logs`. The directive needs *some* valid PEM at that path or nginx errors at startup, even if you'll never actually use mTLS on this droplet.

```bash
mkdir -p /opt/nonprofitcrm/nginx-certs
# Quickest: reuse the letsencrypt fullchain as a stand-in. mTLS verify is location-scoped,
# so this PEM is loaded but never validated against unless /api/health or /api/logs is hit.
cp /etc/letsencrypt/live/<your_domain>/fullchain.pem /opt/nonprofitcrm/nginx-certs/fm-client.crt
```

For prod, the operator-pasted real FM client cert goes here (see `docs/runbooks/fleet-manager-cert-paste.md`). For demo and test droplets, the stand-in is fine.

---

## 9. GitHub repository secrets

Settings → Secrets and variables → Actions → New repository secret:

For the public-demo droplet (different droplets have different secret-name prefixes — see the deploy workflow file for the exact names):

| Secret name | Value |
|---|---|
| `DEMO_DEPLOY_HOST` | the droplet IP (e.g. `147.182.214.147`) |
| `DEMO_DEPLOY_USER` | the SSH user (e.g. `root`) |
| `DEMO_DEPLOY_SSH_KEY` | the **entire contents** of `github_key` from step 3, including BEGIN/END markers and trailing newline |

`BUILD_SERVER_API_KEY` is shared repo-wide across all deploy workflows. `BUILD_SERVER_URL` is a `vars` entry, also shared.

**Common gotcha:** when pasting the private key, make sure the trailing newline survives. Some text editors strip it on copy. The key must end with a newline after `-----END OPENSSH PRIVATE KEY-----`.

---

## 10. First deploy

Push to the branch the workflow listens on (`release/public-demo` for the demo, `main` for prod). The workflow will:

1. Build `app` and `web` Docker images, push to GHCR with the appropriate tag.
2. SCP `docker-compose.prod.yml` to `/opt/nonprofitcrm/`.
3. SSH to the droplet, run `docker compose pull` + `up -d` + `migrate --force` + `storage:link` + cache clears + `build:public`.

Watch the run in GitHub → Actions. Common failure modes:

- **`ssh: handshake failed: ssh: unable to authenticate`** — the public key isn't in the droplet's `authorized_keys`, or the private key in the secret doesn't match. Re-check step 3.
- **`dial tcp <ip>:22: i/o timeout` after partial success** — UFW `LIMIT` on :22 is dropping subsequent connections. Re-check step 2c.
- **`docker compose up` errors about empty config** — nginx conf bind-mount source is missing; re-check step 7.

**Workflow goes green but the site 500s on login** — the workflow's `migrate --force` step doesn't reliably run on first install. This is the expected first-deploy state, not a failure mode. Proceed to step 11 to run the migration manually.

---

## 11. First-time database migration + seed (REQUIRED — not optional)

**The deploy workflow's `migrate --force` step does not reliably create tables on first install.** Confirmed on both prod and the public-demo droplet — the first-deploy migrate exits 0 (so the workflow goes green) but `migrate:status` afterwards reports `Migration table not found`, and any DB-touching action 500s. Suspected cause is a race between `docker compose up -d` returning and the postgres container finishing its first-time data-directory init, but unconfirmed and not the priority to debug — the workaround is documented and reliable.

**After the first deploy goes green, immediately SSH in and run the migration manually:**

```bash
# Confirm the symptom (optional sanity check):
docker exec nonprofitcrm_app php artisan migrate:status
# If it says "Migration table not found", the workflow's migrate didn't take.

# Wipe + re-migrate + seed (admin user, roles, base pages, sample images all land):
docker exec nonprofitcrm_app php artisan migrate:fresh --seed --force
docker exec nonprofitcrm_app php artisan storage:link
```

Then log in to `https://<your_domain>/admin` with the `ADMIN_EMAIL` / `ADMIN_PASSWORD` from `.env`. For demo droplets, use the **Random Data Generator** widget on the dashboard to populate scrub contacts/donations/events/etc.

**Symptom if you skip this step:** login returns 500. The Laravel log will show `SQLSTATE[42P01]: Undefined table: 7 ERROR: relation "roles" does not exist` (Spatie's `roles` table is the first one the seed-or-login flow touches; the deeper truth is *no* tables exist yet).

For a wholesale wipe-and-reseed later (preserving sample images via the seeder):

```bash
docker exec nonprofitcrm_app sh -c 'rm -rf storage/app/public/* storage/media-library/temp/* && php artisan migrate:fresh --seed --force'
```

**Architectural debt:** the deploy workflow should either (a) wait for postgres health before running migrate (`docker compose up --wait` if the compose file declares healthchecks), or (b) the workflow's migrate step should be retried on the "no such table"/"connection refused" failure modes. Either fix would eliminate the manual-migrate step. Until then, treat the manual migrate as a required step, not optional.

---

## 12. TLS cert auto-renewal

Certbot installs a renewal timer by default. Verify:

```bash
systemctl status certbot.timer
certbot renew --dry-run
```

Renewal needs :80 free briefly. If nginx is running on :80 (it is, after step 10), the renewal will fail unless certbot has a deploy-hook to stop nginx and restart it. For tonight's-shipped state, this works because certbot's `--standalone` mode binds :80 and nginx releases it temporarily — but in practice it's better to switch to the `--webroot` plugin or add a `deploy-hook` script. **File this as follow-up;** the cert is good for 90 days from issue.

---

## Recovery — common situations

**Lost SSH access to the droplet.** Use DigitalOcean's recovery console (web-based, via the droplet page → Access). The DOTTY-managed key it injects expires after a day; if it's expired, request a fresh recovery via the DO panel.

**Workflow has been failing and you need to test SSH directly.** From your local machine:
```bash
ssh -i /tmp/private_key root@<DROPLET_IP> 'whoami'   # paste the secret value to /tmp/private_key first
```
If this works locally but the workflow fails, the secret formatting in GitHub is the suspect (usually a missing trailing newline).

**Need to wipe and start over.** From the droplet:
```bash
cd /opt/nonprofitcrm
docker compose -f docker-compose.prod.yml down -v   # -v wipes named volumes — DESTRUCTIVE, drops the DB
docker image prune -af
```
Then re-trigger the workflow.

---

## Open architectural debt (not blocking, file later)

- **First-deploy migrate step doesn't run.** The workflow's `migrate --force` returns success but no migrations actually land on first install — confirmed on both prod and public-demo. Manual migrate (step 11) is the workaround. Real fix: add a postgres healthcheck to `docker-compose.prod.yml` and use `docker compose up --wait`, OR retry migrate on connection-refused / no-such-table.
- **nginx conf is not in the deployment pipeline.** Both prod and demo need `docker/nginx/prod.conf` placed manually and re-placed on every change. Future fix: extend each deploy workflow with an scp step that pushes the right conf (e.g. `docker/nginx/demo.conf` for demo) into `/opt/nonprofitcrm/docker/nginx/prod.conf` on the droplet.
- **TLS cert renewal nginx-coordination.** Certbot's `--standalone` mode and a long-running nginx process don't coordinate cleanly. Switch to `--webroot` or add a deploy-hook before any cert hits its 90-day window.
- **`droplet-setup.md` is stale.** Predates SSL/mTLS, the conf-file gap, and the 2375/2376 cleanup. Either deprecate it (point to this doc) or merge the two.
