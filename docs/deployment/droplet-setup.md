# Droplet Setup — One-Time Steps

Run these steps once when provisioning a new server. After this is done, all future deploys happen automatically via GitHub Actions on push to `main`.

---

## 1. Create the Droplet

In the DigitalOcean control panel:

- **Image**: Marketplace → Docker on Ubuntu 22.04 (comes with Docker and Docker Compose pre-installed)
- **Size**: 1 GB RAM / 1 vCPU ($6/month basic droplet) is sufficient for pre-beta
- **Authentication**: Add your SSH public key

Note the droplet's IP address — you'll need it below.

---

## 2. Create a deploy user

SSH in as root, then:

```bash
useradd -m -s /bin/bash deploy
usermod -aG docker deploy
mkdir -p /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
```

---

## 3. Generate a deploy SSH keypair

On your **local machine** (not the server):

```bash
ssh-keygen -t ed25519 -C "github-deploy" -f ~/.ssh/nonprofitcrm_deploy
```

This creates two files:
- `~/.ssh/nonprofitcrm_deploy` — private key
- `~/.ssh/nonprofitcrm_deploy.pub` — public key

Copy the public key to the server:

```bash
cat ~/.ssh/nonprofitcrm_deploy.pub
```

On the server, paste it into the deploy user's authorized_keys:

```bash
nano /home/deploy/.ssh/authorized_keys
# paste the public key, save
chmod 600 /home/deploy/.ssh/authorized_keys
chown -R deploy:deploy /home/deploy/.ssh
```

---

## 4. Add GitHub Secrets

In the GitHub repository → Settings → Secrets and variables → Actions, add:

| Secret name      | Value |
|------------------|-------|
| `DEPLOY_SSH_KEY` | Contents of `~/.ssh/nonprofitcrm_deploy` (the **private** key) |
| `DEPLOY_HOST`    | The droplet's IP address |
| `DEPLOY_USER`    | `deploy` |

---

## 5. Create the app directory on the server

```bash
mkdir -p /opt/nonprofitcrm
chown deploy:deploy /opt/nonprofitcrm
```

---

## 6. Copy the production compose file to the server

From your local machine:

```bash
scp -i ~/.ssh/nonprofitcrm_deploy docker-compose.prod.yml deploy@YOUR_DROPLET_IP:/opt/nonprofitcrm/docker-compose.prod.yml
```

---

## 7. Create the .env file on the server

SSH in as the deploy user:

```bash
ssh -i ~/.ssh/nonprofitcrm_deploy deploy@YOUR_DROPLET_IP
cd /opt/nonprofitcrm
nano .env
```

Minimum required values for a working production install:

```dotenv
GHCR_OWNER=your-github-username

APP_NAME="NonProfitCRM"
APP_ENV=production
APP_KEY=          # generate with: php artisan key:generate --show (run locally)
APP_DEBUG=false
APP_TIMEZONE=UTC
APP_URL=http://YOUR_DROPLET_IP

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=nonprofitcrm
DB_USERNAME=postgres
DB_PASSWORD=      # choose a strong random password

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

MAIL_MAILER=log

ADMIN_NAME="Admin"
ADMIN_EMAIL=      # your email address
ADMIN_PASSWORD=   # choose a strong password
```

---

## 8. First deploy

Push to `main` (or merge a branch). GitHub Actions will:

1. Build the `app` and `web` Docker images
2. Push them to GHCR
3. SSH into the droplet, pull the images, and start the stack
4. Run `php artisan migrate --force`

Watch the run in GitHub → Actions. When it goes green, visit `http://YOUR_DROPLET_IP` to confirm the app is up.

---

## 9. Seed the database (first time only)

SSH into the server and run:

```bash
docker compose -f /opt/nonprofitcrm/docker-compose.prod.yml exec app php artisan db:seed
```

This creates the admin user defined in your `.env` (`ADMIN_EMAIL` / `ADMIN_PASSWORD`).

---

## Notes

- **Storage files** (uploaded media) are served through PHP-FPM rather than nginx directly. This is a known limitation of the two-image architecture and will be addressed when the media library is built (session 061).
- **SSL/HTTPS** is not configured in this session. A domain and Caddy-based SSL are planned for a later session.
- **Database** lives in a named Docker volume (`postgres_data`) on the droplet. It survives container restarts and image updates. To move to a managed database later, `pg_dump` out and `pg_restore` into the new host, then update `DB_HOST` in `.env`.
