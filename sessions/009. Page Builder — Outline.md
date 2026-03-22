# Session 009 Outline — Deployment

> **Session Preparation**: This is a planning outline, not a complete implementation prompt.
> At the start of this session, read this outline, confirm the target environment and tooling
> with the user, and expand into a full implementation prompt. Deployment architecture is
> highly environment-specific — do not assume anything carried over from dev without verifying.

---

## Goal

Get the application deployable to a staging environment reachable by URL. Surfacing the app outside of local Docker early will catch architectural problems (environment config, asset compilation, queue setup, storage paths, database connectivity) before they compound. A production-ready deployment pipeline can be hardened later; the goal here is a working, repeatable deployment process.

---

## Key Decisions to Make at Session Start

- **Target environment**: Where is staging hosted? Laravel Forge + DigitalOcean/Linode? A bare VPS? Fly.io? Railway? The entire session shape depends on this answer.
- **Deployment method**: Forge (push-to-deploy), GitHub Actions CI/CD, manual Envoyer, or manual SSH + artisan? Decide before building.
- **Domain/subdomain**: Is there a staging domain ready, or do we use a raw IP for now?
- **Queue driver**: Does the staging env use Redis, database, or sync? This affects worker setup.
- **Storage**: Local disk vs S3-compatible for media uploads? Decide before configuring.
- **Environment secrets**: How are `.env` values managed in the target environment? Forge env editor, GitHub secrets, dotenv vault, something else?

---

## Scope

**In:**
- Working deployment to a staging URL
- `APP_ENV=staging` or `production` with correct config
- Database migrations run on deploy
- Asset compilation (Vite build) in CI or on server
- Basic queue worker running (even if `sync` driver for now)
- Storage link configured
- Health check route (`/up` already exists in Laravel)
- Documented deployment runbook (even if short)

**Out:**
- Zero-downtime deployment (Envoyer-style atomic deploys) — future hardening
- Full CI test suite on push (can add later)
- CDN / asset distribution
- Auto-scaling or container orchestration

---

## Rough Build List

- Confirm and document target environment and credentials
- Set up server / hosting account (if not done)
- Configure `.env` for staging: APP_URL, DB, mail, queue, storage
- Deployment script or Forge deploy script
- Vite production build step
- Migration step in deploy pipeline
- Queue worker setup (systemd service, Forge daemon, or sync)
- Storage symlink
- Verify public frontend is reachable and admin panel loads
- Write `docs/deployment.md` runbook

---

## Open Questions at Planning Time

- Is there an existing server or hosting account to deploy to, or does one need to be provisioned?
- Is there a domain name available for staging?
- Are there any SMTP/email credentials available for staging (even Mailtrap)?

---

## What This Unlocks

- A real URL to test against during all future sessions
- Catches environment-specific bugs early
- Gives stakeholders something to look at
- Required before any email/notification work can be properly tested
