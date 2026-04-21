# Fleet Manager — Requirements & Product Description

**Status:** Draft v1
**Author:** Al
**Context:** Companion operational tool for NonprofitCRM

---

## Purpose

Fleet Manager is an internal operational tool for monitoring NonprofitCRM installations deployed across client droplets. Its role is to give a single operator (me) confidence that every client's system is healthy, backed up, and reachable — without requiring me to check each one manually.

It is not a product. It is not client-facing. It is not multi-tenant. It exists to prevent disasters and surface problems before clients find them.

## Scope

### In scope for v1

- Central registry of client installations
- Scheduled health polling of each install
- Dashboard showing current state across the fleet
- Historical health data with tiered retention
- Email alerts on state transitions
- Per-client operational notes
- Backup freshness tracking (reported by clients, read by Fleet Manager)
- SSL certificate expiry monitoring
- Version tracking per install
- A public system status page exposing aggregate uptime

### Explicitly out of scope for v1

- Remote action on client droplets (upgrades, restarts, SSH orchestration)
- Centralized log aggregation
- Performance metrics and graphing (CPU, memory, response times)
- Multi-user roles, permissions, audit logs
- Orchestrating or performing backups (the CRM does this; Fleet Manager observes)
- Direct verification of backup file integrity in object storage
- Client-facing views of any kind beyond the public status page

## Architecture Overview

### Deployment shape

- Standalone Laravel application on its own DigitalOcean droplet
- PostgreSQL for persistence
- Redis for queue backend
- Own failure domain — does not share a droplet with bundle server, thumbnailer, or any client install
- Same operational patterns as the CRM (nginx, deploy scripts, PHP version) to minimize cognitive overhead

### Stack

- **Framework:** Laravel
- **Admin UI:** Filament
- **Database:** PostgreSQL
- **Queue/scheduler:** Redis-backed Laravel queues, Laravel scheduler
- **Auth:** Standard Laravel auth, single user to start
- **Mail:** Whatever transactional service the CRM uses (Resend, likely)

### Communication model

- **Polling, not push.** Fleet Manager initiates all health checks on a schedule.
- Each client install exposes an authenticated health endpoint.
- Fleet Manager holds a per-client API key; client verifies on each request.
- No inbound connections from clients to Fleet Manager in v1.

## Data Model (initial sketch)

Names and exact fields to be refined in the Claude Code planning session, but the baseline entities:

### Clients (install registry)
- Display name, slug
- Primary domain, droplet IP/hostname
- API key for health polling
- Current deployed version
- Install date, contact info
- Free-text notes field (timestamped entries)
- Active / archived status

### Health checks
- Foreign key to client
- Timestamp
- Overall status (green / yellow / red)
- Subchecks — app responsive, database reachable, Redis reachable, disk usage, backup freshness, SSL expiry
- Raw response payload

### Incidents
- Derived from health check transitions
- Start time, end time, duration
- Client, severity, summary
- Used to drive alerting and the status page

### Alerts
- Sent notifications log
- For audit and to prevent duplicate paging during ongoing incidents

## Polling & Health Checks

### Cadence
- Scheduler dispatches a "poll all active clients" job every minute (tunable)
- Each client poll is its own queued job so a slow client doesn't block others
- Failed polls retry with backoff; N consecutive failures = red

### What gets checked
Collected from the client's authenticated health endpoint:
- Application responding to HTTP
- Database connection live
- Redis connection live
- Disk usage percentage on droplet
- Timestamp of last successful backup
- SSL certificate expiry date
- Current application version
- Any client-defined custom checks (future)

### Status interpretation
- **Green:** all subchecks passing
- **Yellow:** a subcheck is in a warning threshold (e.g., disk >80%, SSL <30 days, backup age 24–36h)
- **Red:** a subcheck is failing (e.g., DB unreachable, backup >36h, SSL <7 days, poll itself failed N times)

Thresholds configurable per-client to accommodate unusual situations.

## Alerting

v1 is deliberately simple:

- Any transition to red triggers an email alert
- Any transition from red back to green triggers a resolution email
- One alert per incident — do not re-send while the incident is ongoing
- Email to the operator address; no SMS, Pushover, or other channels in v1

The system is designed to scale alert sophistication later (quiet hours, severity routing, multiple channels) but none of that is built now.

## Data Retention

Health check history follows tiered retention:

- **Full resolution for 30 days:** every individual health check preserved. Supports detailed forensics of recent incidents.
- **Monthly rollups for 3 years:** after 30 days, individual checks are aggregated into a single monthly summary per client (uptime percentage, incident count, notable events). Individual records purged.
- **Permanent:** incident records (not individual checks), client registry, notes.

Implementation detail deferred to Claude Code session, but the shape matters now: Fleet Manager must be able to answer "what happened last Tuesday" for 30 days and "how was Client X doing in Q2 last year" indefinitely.

## Public Status Page

A read-only status page exposed at a public URL (`status.<domain>` or similar) showing aggregate fleet health — not per-client detail unless individual clients opt in.

Logically lives within Fleet Manager since it has the data. Implementation deferred, but mentioned here so it's part of the architectural picture from the start.

Open question for later: whether individual clients appear by name on a public page, or only aggregate metrics. Leaning aggregate-only for v1.

## Security Posture

- Single operator account; standard Laravel auth with a strong password and 2FA if Filament supports it cleanly
- Per-client API keys stored encrypted at rest
- Fleet Manager can read status from clients but cannot take action on them in v1
- Compromise of Fleet Manager should not compromise client data — it holds no database credentials, no SSH keys, no backups
- HTTPS-only, standard hardening

## Capsize Testing Before Launch

Before declaring v1 ready:

- Destroy a client install and confirm Fleet Manager alerts correctly
- Simulate database-down, Redis-down, disk-full, and backup-stale states and confirm each is detected
- Attempt to hit a client's health endpoint without a valid API key and confirm rejection
- Attempt to compromise Fleet Manager itself and confirm blast radius is limited

## Points of Discussion for Claude Code Planning Session

These are deliberately left open for the technical planning session, either because they depend on specifics of the CRM's internal architecture or because they're better resolved with the code in front of us:

1. **Agent endpoint contract.** Exact shape of the health endpoint that ships inside the CRM — URL path, auth scheme (bearer token vs. signed request), response schema, version negotiation. Both sides must implement against this, so it needs to be specified before either is built.

2. **Backup implementation details.** How the CRM actually performs backups — `pg_dump` invocation, compression, encryption at rest, object storage credentials model, retention policy on the client side, how success is recorded for Fleet Manager to observe. The storage target (DO Spaces, likely) is decided but the mechanics are not.

3. **Retention implementation.** How the tiered retention is actually enforced — scheduled job cadence, rollup query shape, whether rollups happen in Postgres directly or in application code.

4. **Version tracking mechanism.** How the CRM reports its current version to the health endpoint — baked into the image at build time, read from a VERSION file, derived from git SHA.

5. **SSL expiry check mechanism.** Whether the client reports its own cert expiry (simpler, requires the CRM to read its own cert) or Fleet Manager checks externally (more honest, handles cases where the CRM can't see its own cert).

6. **Credential bootstrapping.** How an API key gets generated and installed when a new client comes online — manual at first is fine, but the workflow should be named.

7. **Database schema specifics.** Field names, indexes, whether `health_checks` is partitioned by time given retention patterns, how rolled-up data is stored.

## v1 Success Criteria

Fleet Manager v1 is done when:

- Every client install is in the registry and being polled
- The dashboard shows current state at a glance
- I get email alerts within a few minutes of a real problem
- Historical data is queryable for at least 30 days
- I have personally tested the alerting by breaking things on purpose
- The public status page is live

## Deferred to Later Versions

Captured here so they don't get lost but don't distract from v1:

- Upgrade orchestration (push a new CRM version to one or all clients)
- Automated backup restoration testing against a scratch environment
- Backup integrity verification (read the file from object storage, check structure)
- Cross-provider backup redundancy (Backblaze B2 as secondary)
- Performance metrics and graphing
- Multi-channel alerting (SMS, Pushover, etc.)
- Alert sophistication (quiet hours, severity, grouping)
- Per-client opt-in to public status page with individual naming
- Additional operators with role separation